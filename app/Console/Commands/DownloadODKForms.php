<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class DownloadODKForms extends Command
{
    protected $signature = 'odk:download';
    protected $description = 'Télécharge automatiquement les fichiers des rosters depuis ODK Central et les stocke dans storage/public';

    public function handle()
    {
        // === Paramètres de connexion ODK ===
        $baseUrl = 'https://central.lshtm.ac.uk/v1/projects/195';
        $formId = 'cov25c4v1';
        $username = env('ODK_USERNAME', "corine.ngufor@lshtm.ac.uk"); // À définir dans .env
        $password = env('ODK_PASSWORD', "Lshtm@@crec21"); // À définir dans .env

        // === Liste des rosters ===
        $rosters = [
            'cov25c4v1',
            // 'cg',
            // 'SES',
            // 'smc_cov_hh_cg_child_2023',
            // 'smc_cov_roster_2023',
        ];

        
        foreach ($rosters as $roster) {
            try {
                $this->info("Téléchargement de $roster...");
                $url = "$baseUrl/forms/$formId/submissions.csv.zip";

                // === URL du CSV du roster ===
                // $url = "$baseUrl/forms/$formId/submissions.csv?group=$roster";

                // === Requête authentifiée sans vérification SSL ===
                $response = Http::withOptions(['verify' => false])
                    ->withBasicAuth($username, $password)
                    ->timeout(120)
                     ->sink(storage_path('app/data.zip'))
                    ->get($url);

                if ($response->successful()) {
                    // === Nom du fichier local ===
                    $filename = $roster . '_' . now()->format('Ymd_His') . '.csv';

                    // === Stockage dans storage/app/public/odk ===
                    // Storage::disk('public')->put('odk/' . $filename, $response->body());

                    $this->info("✔ Fichier sauvegardé : storage/app/public/odk/$filename");
                } else {
                    $this->error("Échec du téléchargement de $roster : " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error("Erreur pour $roster : " . $e->getMessage());
            }
        }
        // foreach ($rosters as $roster) {
        //     try {
        //         $this->info("Téléchargement de $roster...");


        //         // === URL du CSV du roster via endpoint .svc ===
        //         $url = "$baseUrl/forms/$formId.svc/$roster.csv";


        //         // === Requête authentifiée sans vérification SSL ===
        //         $response = Http::withOptions(['verify' => false])
        //             ->withBasicAuth($username, $password)
        //             ->get($url);


        //         if ($response->successful()) {
        //             // === Nom du fichier local ===
        //             // $filename = $roster . '_' . now()->format('Ymd_His') . '.csv';
        //             $filename = "cov25c4v1_$roster.csv";


        //             // === Stockage dans storage/app/public/odk ===
        //             Storage::disk('public')->put('odk/' . $filename, $response->body());


        //             $this->info("✔ Fichier sauvegardé : storage/app/public/odk/$filename");
        //         } else {
        //             $this->error("Échec du téléchargement de $roster : " . $response->status());
        //         }
        //     } catch (\Exception $e) {
        //         $this->error("Erreur pour $roster : " . $e->getMessage());
        //     }
        // }


        $this->info('✅ Téléchargement terminé.');
    }
}
