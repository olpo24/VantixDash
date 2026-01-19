<?php
declare(strict_types=1);

namespace VantixDash;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use VantixDash\Mail\SmtpConfigService;

/**
 * MailService - Verwaltet den E-Mail-Versand via PHPMailer
 */
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

class MailService {
    private SmtpConfigService $smtpConfig;
    private Logger $logger;

    public function __construct(SmtpConfigService $smtpConfig, Logger $logger) {
        $this->smtpConfig = $smtpConfig;
$this->logger = $logger;
}
	/**
 * Versendet eine E-Mail unter Nutzung der zentralen Konfiguration
 */
public function send(string $to, string $subject, string $bodyHtml, string $altText = ''): bool {
    $mail = new PHPMailer(true);

    try {
        // Server Einstellungen
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        
        $mail->Host       = $this->smtpConfig->getHost();
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->smtpConfig->getUsername();
        $mail->Password   = $this->smtpConfig->getPassword();
        $mail->Port       = $this->smtpConfig->getPort();

        // Automatische Verschlüsselungswahl basierend auf dem Port
        if ($mail->Port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Absender & Empfänger
        $mail->setFrom(
            $this->smtpConfig->getFromEmail(),
            $this->smtpConfig->getFromName()
        );
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
            'host'  => $this->smtpConfig->getHost(),
            'port'  => $this->smtpConfig->getPort()
        ]);
        return false;
    }
}
}
