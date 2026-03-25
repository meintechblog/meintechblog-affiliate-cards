<?php

declare(strict_types=1);

final class MTB_Affiliate_Telegram_Handler {
    private const TRACKING_ID_OPTION = 'mtb_telegram_tracking_id';
    private const LAST_ASIN_OPTION   = 'mtb_telegram_last_asin';
    private const ASIN_PATTERN       = '/^B0[A-Z0-9]{8}$/i';
    private const ASIN_EXTRACTION_PATTERN = '/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i';
    private const AMAZON_URL_PATTERN = '/^(https?:\/\/)?(www\.)?(amazon\.[a-z.]+|amzn\.(to|eu))\//i';
    private const DATE_YYMMDD_PATTERN = '/^[0-9]{6}$/';
    private const DATE_FLEXIBLE_PATTERN = '/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/';
    private const CONFIRM_COMMANDS = ['done', 'ok', 'angelegt'];

    private MTB_Affiliate_Settings $settings;
    private MTB_Affiliate_Url_Resolver $urlResolver;
    private MTB_Affiliate_Tracking_Registry $trackingRegistry;

    public function __construct(
        MTB_Affiliate_Settings $settings,
        MTB_Affiliate_Url_Resolver $urlResolver,
        MTB_Affiliate_Tracking_Registry $trackingRegistry
    ) {
        $this->settings         = $settings;
        $this->urlResolver      = $urlResolver;
        $this->trackingRegistry = $trackingRegistry;
    }

    public function handle(array $payload): void {
        $message = $payload['message'] ?? null;
        if ($message === null) {
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text   = trim((string) ($message['text'] ?? ''));
        if ($chatId === 0 || $text === '') {
            return;
        }

        $settings = $this->settings->get_all();
        $botToken = $settings['telegram_bot_token'] ?? '';
        if ($botToken === '') {
            return;
        }

        $this->dispatch($text, $chatId, $botToken);
    }

    private function dispatch(string $rawInput, int $chatId, string $botToken): void {
        // 1. Strip Telegram/Markdown wrappers (port from flows.json input cleaning)
        $input = $rawInput;
        $input = preg_replace('/^<(.+)>$/', '$1', $input);
        $input = preg_replace('/^\((.+)\)$/', '$1', $input);
        $input = preg_replace('/^\[(.+)\]$/', '$1', $input);

        // 2. Resolve ShortURL BEFORE main dispatch (matching flows.json ShortURL Detector + Resolver)
        $shortUrl = $this->urlResolver->extract_short_url($input);
        if ($shortUrl !== null) {
            $resolved = $this->urlResolver->resolve($shortUrl);
            if ($resolved === null) {
                $this->send_message($botToken, $chatId, 'Fehler beim Aufloesen des Shortlinks.');
                return;
            }
            $input = $resolved;
        }

        // 3. Load state from wp_options (per D-04, D-05)
        $trackingId = get_option(self::TRACKING_ID_OPTION, '');
        if ($trackingId === '') {
            $trackingId = $this->default_tracking_id();
        }
        $lastAsin = get_option(self::LAST_ASIN_OPTION, '');

        // 4. Check confirm commands first (D-08, TRID-04) — "done", "ok", "angelegt"
        if (in_array(strtolower($input), self::CONFIRM_COMMANDS, true)) {
            $this->trackingRegistry->register($trackingId);
            $this->send_message($botToken, $chatId, "Tracking-ID registriert: {$trackingId}");
            // Clear warning suppression so future checks see it as registered
            delete_transient('mtb_trid_warned_' . substr(md5($trackingId), 0, 12));
            return;
        }

        // 5. "reset" (case-insensitive, from flows.json)
        if (strtolower($input) === 'reset') {
            $trackingId = $this->default_tracking_id();
            update_option(self::TRACKING_ID_OPTION, $trackingId, false);
            $text = "OK -- Tracking-ID zurueckgesetzt: {$trackingId}";
            if ($lastAsin !== '') {
                $text .= "\n\n" . $this->build_affiliate_url($lastAsin, $trackingId);
            }
            $this->send_message($botToken, $chatId, $text);
            return;
        }

        // 6. "heute" (case-insensitive, from flows.json)
        if (strtolower($input) === 'heute') {
            $d  = new \DateTime('now', new \DateTimeZone('Europe/Berlin'));
            $ts = $d->format('ymd'); // YYMMDD
            $trackingId = "meintechblog-{$ts}-21";
            update_option(self::TRACKING_ID_OPTION, $trackingId, false);
            if ($lastAsin === '') {
                $this->send_message($botToken, $chatId, "OK -- Tracking-ID gesetzt: {$trackingId}");
            } else {
                $this->send_message($botToken, $chatId, $this->build_affiliate_url($lastAsin, $trackingId));
            }
            return;
        }

        // 7. YYMMDD date (6-digit, from flows.json)
        if (preg_match(self::DATE_YYMMDD_PATTERN, $input)) {
            $yy = substr($input, 0, 2);
            $mm = substr($input, 2, 2);
            $dd = substr($input, 4, 2);
            if (! $this->is_valid_date((int) $dd, (int) $mm, $yy)) {
                $this->send_message($botToken, $chatId, "Ungueltiges Datum im Format YYMMDD: {$input}");
                return;
            }
            $trackingId = "meintechblog-{$input}-21";
            update_option(self::TRACKING_ID_OPTION, $trackingId, false);
            $text = "OK -- neue Tracking-ID gesetzt: {$trackingId}";
            if ($lastAsin !== '') {
                $text = $this->build_affiliate_url($lastAsin, $trackingId);
            }
            $this->send_message($botToken, $chatId, $text);
            return;
        }

        // 8. DD.MM.YY or DD.MM.YYYY (flexible, from flows.json)
        if (preg_match(self::DATE_FLEXIBLE_PATTERN, $input, $m)) {
            $day      = (int) $m[1];
            $month    = (int) $m[2];
            $yearPart = $m[3];
            if (! $this->is_valid_date($day, $month, $yearPart)) {
                $this->send_message($botToken, $chatId, "Ungueltiges Datum: {$input}");
                return;
            }
            $year2      = strlen($yearPart) === 4 ? substr($yearPart, 2) : $yearPart;
            $ts         = $year2 . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $trackingId = "meintechblog-{$ts}-21";
            update_option(self::TRACKING_ID_OPTION, $trackingId, false);
            $text = "OK -- neue Tracking-ID gesetzt: {$trackingId}";
            if ($lastAsin !== '') {
                $text = $this->build_affiliate_url($lastAsin, $trackingId);
            }
            $this->send_message($botToken, $chatId, $text);
            return;
        }

        // 9. Amazon URL (from flows.json)
        if (preg_match(self::AMAZON_URL_PATTERN, $input)) {
            $asin = $this->extract_asin_from_url($input);
            if ($asin === null) {
                $this->send_message($botToken, $chatId, 'Konnte keine ASIN aus der URL extrahieren.');
                return;
            }
            update_option(self::LAST_ASIN_OPTION, $asin, false);
            $this->send_message($botToken, $chatId, $this->build_affiliate_url($asin, $trackingId));
            $this->maybe_warn_unregistered($trackingId, $chatId, $botToken);
            return;
        }

        // 10. Direct ASIN (from flows.json — B0-prefixed)
        if (preg_match(self::ASIN_PATTERN, $input)) {
            $asin = strtoupper($input);
            update_option(self::LAST_ASIN_OPTION, $asin, false);
            $this->send_message($botToken, $chatId, $this->build_affiliate_url($asin, $trackingId));
            $this->maybe_warn_unregistered($trackingId, $chatId, $botToken);
            return;
        }

        // 11. Fallback (from flows.json — German help text)
        $this->send_message(
            $botToken,
            $chatId,
            "Unbekanntes Format.\n\n"
            . "Gueltige Eingaben:\n"
            . "- ASIN (B0XXXXXXXX)\n"
            . "- Amazon-URL\n"
            . "- Shortlink (amzn.to / amzn.eu)\n"
            . "- Datum YYMMDD\n"
            . "- Datum DD.MM.YY / DD.MM.YYYY\n"
            . "- heute\n"
            . "- reset\n"
            . "- done / ok / angelegt"
        );
    }

    /** Returns default tracking-ID derived from current date, matching Partner-Tag format. */
    private function default_tracking_id(): string {
        return (new MTB_Affiliate_Amazon_Client())->derive_partner_tag(current_time('Y-m-d H:i:s'));
    }

    /** Builds a simple affiliate URL in flows.json format (not the extended REST controller format). */
    private function build_affiliate_url(string $asin, string $trackingId): string {
        return "https://www.amazon.de/dp/{$asin}?tag={$trackingId}";
    }

    /** Extracts ASIN from an Amazon product URL. Returns null if not found. */
    private function extract_asin_from_url(string $url): ?string {
        if (! preg_match(self::ASIN_EXTRACTION_PATTERN, $url, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    /**
     * Validates a date. Port from flows.json isValidDate().
     * Supports 2-digit or 4-digit year strings.
     */
    private function is_valid_date(int $day, int $month, string $yearStr): bool {
        $year = strlen($yearStr) === 4 ? (int) $yearStr : 2000 + (int) $yearStr;
        return checkdate($month, $day, $year);
    }

    /**
     * Sends a message via the Telegram Bot API.
     * Never throws on failure — Telegram errors are silent.
     */
    private function send_message(string $botToken, int $chatId, string $text): void {
        wp_remote_post(
            "https://api.telegram.org/bot{$botToken}/sendMessage",
            [
                'timeout'     => 5,
                'headers'     => ['Content-Type' => 'application/json'],
                'body'        => (string) wp_json_encode([
                    'chat_id' => $chatId,
                    'text'    => $text,
                ]),
            ]
        );
    }

    /**
     * Warns once if the current tracking-ID is not in the registry.
     * Warning suppression uses a 30-day transient (per D-07/TRID-03).
     */
    private function maybe_warn_unregistered(string $trackingId, int $chatId, string $botToken): void {
        if ($this->trackingRegistry->exists($trackingId)) {
            return;
        }

        $transientKey = 'mtb_trid_warned_' . substr(md5($trackingId), 0, 12);
        if (get_transient($transientKey) !== false) {
            return;
        }

        $this->send_message(
            $botToken,
            $chatId,
            "Tracking-ID nicht in Registry: {$trackingId}\nSende 'done', 'ok' oder 'angelegt' um sie zu registrieren."
        );
        set_transient($transientKey, '1', 30 * DAY_IN_SECONDS);
    }
}
