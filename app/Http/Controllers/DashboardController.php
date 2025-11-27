<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\Csv\Reader;
use App\Services\OdkDataValidator;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    // public function index()
    // {
    //     $base = storage_path('app/public/odk');
    //     $mainFile = "$base/main_enriched.csv";
    //     $mothersFile = "$base/mothers_enriched.csv";
    //     $childrenFile = "$base/children_enriched.csv";

    //     $main = $this->loadCsvRecords($mainFile);
    //     $mothers = $this->loadCsvRecords($mothersFile);
    //     $children = $this->loadCsvRecords($childrenFile);

    //     // lists for filters (unique and sorted)
    //     $zones = collect($main)->pluck('l1')->unique()->filter()->sort()->values()->toArray();
    //     $communes = collect($main)->pluck('l2')->unique()->filter()->sort()->values()->toArray();
    //     $villages = collect($main)->pluck('village_name')->unique()->filter()->sort()->values()->toArray();
    //     $agents = collect($main)->pluck('fieldworker_name')->unique()->filter()->sort()->values()->toArray();

    //     // KPI computations (same as before)...
    //     $kpi = [
    //         'menages_total' => count($main),
    //         'menages_eligible' => collect($main)->where('Eligible', true)->count(),
    //         'meres_total' => count($mothers),
    //         'meres_eligible' => collect($mothers)->where('Eligible', true)->count(),
    //         'enfants_total' => count($children),
    //         'enfants_eligible' => collect($children)->where('Eligible', true)->count(),
    //     ];
    //     $kpi['menages_pct'] = $kpi['menages_total'] ? round(100 * $kpi['menages_eligible'] / $kpi['menages_total'], 1) : 0;
    //     $kpi['meres_pct'] = $kpi['meres_total'] ? round(100 * $kpi['meres_eligible'] / $kpi['meres_total'], 1) : 0;
    //     $kpi['enfants_pct'] = $kpi['enfants_total'] ? round(100 * $kpi['enfants_eligible'] / $kpi['enfants_total'], 1) : 0;

    //     // generate tables & markers as before (omitted here for brevity)...
    //     // reuse previous logic for grouped summaries and markers
    //     // ...

    //     // pass filters to view
    //     return view('dashboard.kpis', compact(
    //         'kpi',
    //         'menages_table','meres_table','enfants_table',
    //         'menages_markers','meres_markers','enfants_markers',
    //         'zones','communes','villages','agents'
    //     ));
    // }

    public function index()
    {
        $base = storage_path('app/public/odk');

        // Charger les CSV
        $menages = collect(\League\Csv\Reader::createFromPath("$base/main_enriched.csv", 'r')->setHeaderOffset(0)->getRecords());
        $meres = collect(\League\Csv\Reader::createFromPath("$base/mothers_enriched.csv", 'r')->setHeaderOffset(0)->getRecords());
        $enfants = collect(\League\Csv\Reader::createFromPath("$base/children_enriched.csv", 'r')->setHeaderOffset(0)->getRecords());

        // Fonction d’enrichissement
        $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
        $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];

        // lists for filters (unique and sorted)
        $zones = collect($menages)->pluck('l1')->unique()->filter()->sort()->values()->toArray();
        $communes = collect($menages)->pluck('l2')->unique()->filter()->sort()->values()->toArray();
        $villages = collect($menages)->pluck('village_name')->unique()->filter()->sort()->values()->toArray();
        $agents = collect($menages)->pluck('fieldworker_name')->unique()->filter()->sort()->values()->toArray();


        $enrich = function ($c) use ($mapZone, $mapCommune) {
            return $c->map(function ($r) use ($mapZone, $mapCommune) {
                $r['ZoneSanitaire'] = $mapZone[$r['l1'] ?? null] ?? 'Inconnue';
                $r['Commune'] = $mapCommune[$r['l2'] ?? null] ?? 'Inconnue';
                return $r;
            });
        };

        $menages = $enrich($menages);
        $meres = $enrich($meres);
        $enfants = $enrich($enfants);

        // KPI calculs
        $menage_total = $menages->count();
        $menage_eligible = $menages->where('Eligible', true)->count();
        $mere_total = $meres->count();
        $mere_eligible = $meres->where('Eligible', true)->count();
        $enfant_total = $enfants->count();
        $enfant_eligible = $enfants->where('Eligible', true)->count();

        $kpiData = [
            'menage' => [
                'total' => $menage_total,
                'eligible' => $menage_eligible,
                'pourcentage' => $menage_total ? round($menage_eligible / $menage_total * 100, 1) : 0
            ],
            'mere' => [
                'total' => $mere_total,
                'eligible' => $mere_eligible,
                'pourcentage' => $mere_total ? round($mere_eligible / $mere_total * 100, 1) : 0
            ],
            'enfant' => [
                'total' => $enfant_total,
                'eligible' => $enfant_eligible,
                'pourcentage' => $enfant_total ? round($enfant_eligible / $enfant_total * 100, 1) : 0
            ]
        ];

        // Tables pour DataTables (groupées par Zone Sanitaire + Village)
        $menages_table = $menages
            ->groupBy(fn($r) => $r['ZoneSanitaire'] . '-' . $r['village_name'])
            ->map(fn($grp) => [
                'ZoneSanitaire' => $grp->first()['ZoneSanitaire'],
                'Village' => $grp->first()['village_name'],
                'Total' => $grp->count(),
                'Eligibles' => $grp->where('Eligible', true)->count(),
            ])
            ->values();

        $meres_table = $meres
            ->groupBy(fn($r) => $r['ZoneSanitaire'] . '-' . $r['village_name'])
            ->map(fn($grp) => [
                'ZoneSanitaire' => $grp->first()['ZoneSanitaire'],
                'Village' => $grp->first()['village_name'],
                'Total' => $grp->count(),
                'Eligibles' => $grp->where('Eligible', true)->count(),
            ])
            ->values();

        $enfants_table = $enfants
            ->groupBy(fn($r) => $r['ZoneSanitaire'] . '-' . $r['village_name'])
            ->map(fn($grp) => [
                'ZoneSanitaire' => $grp->first()['ZoneSanitaire'],
                'Village' => $grp->first()['village_name'],
                'Total' => $grp->count(),
                'Eligibles' => $grp->where('Eligible', true)->count(),
            ])
            ->values();

        // Marqueurs pour les cartes
        $menages_markers = $menages->map(function ($r) {
            return [
                'id' => $r['KEY'] ?? null,
                'village' => $r['village_name'] ?? null,
                'agent' => $r['fieldworker_name'] ?? null,
                'eligible' => (bool)($r['Eligible'] ?? false),
                'lat' => isset($r['current_location-Latitude']) ? (float)$r['current_location-Latitude'] : null,
                'lng' => isset($r['current_location-Longitude']) ? (float)$r['current_location-Longitude'] : null,
                'l1' => $r['ZoneSanitaire'] ?? null,
                'l2' => $r['Commune'] ?? null,
            ];
        })->values();

        $meres_markers = $meres->map(function ($r) {
            return [
                'id' => $r['KEY'] ?? null,
                'village' => $r['village_name'] ?? null,
                'agent' => $r['fieldworker_name'] ?? null,
                'eligible' => (bool)($r['Eligible'] ?? false),
                'lat' => isset($r['current_location-Latitude']) ? (float)$r['current_location-Latitude'] : null,
                'lng' => isset($r['current_location-Longitude']) ? (float)$r['current_location-Longitude'] : null,
                'l1' => $r['ZoneSanitaire'] ?? null,
                'l2' => $r['Commune'] ?? null,
            ];
        })->values();

        $enfants_markers = $enfants->map(function ($r) {
            return [
                'id' => $r['KEY'] ?? null,
                'village' => $r['village_name'] ?? null,
                'agent' => $r['fieldworker_name'] ?? null,
                'eligible' => (bool)($r['Eligible'] ?? false),
                'lat' => isset($r['current_location-Latitude']) ? (float)$r['current_location-Latitude'] : null,
                'lng' => isset($r['current_location-Longitude']) ? (float)$r['current_location-Longitude'] : null,
                'l1' => $r['ZoneSanitaire'] ?? null,
                'l2' => $r['Commune'] ?? null,
            ];
        })->values();



        return view('dashboard.kpis', compact(
            'kpiData',
            'menages_table',
            'meres_table',
            'enfants_table',
            "mapZone",
            "mapCommune",
            'zones',
            'communes',
            'villages',
            'agents',
            'menages_markers',
            'meres_markers',
            'enfants_markers'
        ));
    }

    public function errors(OdkDataValidator $validator)
    {
        $base = storage_path('app/public/odk');
        $files = [
            ['path' => "$base/main_enriched.csv", 'label' => 'main_enriched.csv'],
            ['path' => "$base/mothers_enriched.csv", 'label' => 'mothers_enriched.csv'],
            ['path' => "$base/children_enriched.csv", 'label' => 'children_enriched.csv'],
        ];

        $errors = $validator->validateAll($files);

        // Pass also a count summary
        $summary = [
            'total_errors' => count($errors),
            'by_file' => []
        ];
        foreach ($errors as $e) {
            $f = $e['file'] ?? 'unknown';
            $summary['by_file'][$f] = ($summary['by_file'][$f] ?? 0) + 1;
        }

        return view('dashboard.errors', compact('errors', 'summary'));
    }

    private function loadCsvRecords($file)
    {
        if (!file_exists($file)) return [];
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        return iterator_to_array($csv->getRecords());
    }


//    public function detailsMenages()
// {
//     $base = storage_path('app/public/odk');

//     $menages = collect(\League\Csv\Reader::createFromPath("$base/main_enriched.csv", 'r')
//         ->setHeaderOffset(0)->getRecords());

//     $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
//     $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];

//     // Enrichir les infos
//     $menages = $menages->map(function ($r) use ($mapZone, $mapCommune) {
//         $r['ZoneSanitaire'] = $mapZone[$r['l1'] ?? null] ?? 'Inconnue';
//         $r['Commune'] = $mapCommune[$r['l2'] ?? null] ?? 'Inconnue';
//         $r['village_name'] = $r['village_name'] ?? 'Inconnu';
//         return $r;
//     });

//     $zones = $menages->pluck('ZoneSanitaire')->unique()->sort()->values();
//     $communes = $menages->pluck('Commune')->unique()->sort()->values();
//     $villages = $menages->pluck('village_name')->unique()->sort()->values();

//     // Tableau résumé
//     $summary = $menages
//         ->groupBy(fn($r) => $r['ZoneSanitaire'].'-'.$r['Commune'].'-'.$r['village_name'])
//         ->map(function ($grp) {
//             $total = $grp->count();
//             $eligibles = $grp->where('Eligible', true)->count();
//             return [
//                 'ZoneSanitaire' => $grp->first()['ZoneSanitaire'],
//                 'Commune' => $grp->first()['Commune'],
//                 'Village' => $grp->first()['village_name'],
//                 'TotalMenages' => $total,
//                 'Eligibles' => $eligibles,
//                 'Taux' => $total ? round($eligibles / $total * 100, 1) : 0,
//             ];
//         })
//         ->sortBy(['ZoneSanitaire', 'Commune', 'Village'])
//         ->values();

//     // Tableau individuel
//     $individuals = $menages->map(function ($r) {
//         return [
//             'ZoneSanitaire' => $r['ZoneSanitaire'],
//             'Commune' => $r['Commune'],
//             'Village' => $r['village_name'],
//             'Household' => $r['household'] ?? '—',
//             'Visits' => $r['visit_num'] ?? '—',
//             'Eligible' => $r['Eligible'] ? 'Oui' : 'Non',
//             'lat' => $r['current_location-Latitude'] ?? null,
//             'lng' => $r['current_location-Longitude'] ?? null,
//         ];
//     })->sortBy(['ZoneSanitaire', 'Commune', 'Village'])->values();

//     $markers = $individuals
//         ->filter(fn($r) => $r['lat'] && $r['lng'])
//         ->map(fn($r) => [
//             'lat' => (float)$r['lat'],
//             'lng' => (float)$r['lng'],
//             'village' => $r['Village'],
//             'eligible' => $r['Eligible'] === 'Oui',
//             'id' => $r['Household'],
//         ])->values();

//     return view('details.menages', compact('zones', 'communes', 'villages', 'summary', 'individuals', 'markers'));
// }
//     public function detailsEnfants()
// {
//     $base = storage_path('app/public/odk');

//     $enfants = collect(\League\Csv\Reader::createFromPath("$base/children_enriched.csv", 'r')
//         ->setHeaderOffset(0)->getRecords());

//     $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
//     $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];

//     $enfants = $enfants->map(function ($r) use ($mapZone, $mapCommune) {
//         $r['ZoneSanitaire'] = $mapZone[$r['l1'] ?? null] ?? 'Inconnue';
//         $r['Commune'] = $mapCommune[$r['l2'] ?? null] ?? 'Inconnue';
//         $r['village_name'] = $r['village_name'] ?? 'Inconnu';
//         return $r;
//     });

//     $zones = $enfants->pluck('ZoneSanitaire')->unique()->sort()->values();
//     $communes = $enfants->pluck('Commune')->unique()->sort()->values();
//     $villages = $enfants->pluck('village_name')->unique()->sort()->values();

//     // Tableau résumé
//     $summary = $enfants
//         ->groupBy(fn($r) => $r['ZoneSanitaire'].'-'.$r['Commune'].'-'.$r['village_name'])
//         ->map(function ($grp) {
//             $total = $grp->count();
//             $eligibles = $grp->where('Eligible', true)->count();
//             $sleep = $grp->where('child_sleep_last_night', 1)->count();
//             $ageGroups = [
//                 '6-10' => $grp->whereBetween('age_months_est', [6, 10])->count(),
//                 '11-29' => $grp->whereBetween('age_months_est', [11, 29])->count(),
//                 '30+' => $grp->where('age_months_est', '>=', 30)->count(),
//             ];
//             return [
//                 'ZoneSanitaire' => $grp->first()['ZoneSanitaire'],
//                 'Commune' => $grp->first()['Commune'],
//                 'Village' => $grp->first()['village_name'],
//                 'Total' => $total,
//                 'Passé Nuit' => $sleep,
//                 'Eligibles' => $eligibles,
//                 '6-10m' => $ageGroups['6-10'],
//                 '11-29m' => $ageGroups['11-29'],
//                 '30m+' => $ageGroups['30+'],
//             ];
//         })
//         ->sortBy(['ZoneSanitaire', 'Commune', 'Village'])
//         ->values();

//     $individuals = $enfants->map(function ($r) {
//         return [
//             'ZoneSanitaire' => $r['ZoneSanitaire'],
//             'Commune' => $r['Commune'],
//             'Village' => $r['village_name'],
//             'Nom' => $r['child_name'] ?? '—',
//             'Age (mois)' => $r['age_months_est'] ?? '—',
//             'Eligible' => $r['Eligible'] ? 'Oui' : 'Non',
//             'lat' => $r['current_location-Latitude'] ?? null,
//             'lng' => $r['current_location-Longitude'] ?? null,
//         ];
//     })->sortBy(['ZoneSanitaire', 'Commune', 'Village'])->values();

//     $markers = $individuals
//         ->filter(fn($r) => $r['lat'] && $r['lng'])
//         ->map(fn($r) => [
//             'lat' => (float)$r['lat'],
//             'lng' => (float)$r['lng'],
//             'village' => $r['Village'],
//             'eligible' => $r['Eligible'] === 'Oui',
//             'name' => $r['Nom'],
//         ])->values();

//     return view('details.enfants', compact('zones', 'communes', 'villages', 'summary', 'individuals', 'markers'));
// }


//  public function detailsMeres()
// {
//     $base = storage_path('app/public/odk');

//     $meres = collect(\League\Csv\Reader::createFromPath("$base/mothers_enriched.csv", 'r')->setHeaderOffset(0)->getRecords());

//     $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
//     $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];

//     $meres = $meres->map(function ($r) use ($mapZone, $mapCommune) {
//         $r['ZoneSanitaire'] = $mapZone[$r['l1'] ?? null] ?? 'Inconnue';
//         $r['Commune'] = $mapCommune[$r['l2'] ?? null] ?? 'Inconnue';
//         $r['village_name'] = $r['village_name'] ?? 'Inconnu';
//         return $r;
//     });

//     $zones = $meres->pluck('ZoneSanitaire')->unique()->filter()->sort()->values();
//     $communes = $meres->pluck('Commune')->unique()->filter()->sort()->values();
//     $villages = $meres->pluck('village_name')->unique()->filter()->sort()->values();

//     // Tableau résumé trié
//     $summary = $meres
//         ->groupBy(fn($r) => $r['ZoneSanitaire'].'-'.$r['Commune'].'-'.$r['village_name'])
//         ->map(function ($grp) {
//             $total = $grp->count();
//             $eligibles = $grp->where('Eligible', true)->count();
//             return [
//                 'ZoneSanitaire' => $grp->first()['ZoneSanitaire'],
//                 'Commune' => $grp->first()['Commune'],
//                 'Village' => $grp->first()['village_name'],
//                 'TotalMeres' => $total,
//                 'Eligibles' => $eligibles,
//                 'Taux' => $total ? round($eligibles / $total * 100, 1) : 0,
//             ];
//         })
//         ->sortBy(['ZoneSanitaire', 'Commune', 'Village'])
//         ->values();

//     // Tableau individuel
//     $individuals = $meres
//         ->map(function ($r) {
//             return [
//                 'ZoneSanitaire' => $r['ZoneSanitaire'],
//                 'Commune' => $r['Commune'],
//                 'Village' => $r['village_name'],
//                 'Nom' => $r['mother_name'] ?? '—',
//                 'Age' => $r['mother_age'] ?? '—',
//                 'Eligible' => !empty($r['Eligible']) && $r['Eligible'] == true ? 'Oui' : 'Non',
//                 'lat' => $r['current_location-Latitude'] ?? null,
//                 'lng' => $r['current_location-Longitude'] ?? null,
//             ];
//         })
//         ->sortBy(['ZoneSanitaire', 'Commune', 'Village'])
//         ->values();

//     // Markers map
//     $markers = $individuals
//         ->filter(fn($r) => $r['lat'] && $r['lng'])
//         ->map(fn($r) => [
//             'lat' => (float)$r['lat'],
//             'lng' => (float)$r['lng'],
//             'village' => $r['Village'],
//             'eligible' => $r['Eligible'] === 'Oui',
//             'name' => $r['Nom'],
//         ])
//         ->values();

//     return view('details.meres', compact('zones', 'communes', 'villages', 'summary', 'individuals', 'markers'));
// }




protected function loadArrondissementsMap(): array
{
    // Cherche le fichier de correspondances l3 -> nom arrondissement.
    // Place ton fichier CSV dans storage/app/public/odk/arrondissements_l3.csv
    $candidates = [
        storage_path('app/public/odk/arrondissements_l3.csv'),
        // storage_path('app/public/odk/a55bafce-393f-4b2c-b897-7e068896a14a.csv'),
        // storage_path('app/public/odk/l3_arrondissements.csv')
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            try {
                $csv = Reader::createFromPath($path, 'r');
                $csv->setHeaderOffset(0);
                $records = iterator_to_array($csv->getRecords());
                $map = [];
                // On essaie de détecter les colonnes: code / label ou name
                foreach ($records as $rec) {
                    // try common column names:
                    $keys = array_keys($rec);
                    $codeKey = null;
                    $labelKey = null;
                    foreach ($keys as $k) {
                        $lk = Str::lower($k);
                        if (in_array($lk, ['code','name','code_l3','l3','value','id'])) $codeKey = $k;
                        if (in_array($lk, ['label','libelle','nom','name','label_fr'])) $labelKey = $k;
                    }
                    // fallback: first two columns
                    if (!$codeKey) $codeKey = $keys[0] ?? null;
                    if (!$labelKey) $labelKey = $keys[1] ?? ($keys[0] ?? null);

                    $code = isset($rec[$codeKey]) ? trim((string)$rec[$codeKey]) : null;
                    $label = isset($rec[$labelKey]) ? trim((string)$rec[$labelKey]) : null;
                    if ($code !== null) $map[$code] = $label ?? $code;
                }
                return $map;
            } catch (\Throwable $e) {
                // ignore file parse error and try next
            }
        }
    }
    return []; // vide si aucun mapping trouvé
}

/**
 * Détails Ménages (agrégé + individuels + cartes)
 */
public function detailsMenages()
{
    $base = storage_path('app/public/odk');

    // fichiers CSV enrichis attendus
    $menagesFile = "$base/main_enriched.csv";
    $meresFile = "$base/mothers_enriched.csv";
    $enfantsFile = "$base/children_enriched.csv";

    if (!file_exists($menagesFile)) {
        abort(500, "Fichier main_enriched.csv introuvable dans storage/app/public/odk");
    }

    $menages = collect(Reader::createFromPath($menagesFile, 'r')->setHeaderOffset(0)->getRecords());
    $meres = file_exists($meresFile) ? collect(Reader::createFromPath($meresFile, 'r')->setHeaderOffset(0)->getRecords()) : collect();
    $enfants = file_exists($enfantsFile) ? collect(Reader::createFromPath($enfantsFile, 'r')->setHeaderOffset(0)->getRecords()) : collect();

    // maps l1 / l2 (définis par toi)
    $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
    $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];

    // arrondissements = mapping l3 -> label (chargé depuis CSV)
    $arrMap = $this->loadArrondissementsMap();

    // Enrichir les lignes (ZoneSanitaire, Commune, Arrondissement, village_name, agent)
    $enrich = function($r) use ($mapZone, $mapCommune, $arrMap) {
        $r['ZoneSanitaire'] = $mapZone[$r['l1'] ?? null] ?? ($r['l1'] ?? 'Inconnue');
        $r['Commune'] = $mapCommune[$r['l2'] ?? null] ?? ($r['l2'] ?? 'Inconnue');
        $l3 = (string)($r['l3'] ?? '');
        $r['Arrondissement'] = $arrMap[$l3] ?? ($r['l3'] ?? 'Inconnu');
        $r['village_name'] = $r['village_name'] ?? 'Inconnu';
        $r['fieldworker_name'] = $r['fieldworker_name'] ?? ($r['fieldworker'] ?? null);
        // Eligible normalization to boolean
        $r['Eligible'] = isset($r['Eligible']) ? filter_var($r['Eligible'], FILTER_VALIDATE_BOOLEAN) : (isset($r['eligible']) ? filter_var($r['eligible'], FILTER_VALIDATE_BOOLEAN) : false);
        return $r;
    };

    $menages = $menages->map($enrich);
    $meres = $meres->map($enrich);
    $enfants = $enfants->map($enrich);

    // Filters lists
    $zones = $menages->pluck('ZoneSanitaire')->unique()->sort()->values();
    $communes = $menages->pluck('Commune')->unique()->sort()->values();
    $arrondissements = $menages->pluck('Arrondissement')->unique()->sort()->values();
    $villages = $menages->pluck('village_name')->unique()->sort()->values();
    $agents = $menages->pluck('fieldworker_name')->unique()->filter()->sort()->values();

    // Résumé agrégé par Zone/Commune/Arrondissement/Village
    $summary = $menages
        ->groupBy(fn($r) => $r['ZoneSanitaire'].'||'.$r['Commune'].'||'.$r['Arrondissement'].'||'.$r['village_name'])
        ->map(function($grp) use ($meres, $enfants) {
            $first = $grp->first();
            $village = $first['village_name'];
            $menages_visites = $grp->count();
            $menages_eligibles = $grp->where('Eligible', true)->count();

            // mères within same household/village: we'll count by village
            $meres_village = $meres->where('village_name', $village);
            $meres_visitees = $meres_village->count();
            $meres_eligibles = $meres_village->where('Eligible', true)->count();

            $enfants_village = $enfants->where('village_name', $village);
            $enfants_visites = $enfants_village->count();
            $enfants_eligibles = $enfants_village->where('Eligible', true)->count();

            return [
                'ZoneSanitaire' => $first['ZoneSanitaire'],
                'Commune' => $first['Commune'],
                'Arrondissement' => $first['Arrondissement'],
                'Village' => $village,
                'MenagesVisites' => $menages_visites,
                'MenagesEligibles' => $menages_eligibles,
                'PctMenages' => $menages_visites ? round(100 * $menages_eligibles / $menages_visites, 1) : 0,
                'MeresVisitees' => $meres_visitees,
                'MeresEligibles' => $meres_eligibles,
                'PctMeres' => $meres_visitees ? round(100 * $meres_eligibles / $meres_visitees, 1) : 0,
                'EnfantsVisites' => $enfants_visites,
                'EnfantsEligibles' => $enfants_eligibles,
                'PctEnfants' => $enfants_visits = $enfants_visites ? round(100 * $enfants_eligibles / $enfants_visites, 1) : 0,
            ];
        })
        ->sortBy(fn($v) => $v['ZoneSanitaire'].'-'.$v['Commune'].'-'.$v['Arrondissement'].'-'.$v['Village'])
        ->values();

    // Tableau individuel : household rows with visit_num counted
    $individuals = $menages->map(function($r) {
        // visit_num may be numeric or string list; try count if list separated, else cast to int
        $visits = 0;
        if (isset($r['visit_num'])) {
            $vn = $r['visit_num'];
            if (is_numeric($vn)) {
                $visits = (int)$vn;
            } elseif (is_string($vn) && Str::contains($vn, [' ', ',', ';','/'])) {
                $parts = preg_split('/[\\s,;\\/]+/', trim($vn));
                $visits = count(array_filter($parts, fn($p)=>$p!=='')); 
            } else {
                $visits = (int)$vn;
            }
        }

        return [
            'ZoneSanitaire' => $r['ZoneSanitaire'],
            'Commune' => $r['Commune'],
            'Arrondissement' => $r['Arrondissement'],
            'Village' => $r['village_name'],
            'Household' => $r['household'] ?? $r['HOUSEHOLD'] ?? null,
            'Eligible' => $r['Eligible'] ? 'Oui' : 'Non',
            'Visits' => $visits,
            'lat' => $r['current_location-Latitude'] ?? null,
            'lng' => $r['current_location-Longitude'] ?? null,
            'Agent' => $r['fieldworker_name'] ?? null,
        ];
    })->values();

    // Markers
    $markers = $individuals
        ->filter(fn($r) => $r['lat'] && $r['lng'])
        ->map(fn($r) => [
            'lat' => (float)$r['lat'],
            'lng' => (float)$r['lng'],
            'ZoneSanitaire' => $r['ZoneSanitaire'],
            'Commune' => $r['Commune'],
            'Arrondissement' => $r['Arrondissement'],
            'village' => $r['Village'],
            'eligible' => $r['Eligible'] === 'Oui',
            'id' => $r['Household'],
            'agent' => $r['Agent'],
        ])->values();

    return view('details.menages', compact(
        'zones','communes','arrondissements','villages','agents',
        'summary','individuals','markers'
    ));
}

  public function detailsMeres(Request $request)
    {
        $base = storage_path('app/public/odk');
        $file = $base . '/mothers_enriched.csv';

        if (!file_exists($file)) {
            abort(500, "Fichier mothers_enriched.csv introuvable dans storage/app/public/odk");
        }

        $csv = Reader::createFromPath($file, 'r')->setHeaderOffset(0);
        $rows = collect($csv->getRecords());

        // maps l1 / l2
        $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
        $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];

        $arrMap = $this->loadArrondissementsMap();

        // Normalisation / enrichissement
        $data = $rows->map(function ($r) use ($mapZone, $mapCommune, $arrMap) {
            $r = array_map(function($v){ return $v === '' ? null : $v; }, $r);

            $l1 = $r['l1'] ?? null;
            $l2 = $r['l2'] ?? null;
            $l3 = isset($r['l3']) ? (string)$r['l3'] : null;

            $zone = $mapZone[$l1] ?? ($l1 ?? 'Inconnue');
            $commune = $mapCommune[$l2] ?? ($l2 ?? 'Inconnue');
            $arr = $arrMap[$l3] ?? ($l3 ?? 'Inconnu');

            $village = $r['village_name'] ?? ($r['village'] ?? 'Inconnu');
            $agent = $r['fieldworker_name'] ?? ($r['fieldworker'] ?? null);

            $eligible = false;
            if (isset($r['Eligible'])) $eligible = filter_var($r['Eligible'], FILTER_VALIDATE_BOOLEAN);
            elseif (isset($r['eligible'])) $eligible = filter_var($r['eligible'], FILTER_VALIDATE_BOOLEAN);

            return [
                'raw' => $r,
                'ZoneSanitaire' => $zone,
                'Commune' => $commune,
                'Arrondissement' => $arr,
                'village_name' => $village,
                'fieldworker_name' => $agent,
                'mother_name' => $r['mother_name'] ?? ($r['name'] ?? null),
                'mother_age' => $r['mother_age'] ?? null,
                'Eligible' => $eligible,
                'lat' => $r['current_location-Latitude'] ?? null,
                'lng' => $r['current_location-Longitude'] ?? null,
            ];
        });

        // Lists for filters
        $zones = $data->pluck('ZoneSanitaire')->unique()->filter()->sort()->values();
        $communes = $data->pluck('Commune')->unique()->filter()->sort()->values();
        $arrondissements = $data->pluck('Arrondissement')->unique()->filter()->sort()->values();
        $villages = $data->pluck('village_name')->unique()->filter()->sort()->values();
        $agents = $data->pluck('fieldworker_name')->unique()->filter()->sort()->values();

        // SUMMARY grouped by Zone/Commune/Arrondissement/Village
        $summary = $data
            ->groupBy(fn($r) => $r['ZoneSanitaire'].'||'.$r['Commune'].'||'.$r['Arrondissement'].'||'.$r['village_name'])
            ->map(function ($grp) {
                $first = $grp->first();
                $total = $grp->count();
                $eligibles = $grp->where('Eligible', true)->count();
                return [
                    'ZoneSanitaire' => $first['ZoneSanitaire'],
                    'Commune' => $first['Commune'],
                    'Arrondissement' => $first['Arrondissement'],
                    'Village' => $first['village_name'],
                    'TotalMeres' => $total,
                    'Eligibles' => $eligibles,
                    'Taux' => $total ? round(100 * $eligibles / $total, 1) : 0,
                ];
            })
            ->sortBy(fn($v) => $v['ZoneSanitaire'].'-'.$v['Commune'].'-'.$v['Arrondissement'].'-'.$v['Village'])
            ->values();

        // INDIVIDUALS
        $individuals = $data->map(function ($r) {
            return [
                'ZoneSanitaire' => $r['ZoneSanitaire'],
                'Commune' => $r['Commune'],
                'Arrondissement' => $r['Arrondissement'],
                'Village' => $r['village_name'],
                'Nom' => $r['mother_name'] ?? '—',
                'Age' => $r['mother_age'] ?? '—',
                'Eligible' => $r['Eligible'] ? 'Oui' : 'Non',
                'lat' => $r['lat'],
                'lng' => $r['lng'],
                'Agent' => $r['fieldworker_name'] ?? null,
            ];
        })->values();

        // MARKERS for the map (only those with coordinates)
        $markers = $individuals
            ->filter(fn($r) => $r['lat'] && $r['lng'])
            ->map(fn($r) => [
                'lat' => (float)$r['lat'],
                'lng' => (float)$r['lng'],
                'ZoneSanitaire' => $r['ZoneSanitaire'],
                'Commune' => $r['Commune'],
                'Arrondissement' => $r['Arrondissement'],
                'village' => $r['Village'],
                'eligible' => $r['Eligible'] === 'Oui',
                'name' => $r['Nom'],
                'agent' => $r['Agent'],
            ])->values();

        return view('details.meres', compact(
            'zones','communes','arrondissements','villages','agents',
            'summary','individuals','markers'
        ));
    }

/**
 * Détails Enfants (agrégé par zones + groupes d'âge, individuels, carte)
 */
public function detailsEnfants()
{
    $base = storage_path('app/public/odk');
    $file = "$base/children_enriched.csv";
    if (!file_exists($file)) abort(500, "Fichier children_enriched.csv introuvable dans storage/app/public/odk");

    $enfants = collect(Reader::createFromPath($file, 'r')->setHeaderOffset(0)->getRecords());

    $mapZone = [1 => 'TCHAOUROU', 2 => 'DAGLA'];
    $mapCommune = [1 => 'Tchaourou', 2 => 'Dassa', 3 => 'Glazoué'];
    $arrMap = $this->loadArrondissementsMap();

    // Enrich
    $enfants = $enfants->map(function($r) use ($mapZone, $mapCommune, $arrMap) {
        $r['ZoneSanitaire'] = $mapZone[$r['l1'] ?? null] ?? ($r['l1'] ?? 'Inconnue');
        $r['Commune'] = $mapCommune[$r['l2'] ?? null] ?? ($r['l2'] ?? 'Inconnue');
        $l3 = (string)($r['l3'] ?? '');
        $r['Arrondissement'] = $arrMap[$l3] ?? ($r['l3'] ?? 'Inconnu');
        $r['village_name'] = $r['village_name'] ?? 'Inconnu';
        $r['fieldworker_name'] = $r['fieldworker_name'] ?? ($r['fieldworker'] ?? null);
        $r['Eligible'] = isset($r['Eligible']) ? filter_var($r['Eligible'], FILTER_VALIDATE_BOOLEAN) : (isset($r['eligible']) ? filter_var($r['eligible'], FILTER_VALIDATE_BOOLEAN) : false);
        // ensure numeric age if possible
        if (isset($r['age_months_est']) && is_numeric($r['age_months_est'])) {
            $r['age_months_est'] = (int)$r['age_months_est'];
        } else {
            $r['age_months_est'] = null;
        }
        return $r;
    });

    $zones = $enfants->pluck('ZoneSanitaire')->unique()->sort()->values();
    $communes = $enfants->pluck('Commune')->unique()->sort()->values();
    $arrondissements = $enfants->pluck('Arrondissement')->unique()->sort()->values();
    $villages = $enfants->pluck('village_name')->unique()->sort()->values();
    $agents = $enfants->pluck('fieldworker_name')->unique()->filter()->sort()->values();

    // Age groups definition: 6-10, 11-29, 11-30, >30 (note: 11-29 and 11-30 both)
    $summary = $enfants
        ->groupBy(fn($r) => $r['ZoneSanitaire'].'||'.$r['Commune'].'||'.$r['Arrondissement'].'||'.$r['village_name'])
        ->map(function($grp) {
            $first = $grp->first();
            $total = $grp->count();
            $nuit = $grp->where('child_sleep_last_night', 1)->count();
            $eligibles = $grp->where('Eligible', true)->count();

            $g610 = $grp->filter(fn($r) => isset($r['age_months_est']) && $r['age_months_est'] >= 6 && $r['age_months_est'] <= 10)->count();
            $g1129 = $grp->filter(fn($r) => isset($r['age_months_est']) && $r['age_months_est'] >= 11 && $r['age_months_est'] <= 29)->count();
            $g1130 = $grp->filter(fn($r) => isset($r['age_months_est']) && $r['age_months_est'] >= 11 && $r['age_months_est'] <= 30)->count();
            $g30plus = $grp->filter(fn($r) => isset($r['age_months_est']) && $r['age_months_est'] > 30)->count();

            return [
                'ZoneSanitaire' => $first['ZoneSanitaire'],
                'Commune' => $first['Commune'],
                'Arrondissement' => $first['Arrondissement'],
                'Village' => $first['village_name'],
                'TotalEnfants' => $total,
                'NuitDerniere' => $nuit,
                'Eligibles' => $eligibles,
                'G_6_10' => $g610,
                'G_11_29' => $g1129,
                'G_11_30' => $g1130,
                'G_30_PLUS' => $g30plus,
            ];
        })
        ->sortBy(fn($v) => $v['ZoneSanitaire'].'-'.$v['Commune'].'-'.$v['Arrondissement'].'-'.$v['Village'])
        ->values();

    // individuels
    $individuals = $enfants->map(function($r) {
        return [
            'ZoneSanitaire' => $r['ZoneSanitaire'],
            'Commune' => $r['Commune'],
            'Arrondissement' => $r['Arrondissement'],
            'Village' => $r['village_name'],
            'ChildId' => $r['KEY'] ?? $r['child_id'] ?? null,
            'AgeMonths' => $r['age_months_est'] ?? null,
            'SleepLastNight' => (isset($r['child_sleep_last_night']) && ($r['child_sleep_last_night'] == 1 || $r['child_sleep_last_night'] === '1')) ? 'Oui' : 'Non',
            'Eligible' => $r['Eligible'] ? 'Oui' : 'Non',
            'lat' => $r['current_location-Latitude'] ?? null,
            'lng' => $r['current_location-Longitude'] ?? null,
            'Agent' => $r['fieldworker_name'] ?? null,
        ];
    })->values();

    $markers = $individuals
        ->filter(fn($r) => $r['lat'] && $r['lng'])
        ->map(fn($r) => [
            'lat' => (float)$r['lat'],
            'lng' => (float)$r['lng'],
            'ZoneSanitaire' => $r['ZoneSanitaire'],
            'Commune' => $r['Commune'],
            'Arrondissement' => $r['Arrondissement'],
            'village' => $r['Village'],
            'eligible' => $r['Eligible'] === 'Oui',
            'id' => $r['ChildId'],
            'agent' => $r['Agent'],
        ])->values();

    return view('details.enfants', compact(
        'zones','communes','arrondissements','villages','agents',
        'summary','individuals','markers'
    ));
}
}
