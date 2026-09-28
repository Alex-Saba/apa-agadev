<?php

declare(strict_types=1);

namespace PluginApaAgadev\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

final class OrderConfirmationService
{
    /** Called only on the submission POST, never when opening the confirmation page. */
    public function send(array $response, string $intent): bool
    {
        $data = new OrderDataService();
        $order = $data->entity($response);
        $uuid = $order['uuid'] ?? '';
        if ($intent !== 'submit' || empty($response['ok']) || ($order['status'] ?? '') !== 'submitted'
            || ! is_string($uuid) || ! $data->validUuid($uuid)) return false;

        $user = wp_get_current_user();
        if (! $user->ID || ! is_email($user->user_email)) {
            error_log('[APA Agadev] Order confirmation: invalid submitter email for order ' . $uuid);
            return false;
        }

        // A unique option atomically claims the order across concurrent requests.
        // Keep a successful or interrupted claim: never resend on a page refresh.
        $key = 'apa_order_confirmation_' . $uuid;
        if (! add_option($key, ['status' => 'sending', 'at' => time()], '', false)) return false;
        $path = null;
        $accepted = false;
        $phase = 'pdf';
        try {
            $pdf = $this->pdf($order);
            $phase = 'temporary_file';
            $path = tempnam(get_temp_dir(), 'apa-order-');
            if ($path === false) throw new \RuntimeException('temporary_file');
            $pdfPath = $path . '.pdf';
            if (! rename($path, $pdfPath)) throw new \RuntimeException('temporary_filename');
            $path = $pdfPath;
            if (file_put_contents($path, $pdf) !== strlen($pdf)) throw new \RuntimeException('pdf_write');
            $reference = sanitize_text_field((string) ($order['code'] ?? '')) ?: $uuid;
            $subject = 'Confirmation de soumission de votre commande ' . $reference;
            $message = "Bonjour,\n\nNous avons bien reçu votre commande " . $reference
                . ". Vous trouverez son récapitulatif en pièce jointe au format PDF."
                . " Vous pouvez suivre son statut depuis votre espace personnel WordPress.\n\nL’équipe Maivou";
            $phase = 'mail';
            $accepted = wp_mail($user->user_email, $subject, $message, ['Content-Type: text/plain; charset=UTF-8'], [$path]);
            if (! $accepted) throw new \RuntimeException('mail_rejected');
            // This records acceptance by WordPress's mail transport, not inbox delivery.
            $phase = 'record';
            update_option($key, ['status' => 'accepted', 'at' => time()], false);
            return true;
        } catch (\Throwable $error) {
            if (! $accepted) delete_option($key);
            // No email addresses, tokens or order contents in diagnostics.
            error_log('[APA Agadev] Order confirmation failed for ' . $uuid . ' at ' . $phase . ' (' . get_class($error) . ')');
            return false;
        } finally {
            if (is_string($path) && is_file($path)) unlink($path);
        }
    }

    public function pdf(array $order): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', get_temp_dir());
        $options->set('fontCache', get_temp_dir());
        // Embed the same local logo as the APA document, with no remote dependency.
        $logo = file_get_contents(PLUGIN_APA_AGADEV_PATH . 'assets/images/logo-agadev.svg');
        if (! is_string($logo) || trim($logo) === '') {
            throw new \RuntimeException('AGADEV logo unavailable');
        }
        $brand_logo_data_uri = 'data:image/svg+xml;base64,' . base64_encode($logo);
        $renderer = new Dompdf($options);
        ob_start();
        try {
            require PLUGIN_APA_AGADEV_PATH . 'templates/order-confirmation-pdf.php';
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $renderer->loadHtml($html, 'UTF-8');
        $renderer->setPaper('A4');
        $renderer->render();
        return $renderer->output();
    }
}
