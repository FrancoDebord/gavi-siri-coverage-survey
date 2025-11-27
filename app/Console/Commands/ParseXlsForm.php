<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;

class ParseXlsForm extends Command
{
    protected $signature = 'odk:parse-xlsform {file=storage/app/public/odk/xlsform/cov25c4v1_xslform.xls}';
    protected $description = 'Parse un XLSForm (sheet survey) et génère xlsform_rules.json';

    public function handle()
    {
        $file = base_path($this->argument('file'));
        if (!file_exists($file)) {
            $this->error("Fichier introuvable: $file");
            return 1;
        }

        $spreadsheet = IOFactory::load($file);

        // find survey sheet (case-insensitive)
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (Str::lower($name) === 'survey') {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }
        if (!$sheet) {
            $this->error('Feuille "survey" introuvable dans le XLSForm.');
            return 1;
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) < 2) {
            $this->error('Aucune ligne trouvée dans survey.');
            return 1;
        }

        // header row: find column names and indexes
        $header = array_shift($rows);
        $hmap = [];
        foreach ($header as $col => $name) {
            $key = trim((string)$name);
            if ($key !== '') $hmap[strtolower($key)] = $col;
        }

        $map = [];
        foreach ($rows as $r) {
            $type = $r[$hmap['type'] ?? 'A'] ?? null;
            $name = $r[$hmap['name'] ?? 'B'] ?? null;
            if (!$name) continue;

            $label = $r[$hmap['label'] ?? 'C'] ?? null;
            $required = $r[$hmap['required'] ?? 'D'] ?? null;
            $constraint = $r[$hmap['constraint'] ?? 'E'] ?? null;
            $relevant = $r[$hmap['relevant'] ?? 'F'] ?? null;

            $map[$name] = [
                'type' => $type ? trim((string)$type) : null,
                'label' => $label ? trim((string)$label) : null,
                'required' => (trim((string)$required) !== ''),
                'constraint' => $constraint ? trim((string)$constraint) : null,
                'relevant' => $relevant ? trim((string)$relevant) : null,
            ];
        }

        $outPath = storage_path('app/public/odk/xlsform_rules.json');
        if (!is_dir(dirname($outPath))) mkdir(dirname($outPath), 0755, true);
        file_put_contents($outPath, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Règles générées : $outPath (".count($map)." champs)");
        return 0;
    }
}
