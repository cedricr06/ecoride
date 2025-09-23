<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function envv(string $k, $default = '')
{
    return $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k) ?: $default;
}

class Mailer
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // --- Server settings (depuis .env) ---
        $this->mailer->isSMTP();
        $this->mailer->Host       = envv('SMTP_HOST');
        $this->mailer->SMTPAuth   = true;
        $this->mailer->AuthType   = 'LOGIN'; // évite CRAM-MD5
        $this->mailer->Username   = envv('SMTP_USER');
        $this->mailer->Password   = envv('SMTP_PASS');
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // ENCRYPTION_SMTPS si port 465
        $this->mailer->Port       = (int) envv('SMTP_PORT', 0);
        $this->mailer->CharSet    = 'UTF-8';
        $this->mailer->SMTPDebug  = 0; // pas de debug en prod

        // From
        $fromEmail = envv('SMTP_FROM', 'no-reply@ecoride.local');
        $this->mailer->setFrom($fromEmail, 'EcoRide');
    }

    public function send(string $to, string $subject, string $html, ?string $alt = null): bool
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body    = $html;
            $this->mailer->AltBody = $alt ?? strip_tags($html);
            return $this->mailer->send();
        } catch (\Throwable $e) {
            @error_log('[mail][ERR] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $to
     * @param array{
     *   rider_name?:string,
     *   driver_name?:string,
     *   trip_label?:string,
     *   review_url?:string,            // chemin relatif ou URL absolue
     *   review_token?:string,          // sinon token auto-généré
     *   token?:string,                 // alias
     *   expires_at?:\DateTimeInterface|string
     * } $ctx
     * @throws Exception
     */
    public function sendReviewEmail(string $to, array $ctx): void
    {
        try {
            // Nettoyage instance (si réutilisée)
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($to);

            // Données affichées (échappées)
            $driverName = htmlspecialchars($ctx['driver_name'] ?? 'votre conducteur', ENT_QUOTES, 'UTF-8');
            $tripLabel  = htmlspecialchars($ctx['trip_label']  ?? 'votre trajet',     ENT_QUOTES, 'UTF-8');
            $riderName  = htmlspecialchars($ctx['rider_name']  ?? 'Bonjour',          ENT_QUOTES, 'UTF-8');

            // 1) Token
            $token = $ctx['review_token'] ?? $ctx['token'] ?? bin2hex(random_bytes(24));

            // 2) Base URL (APP_URL prioritaire, fallback BASE_URL, puis déduction)
            $appUrl = rtrim(envv('APP_URL', envv('BASE_URL', '')), '/');
            if ($appUrl === '') {
                $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $public = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
                $appUrl = $https . '://' . $host . $public;
            }

            // 3) URL finale : ctx['review_url'] > défaut /avis?t=token
            if (!empty($ctx['review_url'])) {
                $raw = (string) $ctx['review_url'];
                $finalUrl = preg_match('~^https?://~i', $raw)
                    ? $raw
                    : $appUrl . '/' . ltrim($raw, '/'); // absolutise un chemin relatif
            } else {
                // Robuste sans rewrite en local
                $finalUrl = $appUrl . '/avis?t=' . urlencode($token);
            }

            $reviewUrl = htmlspecialchars($finalUrl, ENT_QUOTES, 'UTF-8');

            // 4) Expiration (facultatif)
            $expStr = $ctx['expires_at'] instanceof \DateTimeInterface
                ? $ctx['expires_at']->format('d/m/Y')
                : htmlspecialchars((string)($ctx['expires_at'] ?? '14 jours'), ENT_QUOTES, 'UTF-8');

            // 5) Mail
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Laissez un avis sur votre trajet EcoRide';

            $this->mailer->Body = <<<HTML
                <p>{$riderName},</p>
                <p>Nous espérons que votre trajet <strong>{$tripLabel}</strong> avec <strong>{$driverName}</strong> s'est bien passé 😊.</p>
                <p>Aidez la communauté EcoRide en laissant un avis sur votre conducteur&nbsp;:</p>
                <p>
                  <a href="{$reviewUrl}" target="_blank" rel="noopener"
                     style="display:inline-block;padding:10px 16px;border-radius:6px;
                            text-decoration:none;background:#16a34a;color:#fff;">
                    Laisser mon avis
                  </a>
                </p>
                <p><small>Ce lien est valable jusqu’au {$expStr}.</small></p>
            HTML;

            $this->mailer->AltBody =
                "Bonjour {$riderName}\n\n" .
                "Trajet : {$tripLabel} avec {$driverName}\n" .
                "Laissez votre avis : {$finalUrl}\n" .
                "Lien valable jusqu’au {$expStr}\n";

            $this->mailer->send();
        } catch (Exception $e) {
            throw new Exception("Failed to send review email to {$to}: {$e->getMessage()}");
        }
    }
}
