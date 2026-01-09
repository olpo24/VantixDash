<?php
// services/SiteService.php

class SiteService {
    private $file;

    public function __construct($sitesFile) {
        $this->file = $sitesFile;
    }

    private function getAll() {
        if (!file_exists($this->file)) return [];
        return json_decode(file_get_contents($this->file), true) ?: [];
    }

    private function save($sites) {
        return file_put_contents($this->file, json_encode(array_values($sites), JSON_PRETTY_PRINT));
    }

    public function addSite($name, $url) {
        // Validierung gehört hierher!
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;

        $sites = $this->getAll();
        $apiKey = bin2hex(random_bytes(16));
        
        $newSite = [
            'id' => bin2hex(random_bytes(8)),
            'name' => htmlspecialchars($name),
            'url' => rtrim($url, '/'),
            'api_key' => $apiKey,
            'status' => 'pending'
        ];

        $sites[] = $newSite;
        $this->save($sites);
        return $newSite;
    }

    public function deleteSite($id) {
        $sites = $this->getAll();
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        return $this->save($filtered);
    }
}
