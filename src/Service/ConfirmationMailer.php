<?php

namespace App\Service;

use RuntimeException;

final class ConfirmationMailer
{
    /**
     * @param array<string, mixed> $course
     * @param array<string, string> $booking
     *
     * @return array<int, string>
     */
    public function sendPaymentConfirmation(array $course, array $booking): array
    {
        if ($this->env('CONFIRMATION_EMAIL_ENABLED') === '0') {
            return [];
        }

        $customerEmail = trim((string) ($booking['email'] ?? ''));
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Keine gueltige Kunden-E-Mail vorhanden.');
        }

        $copyEmail = $this->env('CONFIRMATION_COPY_EMAIL');
        $recipients = [$customerEmail];
        if ($copyEmail !== '' && filter_var($copyEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $copyEmail;
        }

        $subject = 'Bestaetigung deiner Kursbuchung';
        $body = $this->messageBody($course, $booking);
        $headers = $this->headers();

        foreach (array_unique($recipients) as $recipient) {
            if (!mail($recipient, $this->encodeSubject($subject), $body, $headers)) {
                throw new RuntimeException(sprintf('E-Mail an %s konnte nicht versendet werden.', $recipient));
            }
        }

        return array_unique($recipients);
    }

    /**
     * @param array<string, mixed> $course
     * @param array<string, string> $booking
     */
    private function messageBody(array $course, array $booking): string
    {
        $lines = [
            sprintf('Hallo %s,', $booking['name'] ?? ''),
            '',
            'deine Zahlung ist eingegangen. Deine Kursbuchung ist damit bestaetigt.',
            '',
            sprintf('Kurs: %s', $course['title'] ?? ''),
            sprintf('Kursleitung: %s', $course['teacher'] ?? ''),
            sprintf('Termin: %s um %s Uhr', $this->formatDate((string) ($course['date'] ?? '')), $course['time'] ?? ''),
            sprintf('Zoom-Link: %s', $course['zoomLink'] ?? ''),
            '',
            'Viele Gruesse',
            $this->env('CONFIRMATION_SENDER_NAME') ?: 'Federleicht',
        ];

        return implode("\n", $lines);
    }

    private function headers(): string
    {
        $fromEmail = $this->env('CONFIRMATION_FROM_EMAIL') ?: 'noreply@fd.mcmonaco.de';
        $fromName = $this->env('CONFIRMATION_SENDER_NAME') ?: 'Federleicht';
        $replyTo = $this->env('CONFIRMATION_REPLY_TO') ?: $fromEmail;

        return implode("\n", [
            sprintf('From: %s <%s>', $this->encodeHeader($fromName), $fromEmail),
            sprintf('Reply-To: %s', $replyTo),
            'Content-Type: text/plain; charset=UTF-8',
            'MIME-Version: 1.0',
        ]);
    }

    private function formatDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return date('d.m.Y', strtotime($value));
    }

    private function encodeSubject(string $value): string
    {
        return $this->encodeHeader($value);
    }

    private function encodeHeader(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B');
        }

        return $value;
    }

    private function env(string $key): string
    {
        return trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? ''));
    }
}
