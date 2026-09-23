<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

use UnexpectedValueException;

/**
 * Serves an authorized, up-to-date printable view for one APA agreement.
 */
final class AgreementPdfService
{
    public const ACTION = 'apa_agadev_download_agreement_pdf';

    private const DOWNLOAD_QUERY_VAR = 'apa_agadev_download';

    private MaivouDataService $data;

    private AgreementPresentationService $presentation;

    public function __construct(
        MaivouDataService $data,
        ?AgreementPresentationService $presentation = null
    ) {
        $this->data = $data;
        $this->presentation = $presentation ?? new AgreementPresentationService();
    }

    public static function downloadUrl(int $agreementId): string
    {
        if ($agreementId < 1) {
            return '';
        }

        $url = add_query_arg([
            self::DOWNLOAD_QUERY_VAR => '1',
            'agreement_id' => $agreementId,
        ], home_url('/'));

        return wp_nonce_url($url, self::nonceAction($agreementId));
    }

    /**
     * Handles the authenticated WordPress download request.
     */
    public function handleDownload(): void
    {
        $downloadRequested = isset($_GET[self::DOWNLOAD_QUERY_VAR])
            && is_scalar($_GET[self::DOWNLOAD_QUERY_VAR])
            && '1' === sanitize_text_field(wp_unslash((string) $_GET[self::DOWNLOAD_QUERY_VAR]));

        if (! $downloadRequested) {
            return;
        }

        if (! is_user_logged_in()) {
            $this->abort(__('Vous devez être connecté pour télécharger cette demande APA.', 'plugin-apa-agadev'), 401);
        }

        $agreementId = isset($_GET['agreement_id']) && is_scalar($_GET['agreement_id'])
            ? absint(wp_unslash((string) $_GET['agreement_id']))
            : 0;
        $nonce = isset($_GET['_wpnonce']) && is_scalar($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
            : '';

        if ($agreementId < 1 || ! wp_verify_nonce($nonce, self::nonceAction($agreementId))) {
            $this->abort(__('Le lien de téléchargement est invalide ou a expiré.', 'plugin-apa-agadev'), 403);
        }

        // Maivou rechecks ownership/authorization for every download.
        $response = $this->data->getAgreement($agreementId);

        if (! $response['ok'] || ! is_array($response['data'])) {
            $this->abort(
                $this->responseError($response, __('Impossible de récupérer cette demande APA.', 'plugin-apa-agadev')),
                $this->safeStatus((int) ($response['status'] ?? 502))
            );
        }

        try {
            $detail = $this->presentation->present($response['data']);
            $html = $this->renderPrintView($detail);
        } catch (UnexpectedValueException $exception) {
            $this->logInvalidPresentation($agreementId, $exception);
            $this->abort(__('La présentation détaillée de cette demande APA est indisponible.', 'plugin-apa-agadev'), 502);
        }

        // Private HTML is printed by the browser; do not generate or stream a PDF.
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the template.
        exit;
    }

    /**
     * Renders display values without a server-side PDF engine.
     *
     * @param array<string, mixed> $detail
     */
    public function renderPrintView(array $detail): string
    {
        return $this->renderTemplate($detail);
    }

    private static function nonceAction(int $agreementId): string
    {
        return self::ACTION . '_' . $agreementId;
    }

    /** @param array<string, mixed> $detail */
    private function renderTemplate(array $detail): string
    {
        $path = PLUGIN_APA_AGADEV_PATH . 'templates/agreement-pdf.php';

        if (! is_readable($path)) {
            throw new UnexpectedValueException('The agreement PDF template is unavailable.');
        }

        $agreement_detail = $detail;
        $brand_logo_data_uri = $this->brandLogoDataUri();
        ob_start();
        require $path;

        return (string) ob_get_clean();
    }

    /**
     * Embeds the local logo so PDF generation never depends on a public URL.
     */
    private function brandLogoDataUri(): string
    {
        $path = PLUGIN_APA_AGADEV_PATH . 'assets/images/logo-agadev.svg';

        if (! is_readable($path)) {
            throw new UnexpectedValueException('The AGADEV logo is unavailable.');
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new UnexpectedValueException('The AGADEV logo is empty.');
        }

        return 'data:image/svg+xml;base64,' . base64_encode($contents);
    }

    private function logInvalidPresentation(int $agreementId, UnexpectedValueException $exception): void
    {
        // Do not log the API payload: it can contain personal or confidential data.
        error_log(sprintf(
            '[APA Agadev] Agreement PDF unavailable for agreement %d: %s',
            $agreementId,
            $exception->getMessage()
        ));
    }

    /** @param array<string, mixed> $response */
    private function responseError(array $response, string $fallback): string
    {
        $message = trim((string) ($response['error'] ?? ''));

        return $message === '' ? $fallback : $message;
    }

    private function safeStatus(int $status): int
    {
        return $status >= 400 && $status <= 599 ? $status : 502;
    }

    private function abort(string $message, int $status): void
    {
        wp_die(
            esc_html($message),
            esc_html__('Téléchargement de la demande APA', 'plugin-apa-agadev'),
            ['response' => $status]
        );
    }
}
