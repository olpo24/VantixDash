<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Pfade zu den PHPMailer Dateien - Stelle sicher, dass diese existieren!
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

    public function send(string $to, string $subject, string $bodyHtml, string $altText = ''): bool {
        $mail = new PHPMailer(true);
        
        // Hier lag der Fehler: Aufruf von getSmtpConfig() statt getSmtpSettings()
        $smtpSettings = $this->config->getSmtpConfig(); 

        try {
            // SMTP Einstellungen
            $mail->isSMTP();
			$mail->Timeout = 10;
            $mail->Host       = $smtpSettings['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpSettings['user'];
            $mail->Password   = $smtpSettings['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Standard für Port 587
            $mail->Port       = (int)$smtpSettings['port'];
            $mail->CharSet    = 'UTF-8';

            // Absender & Empfänger
            $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
            $mail->addAddress($to);

            // Inhalt
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $altText ?: strip_tags($bodyHtml);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Detaillierten Fehler ins VantixDash Log schreiben
            $this->logger->error("SMTP Versandfehler: " . $mail->ErrorInfo, [
                'to' => $to,
                'host' => $smtpSettings['host']
            ]);
            return false;
        }
    }
}
