<?php

namespace App\Services;

class EligibleKeysService
{
    protected array $keys = [];

    public function __construct()
    {
        $path = storage_path('app/public/odk/eligible_keys.php');
        if (file_exists($path)) {
            $this->keys = include $path;
        }
    }

    /** Retourne toutes les clés éligibles */
    public function all(): array
    {
        return $this->keys;
    }

    /** Vérifie si une clé principale est éligible */
    public function isEligible(string $key): bool
    {
        return in_array($key, $this->keys);
    }
}
