<?php
declare(strict_types=1);

namespace VantixDash\User;

use VantixDash\Config\ConfigService;

/**
 * PasswordService - Password + Reset Token Management
 * 
 * Verantwortlichkeit: Password Hashing, Reset Tokens
 */
class PasswordService {
    private ConfigService $config;
    private const TOKEN_LIFETIME = 1800; // 30 Minuten

    public function __construct(ConfigService $config) {
        $this->config = $config;
    }

    // ==================== PASSWORD UPDATE ====================

    public function updatePassword(string $password): bool {
        if (strlen($password) < 8) {
            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->config->set('password_hash', $hash);
        
        return $this->config->save();
    }

    public function verifyPassword(string $password): bool {
        $storedHash = $this->config->getString('password_hash');
        return password_verify($password, $storedHash);
    }

    // ==================== RESET TOKEN ====================

    public function createResetToken(): string {
        $token = bin2hex(random_bytes(32));
        
        $this->config->set('reset_token', hash('sha256', $token));
        $this->config->set('reset_expires', time() + self::TOKEN_LIFETIME);
        $this->config->save();
        
        return $token; // Raw token (für E-Mail Link)
    }

    public function verifyResetToken(string $token): bool {
        $storedHash = $this->config->getString('reset_token');
        $expires = $this->config->getInt('reset_expires');

        // Token expired?
        if (time() > $expires) {
            return false;
        }

        // Token valid?
        return hash_equals($storedHash, hash('sha256', $token));
    }

    public function clearResetToken(): void {
        $this->config->remove('reset_token');
        $this->config->remove('reset_expires');
        $this->config->save();
    }

    public function getTokenExpiry(): int {
        return $this->config->getInt('reset_expires');
    }
}
