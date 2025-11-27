<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use Illuminate\Http\Request;

class RecruitmentController extends Controller
{
    public function home()
    {
        // Exemple de KPI à afficher (à adapter selon ton CSV)
        $main = $this->loadCsv('odk/main_enriched.csv');
        $totalMenages = count($main);
        $villages = collect($main)->unique('village_name')->count();
        $agents = collect($main)->unique('fieldworker_name')->count();

        return view('home', compact('totalMenages', 'villages', 'agents'));
    }

    public function menages()
    {
        $data = $this->loadCsv('odk/main_enriched.csv');


        $eligibleService = app(\App\Services\EligibleKeysService::class);
        $eligibleCount = collect($data)->filter(function ($item) use ($eligibleService) {
            return $eligibleService->isEligible($item['KEY'] ?? '');
        })->count();

        $ineligibleCount = count($data) - $eligibleCount;

        return view('menages', compact('data', 'eligibleCount', 'ineligibleCount'));
    }

    public function meres()
    {
        $data = $this->loadCsv('odk/mothers_enriched.csv');
        return view('meres', compact('data'));
    }

    public function enfants()
    {
        $data = $this->loadCsv('odk/children_enriched.csv');
        return view('enfants', compact('data'));
    }

    private function loadCsv($path)
    {
        $fullPath = storage_path("app/public/$path");
        if (!file_exists($fullPath)) return [];

        $csv = Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);
        return iterator_to_array($csv->getRecords());
    }
}
