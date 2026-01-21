<?php
declare(strict_types=1);

namespace VantixDash;

use VantixDash\Config\SettingsService;
use Exception;

class UpdateService {
    private SettingsService $settings;
    private Logger $logger;
    
    // ⚠️ WICHTIG: Hier deinen GitHub Username/Repo eintragen!
    private const GITHUB_REPO = 'your-username/vantixdash';
    private const API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases';
    private const UPDATE_DIR = __DIR__ . '/../data/';
    
    public function __construct(SettingsService $settings, Logger $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function checkForUpdates(string $channel = 'stable'): array {
        $currentVersion = $this->settings->getVersion();
        
        try {
            $releases = $this->fetchReleases($channel);
            
            // Leeres Array abfangen BEVOR wir auf [0] zugreifen
            if (empty($releases) || !is_array($releases)) {
                $this->logger->info("Keine Releases auf GitHub gefunden für Channel: $channel");
                return [
                    'update_available' => false,
                    'current' => $currentVersion,
                    'channel' => $channel,
                    'message' => 'Keine Releases auf GitHub gefunden. Bitte erstelle zuerst einen Release.'
                ];
            }
            
            $latestRelease = $releases[0];
            
            // Null-Checks für tag_name
            if (!isset($latestRelease['tag_name']) || empty($latestRelease['tag_name'])) {
                $this->logger->error('Release gefunden, aber tag_name fehlt');
                return [
                    'update_available' => false,
                    'current' => $currentVersion,
                    'message' => 'Release-Daten unvollständig'
                ];
            }
            
            $latestVersion = ltrim($latestRelease['tag_name'], 'v');
            
            // Dev-Channel: Immer als Update anzeigen
            if ($channel === 'dev') {
                $downloadUrl = $this->getAssetUrl($latestRelease);
                
                if (!$downloadUrl) {
                    return [
                        'update_available' => false,
                        'current' => $currentVersion,
                        'message' => 'Development Build gefunden, aber ZIP noch nicht verfügbar. GitHub Action läuft noch?'
                    ];
                }
                
                return [
                    'update_available' => true,
                    'current' => $currentVersion,
                    'latest' => $latestVersion,
                    'tag' => $latestRelease['tag_name'],
                    'download_url' => $downloadUrl,
                    'is_dev' => true,
                    'is_beta' => false,
                    'changelog' => $latestRelease['body'] ?? '',
                    'published_at' => $latestRelease['published_at'] ?? '',
                    'message' => 'Development Build verfügbar'
                ];
            }
            
            // Version vergleichen
            if (version_compare($latestVersion, $currentVersion, '>')) {
                $downloadUrl = $this->getAssetUrl($latestRelease);
                
                if (!$downloadUrl) {
                    return [
                        'update_available' => false,
                        'current' => $currentVersion,
                        'message' => 'Neuere Version gefunden, aber ZIP noch nicht verfügbar. GitHub Action läuft noch?'
                    ];
                }
                
                return [
                    'update_available' => true,
                    'current' => $currentVersion,
                    'latest' => $latestVersion,
                    'tag' => $latestRelease['tag_name'],
                    'download_url' => $downloadUrl,
                    'is_beta' => $latestRelease['prerelease'] ?? false,
                    'is_dev' => false,
                    'changelog' => $latestRelease['body'] ?? '',
                    'published_at' => $latestRelease['published_at'] ?? ''
                ];
            }
            
            return [
                'update_available' => false,
                'current' => $currentVersion,
                'message' => 'Du nutzt bereits die neueste Version.'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Update-Check fehlgeschlagen: ' . $e->getMessage());
            return [
                'error' => true,
                'current' => $currentVersion,
                'message' => 'GitHub API Fehler: ' . $e->getMessage()
            ];
        }
    }

    private function fetchReleases(string $channel): array {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: VantixDash-Updater\r\n" .
                           "Accept: application/vnd.github+json\r\n",
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents(self::API_URL, false, $context);
        
        if ($response === false) {
            $this->logger->error('GitHub API nicht erreichbar: ' . self::API_URL);
            throw new Exception('GitHub API nicht erreichbar');
        }
        
        $releases = json_decode($response, true);
        
        if (!is_array($releases)) {
            $this->logger->error('GitHub API ungültige Antwort: ' . substr($response, 0, 200));
            throw new Exception('Ungültige API-Antwort von GitHub');
        }
        
        // Leeres Array ist OK - kein Fehler werfen, einfach zurückgeben
        if (empty($releases)) {
            $this->logger->info('GitHub Repo hat noch keine Releases');
            return [];
        }
        
        // Nach Channel filtern
        switch ($channel) {
            case 'stable':
                $releases = array_filter($releases, function($r) {
                    return !($r['prerelease'] ?? false) 
                        && !str_contains($r['tag_name'] ?? '', 'dev');
                });
                break;
                
            case 'beta':
                $releases = array_filter($releases, function($r) {
                    return !str_contains($r['tag_name'] ?? '', 'dev');
                });
                break;
                
            case 'dev':
                $releases = array_filter($releases, function($r) {
                    return ($r['tag_name'] ?? '') === 'dev-latest';
                });
                break;
        }
        
        // Re-index array nach filter
        return array_values($releases);
    }

    private function getAssetUrl(array $release): ?string {
        if (empty($release['assets'])) {
            return null;
        }
        
        foreach ($release['assets'] as $asset) {
            if (isset($asset['name']) && str_ends_with($asset['name'], '.zip')) {
                return $asset['browser_download_url'] ?? null;
            }
        }
        
        return null;
    }

    public function installUpdate(string $downloadUrl): bool {
        try {
            $this->logger->info('Starte Update-Installation', ['url' => $downloadUrl]);
            
            $zipPath = self::UPDATE_DIR . 'update_temp.zip';
            $this->downloadFile($downloadUrl, $zipPath);
            
            $backupPath = $this->createBackup();
            
            try {
                $extractPath = self::UPDATE_DIR . 'temp_extract/';
                $this->extractZip($zipPath, $extractPath);
                
                $this->copyFiles($extractPath, dirname(self::UPDATE_DIR));
                
                $this->cleanup($zipPath, $extractPath);
                
                $this->logger->info('Update erfolgreich installiert');
                return true;
                
            } catch (Exception $e) {
                $this->logger->error('Update fehlgeschlagen, führe Rollback durch');
                $this->restoreBackup($backupPath);
                throw $e;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Update-Fehler: ' . $e->getMessage());
            return false;
        }
    }

    private function downloadFile(string $url, string $destination): void {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: VantixDash-Updater\r\n",
                'timeout' => 120
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new Exception('Download fehlgeschlagen');
        }
        
        if (file_put_contents($destination, $content) === false) {
            throw new Exception('Konnte ZIP nicht speichern');
        }
    }

    private function extractZip(string $zipPath, string $extractPath): void {
        if (!extension_loaded('zip')) {
            throw new Exception('ZIP-Extension nicht verfügbar');
        }
        
        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath) !== true) {
            throw new Exception('Konnte ZIP nicht öffnen');
        }
        
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        $zip->extractTo($extractPath);
        $zip->close();
    }

    private function copyFiles(string $source, string $destination): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            
            if (str_starts_with($iterator->getSubPathname(), 'data' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function createBackup(): string {
        $backupPath = self::UPDATE_DIR . 'backup_' . date('YmdHis') . '.zip';
        $rootPath = dirname(self::UPDATE_DIR);
        
        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE) !== true) {
            throw new Exception('Konnte Backup nicht erstellen');
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);
            
            if (str_starts_with($relativePath, 'data' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            
            if ($file->isFile()) {
                $zip->addFile($filePath, $relativePath);
            }
        }
        
        $zip->close();
        return $backupPath;
    }

    private function restoreBackup(string $backupPath): void {
        if (!file_exists($backupPath)) {
            throw new Exception('Backup nicht gefunden');
        }
        
        $extractPath = dirname(self::UPDATE_DIR);
        $this->extractZip($backupPath, $extractPath);
    }

    private function cleanup(string $zipPath, string $extractPath): void {
        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }
        
        if (is_dir($extractPath)) {
            $this->deleteDirectory($extractPath);
        }
    }

    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir);
    }
}
