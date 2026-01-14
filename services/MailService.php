<?php
declare(strict_types=1);

namespace VantixDash;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * MailService - Verwaltet den E-Mail-Versand via PHPMailer
 */
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

class MailService {
    private ConfigService $config;
    private Logger $logger;

    public function __construct(ConfigService $config, Logger $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Versendet eine E-Mail unter Nutzung der zentralen Konfiguration
     */
    public function send(string $to, string $subject, string $bodyHtml, string $altText = ''): bool {
        $mail = new PHPMailer(true);
        
        // Nutzt die neue typsichere getArray-Methode
        $smtp = $this->config->getSmtpConfig(); 

        try {
            // Server Einstellungen
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = $this->config->getTimeout('api'); // Zentrales Timeout nutzen
            
            $mail->Host       = (string)($smtp['host'] ?? '');
            $mail->SMTPAuth   = true;
            $mail->Username   = (string)($smtp['user'] ?? '');
            $mail->Password   = (string)($smtp['pass'] ?? '');
            $mail->Port       = (int)($smtp['port'] ?? 587);

            // Automatische Verschlüsselungswahl basierend auf dem Port
            if ($mail->Port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Absender & Empfänger
            $fromEmail = (string)($smtp['from_email'] ?? 'no-reply@vantixdash.local');
            $fromName  = (string)($smtp['from_name'] ?? 'VantixDash');
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);

            // Inhalt
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $altText ?: strip_tags($bodyHtml);

            $mail->send();
            return true;

        } catch (Exception $e) {
            // Logging mit Kontext für einfachere Fehlersuche
            $this->logger->error("SMTP Versandfehler: " . $mail->ErrorInfo, [
                'to'    => $to,
                'host'  => $smtp['host'] ?? 'unknown',
                'port'  => $smtp['port'] ?? 'unknown'
            ]);
            return false;
        }
    }
}
