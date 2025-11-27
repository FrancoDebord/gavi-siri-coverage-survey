<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use League\Csv\Reader;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;

class MergeODKData extends Command
{
    protected $signature = 'odk:merge';
    protected $description = 'Fusionne les fichiers ODK, ajoute main_key et marque les ménages et mères éligibles.';

    public function handle()
    {
        $path = storage_path('app/public/odk');

        // === Chargement CSV ===
        $mainCsv   = Reader::createFromPath("$path/cov25c4v1.csv", 'r')->setHeaderOffset(0);
        $cgCsv     = Reader::createFromPath("$path/cov25c4v1-cg.csv", 'r')->setHeaderOffset(0);
        $childCsv  = Reader::createFromPath("$path/cov25c4v1-smc_cov_hh_cg_child_2023.csv", 'r')->setHeaderOffset(0);

        $mainRecords   = collect(iterator_to_array($mainCsv->getRecords()));
        $cgRecords     = collect(iterator_to_array($cgCsv->getRecords()));
        $childRecords  = collect(iterator_to_array($childCsv->getRecords()));

        // === Colonnes clés du fichier principal ===
        $keyColumns = [
            'today', 'l1', 'l2', 'l3', 'village_parent', 'village', 'fieldworker',
            'household', 'current_location-Latitude', 'current_location-Longitude',
            'current_location-Altitude', 'current_location-Accuracy',
            'visit_num', 'household_survey_status'
        ];

        $this->info('Chargement des fichiers Excel de référence...');

        // === Référentiels Villages & Fieldworkers ===
        $villagesMap = $this->loadExcelMap("$path/liste_villages.xlsx");
        $fwMap = $this->loadExcelMap("$path/fieldworkers.xlsx");

        $this->info('Fusion des fichiers...');

        // === Étape 1 : enrichir le fichier principal ===
        $mainEnriched = $mainRecords->map(function ($main) use ($villagesMap, $fwMap, $keyColumns) {
            $main['village_name'] = $villagesMap[$main['village']] ?? null;
            $main['fieldworker_name'] = $fwMap[$main['fieldworker']] ?? null;

            $ordered = [];
            foreach ($keyColumns as $col) $ordered[$col] = $main[$col] ?? null;
            $ordered['village_name'] = $main['village_name'];
            $ordered['fieldworker_name'] = $main['fieldworker_name'];
            foreach ($main as $k => $v) if (!isset($ordered[$k])) $ordered[$k] = $v;
            return $ordered;
        });

        // === Étape 2 : mères reliées au principal ===
        $cgMerged = $cgRecords->map(function ($cg) use ($mainEnriched, $keyColumns) {
            $parentKey = $cg['PARENT_KEY'] ?? null;
            $main = $mainEnriched->firstWhere('KEY', $parentKey);
            if ($main) {
                foreach ($keyColumns as $col) $cg[$col] = $main[$col] ?? null;
                $cg['village_name'] = $main['village_name'];
                $cg['fieldworker_name'] = $main['fieldworker_name'];
                $cg['main_key'] = $main['KEY'];
            }
            $ordered = [];
            foreach ($keyColumns as $col) $ordered[$col] = $cg[$col] ?? null;
            $ordered['village_name'] = $cg['village_name'] ?? null;
            $ordered['fieldworker_name'] = $cg['fieldworker_name'] ?? null;
            $ordered['main_key'] = $cg['main_key'] ?? null;
            foreach ($cg as $k => $v) if (!isset($ordered[$k])) $ordered[$k] = $v;
            return $ordered;
        });

        // === Étape 3 : enfants reliés aux mères et au principal ===
        $childMerged = $childRecords->map(function ($child) use ($cgMerged, $villagesMap, $fwMap, $keyColumns) {
            $parentKey = $child['PARENT_KEY'] ?? null;
            $cg = $cgMerged->firstWhere('KEY', $parentKey);

            if ($cg) {
                $child = array_merge($cg, $child);
                $child['main_key'] = $cg['main_key'] ?? null;
            }

            $child['village_name'] = $villagesMap[$child['village']] ?? $child['village_name'] ?? null;
            $child['fieldworker_name'] = $fwMap[$child['fieldworker']] ?? $child['fieldworker_name'] ?? null;

            $sleep = $child['child_sleep_last_night'] ?? null;
            $age = $child['age_months_est'] ?? null;
            $child['Eligible'] = ($sleep == 1 && $age >= 6 && $age <= 30);

            $ordered = [];
            foreach ($keyColumns as $col) $ordered[$col] = $child[$col] ?? null;
            $ordered['village_name'] = $child['village_name'];
            $ordered['fieldworker_name'] = $child['fieldworker_name'];
            $ordered['main_key'] = $child['main_key'] ?? null;
            $ordered['Eligible'] = $child['Eligible'] ?? null;
            foreach ($child as $k => $v) if (!isset($ordered[$k])) $ordered[$k] = $v;
            return $ordered;
        });

        // === Étape 4 : Clés principales avec au moins un enfant éligible ===
        $eligibleMainKeys = $childMerged
            ->where('Eligible', true)
            ->pluck('main_key')
            ->unique()
            ->values()
            ->toArray();

        // Sauvegarde pour usage global
        $eligibleFile = "$path/eligible_keys.php";
        $phpContent = "<?php\n\nreturn " . var_export($eligibleMainKeys, true) . ";\n";
        file_put_contents($eligibleFile, $phpContent);

        // === Étape 5 : Ajouter "Eligible" au fichier principal et mères ===
        $mainEnriched = $mainEnriched->map(function ($row) use ($eligibleMainKeys) {
            $row['Eligible'] = in_array($row['KEY'], $eligibleMainKeys);
            return $row;
        });

        $cgMerged = $cgMerged->map(function ($row) use ($eligibleMainKeys) {
            $row['Eligible'] = in_array($row['main_key'], $eligibleMainKeys);
            return $row;
        });

        // === Exports ===
        $this->exportCsv($path, 'main_enriched.csv', $mainEnriched);
        $this->exportCsv($path, 'mothers_enriched.csv', $cgMerged);
        $this->exportCsv($path, 'children_enriched.csv', $childMerged);

        $this->info("✅ Fusion terminée avec succès !");
        $this->info("✔ Clés éligibles enregistrées dans : $eligibleFile");
    }

    private function loadExcelMap(string $filePath): array
    {
        if (!file_exists($filePath)) {
            $this->warn("⚠️ Fichier non trouvé : $filePath");
            return [];
        }
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $map = [];
        foreach ($rows as $i => $row) {
            if ($i === 1) continue;
            $code = $row['A'] ?? null;
            $label = $row['B'] ?? null;
            if ($code) $map[$code] = $label;
        }
        return $map;
    }

    private function exportCsv(string $path, string $filename, $records)
    {
        if ($records->isEmpty()) {
            $this->warn("⚠️ Aucun enregistrement à exporter pour $filename");
            return;
        }

        $file = "$path/$filename";
        $writer = Writer::createFromPath($file, 'w+');
        $writer->insertOne(array_keys($records->first()));
        $writer->insertAll($records->toArray());
        $this->info("✔ Fichier généré : $filename");
    }
}
