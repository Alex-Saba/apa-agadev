<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use UnexpectedValueException;

/**
 * Generates an authorized, up-to-date PDF for one APA agreement.
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
            $pdf = $this->renderPdf($detail);
        } catch (UnexpectedValueException $exception) {
            $this->logInvalidPresentation($agreementId, $exception);
            $this->abort(__('La présentation détaillée de cette demande APA est indisponible.', 'plugin-apa-agadev'), 502);
        }

        $reference = (string) ($detail['code'] ?: $agreementId);
        $filename = sanitize_file_name('demande-apa-' . $reference . '.pdf');

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF output.
        exit;
    }

    /**
     * Generates the binary document from the same safe view model as HTML.
     *
     * @param array<string, mixed> $detail
     */
    public function renderPdf(array $detail): string
    {
        if (! class_exists(Dompdf::class)) {
            throw new UnexpectedValueException('The PDF engine is unavailable.');
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('tempDir', get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->renderTemplate($detail), 'UTF-8');
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $boldFont = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold');
        $footerColor = [0.31, 0.37, 0.33];
        $brandColor = [0.07, 0.24, 0.15];
        $canvas = $dompdf->getCanvas();

        // The official document footer is repeated consistently on every page.
        $canvas->line(42, 797, 553, 797, [0.77, 0.84, 0.79], 0.6);

        $canvas->page_text(
            42,
            806,
            __('MAIVOU  ·  DEMANDE APA', 'plugin-apa-agadev'),
            $boldFont,
            7.5,
            $brandColor
        );
        $canvas->page_text(
            202,
            806,
            __('Document généré depuis les données enregistrées', 'plugin-apa-agadev'),
            $font,
            7.5,
            $footerColor
        );
        $canvas->page_text(
            520,
            806,
            __('Page {PAGE_NUM}', 'plugin-apa-agadev'),
            $font,
            7.5,
            $footerColor
        );
        $pdf = $dompdf->output();

        if (! is_string($pdf) || $pdf === '') {
            throw new UnexpectedValueException('The generated PDF is empty.');
        }

        return $pdf;
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
