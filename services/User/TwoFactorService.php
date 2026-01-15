<?php
declare(strict_types=1);

namespace VantixDash\User;

use VantixDash\Config\ConfigService;

/**
 * TwoFactorService - 2FA Management (TOTP)
 * 
 * Verantwortlichkeit: 2FA Enable/Disable, Secret Storage
 */
class TwoFactorService {
    private ConfigService $config;

    public function __construct(ConfigService $config) {
        $this->config = $config;
    }

    // ==================== STATUS ====================

    public function isEnabled(): bool {
        return $this->config->getBool('2fa_enabled');
    }

    public function getSecret(): string {
        return $this->config->getString('2fa_secret');
    }

    // ==================== ENABLE/DISABLE ====================

    public function enable(string $secret): bool {
        if (empty($secret)) {
            return false;
        }

        $this->config->set('2fa_enabled', true);
        $this->config->set('2fa_secret', $secret);
        
        return $this->config->save();
    }

    public function disable(): bool {
        $this->config->set('2fa_enabled', false);
        // Secret behalten für Re-Enable?
        // $this->config->remove('2fa_secret');
        
        return $this->config->save();
    }

    // ==================== VERIFICATION ====================

    public function verify(string $code, string $secret = null): bool {
        $secret = $secret ?? $this->getSecret();
        
        if (empty($secret)) {
            return false;
        }

        // Nutzt externe GoogleAuthenticator Library
        require_once dirname(__DIR__, 2) . '/libs/GoogleAuthenticator.php';
        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        return $ga->verifyCode($secret, $code, 2);
    }

    // ==================== SETUP ====================

    public function generateSecret(): string {
        require_once dirname(__DIR__, 2) . '/libs/GoogleAuthenticator.php';
        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        return $ga->createSecret();
    }

    public function getQrCodeUrl(string $secret, string $username): string {
        require_once dirname(__DIR__, 2) . '/libs/GoogleAuthenticator.php';
        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        return $ga->getQRCodeGoogleUrl($username, $secret, 'VantixDash-Monitor');
    }
}
