<?php
declare(strict_types=1);

namespace VantixDash\Mail;

use VantixDash\Config\ConfigService;

/**
 * SmtpConfigService - SMTP Configuration Management
 * 
 * Verantwortlichkeit: SMTP Settings für MailService
 */
class SmtpConfigService {
    private ConfigService $config;

    private const DEFAULT_CONFIG = [
        'host' => '',
        'user' => '',
        'pass' => '',
        'port' => 587,
        'from_email' => '',
        'from_name' => 'VantixDash'
    ];

    public function __construct(ConfigService $config) {
        $this->config = $config;
    }

    // ==================== GETTER ====================

    public function getConfig(): array {
        return $this->config->getArray('smtp', self::DEFAULT_CONFIG);
    }

    public function getHost(): string {
        $smtp = $this->getConfig();
        return (string)($smtp['host'] ?? '');
    }

    public function getPort(): int {
        $smtp = $this->getConfig();
        return (int)($smtp['port'] ?? 587);
    }

    public function getUsername(): string {
        $smtp = $this->getConfig();
        return (string)($smtp['user'] ?? '');
    }

    public function getPassword(): string {
        $smtp = $this->getConfig();
        return (string)($smtp['pass'] ?? '');
    }

    public function getFromEmail(): string {
        $smtp = $this->getConfig();
        return (string)($smtp['from_email'] ?? '');
    }

    public function getFromName(): string {
        $smtp = $this->getConfig();
        return (string)($smtp['from_name'] ?? 'VantixDash');
    }

    // ==================== SETTER ====================

    public function updateConfig(array $newData): bool {
        // Validation
        $validated = [
            'host'       => (string)($newData['host'] ?? ''),
            'user'       => (string)($newData['user'] ?? ''),
            'pass'       => (string)($newData['pass'] ?? ''),
            'port'       => (int)($newData['port'] ?? 587),
            'from_email' => (string)($newData['from_email'] ?? ''),
            'from_name'  => (string)($newData['from_name'] ?? 'VantixDash')
        ];

        // Port Validation
        if ($validated['port'] < 1 || $validated['port'] > 65535) {
            return false;
        }

        // Email Validation
        if (!empty($validated['from_email']) && 
            !filter_var($validated['from_email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $this->config->set('smtp', $validated);
        return $this->config->save();
    }

    // ==================== HELPERS ====================

    public function isConfigured(): bool {
        $smtp = $this->getConfig();
        return !empty($smtp['host']) && !empty($smtp['user']);
    }

    public function testConnection(): bool {
        // TODO: Implement SMTP connection test
        return true;
    }
}
