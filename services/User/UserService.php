<?php
declare(strict_types=1);

namespace VantixDash\User;

use VantixDash\Config\ConfigService;

/**
 * UserService - User Account Management
 * 
 * Verantwortlichkeit: Username, Email, Basic User Data
 */
class UserService {
    private ConfigService $config;

    public function __construct(ConfigService $config) {
        $this->config = $config;
    }

    // ==================== GETTER ====================

    public function getUsername(): string {
        return $this->config->getString('username');
    }

    public function getEmail(): string {
        return $this->config->getString('email');
    }

    public function getUserData(): array {
        return [
            'username' => $this->getUsername(),
            'email' => $this->getEmail(),
            '2fa_enabled' => $this->config->getBool('2fa_enabled')
        ];
    }

    // ==================== SETTER ====================

    public function updateProfile(string $username, string $email): bool {
        // Validation
        $username = htmlspecialchars(strip_tags(trim($username)), ENT_QUOTES, 'UTF-8');
        
        $sanitizedEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($sanitizedEmail === false) {
            return false;
        }

        // Update
        $this->config->set('username', $username);
        $this->config->set('email', $sanitizedEmail);
        
        return $this->config->save();
    }

    // ==================== VALIDATION ====================

    public function verifyCredentials(string $username, string $password): bool {
        $storedUser = $this->getUsername();
        $storedHash = $this->config->getString('password_hash');

        return $username === $storedUser && password_verify($password, $storedHash);
    }
}
