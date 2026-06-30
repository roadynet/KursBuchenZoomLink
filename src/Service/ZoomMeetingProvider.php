<?php

namespace App\Service;

use RuntimeException;

final class ZoomMeetingProvider
{
    private const PLACEHOLDER_PROVIDER = 'placeholder';
    private const API_PROVIDER = 'api';

    public function getOrCreateMeeting(array $course): array
    {
        if ($this->hasExistingApiMeeting($course)) {
            $course['zoomStatus'] = 'api-ready';
            $course['zoomLastSyncedAt'] = date('c');

            return $course;
        }

        try {
            $accessToken = $this->accessToken();
            if ($accessToken !== null) {
                return $this->createApiMeeting($course, $accessToken);
            }
        } catch (\Throwable $exception) {
            return $this->createPlaceholderMeeting($course, 'zoom-api-error: '.$exception->getMessage());
        }

        return $this->createPlaceholderMeeting($course, 'placeholder-ready');
    }

    private function hasExistingApiMeeting(array $course): bool
    {
        return ($course['zoomProvider'] ?? '') === self::API_PROVIDER
            && trim((string) ($course['zoomLink'] ?? '')) !== ''
            && trim((string) ($course['zoomMeetingId'] ?? '')) !== '';
    }

    private function accessToken(): ?string
    {
        $apiKey = $this->env('ZOOM_API_KEY');
        if ($apiKey !== '') {
            return $apiKey;
        }

        $accountId = $this->env('ZOOM_ACCOUNT_ID');
        $clientId = $this->env('ZOOM_CLIENT_ID');
        $clientSecret = $this->env('ZOOM_CLIENT_SECRET');

        if ($accountId === '' || $clientId === '' || $clientSecret === '') {
            return null;
        }

        $response = $this->request(
            'POST',
            'https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.rawurlencode($accountId),
            [
                'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret),
            ],
        );

        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('kein access_token erhalten');
        }

        return $token;
    }

    private function createApiMeeting(array $course, string $accessToken): array
    {
        $hostUserId = $this->env('ZOOM_HOST_USER_ID') ?: 'me';
        $duration = (int) ($this->env('ZOOM_DEFAULT_DURATION_MINUTES') ?: 60);
        $timezone = $this->env('ZOOM_TIMEZONE') ?: 'Europe/Berlin';

        $payload = [
            'topic' => (string) ($course['title'] ?? 'Federleicht Kurs'),
            'type' => 2,
            'start_time' => sprintf('%sT%s:00', $course['date'] ?? date('Y-m-d'), $course['time'] ?? '10:00'),
            'duration' => max(15, $duration),
            'timezone' => $timezone,
            'agenda' => sprintf('Federleicht Kurs: %s', $course['title'] ?? 'Kurs'),
            'settings' => [
                'join_before_host' => false,
                'waiting_room' => true,
                'approval_type' => 2,
                'audio' => 'both',
            ],
        ];

        $response = $this->request(
            'POST',
            sprintf('https://api.zoom.us/v2/users/%s/meetings', rawurlencode($hostUserId)),
            [
                'Authorization: Bearer '.$accessToken,
                'Content-Type: application/json',
            ],
            $payload,
        );

        $meetingId = (string) ($response['id'] ?? '');
        $joinUrl = (string) ($response['join_url'] ?? '');
        if ($meetingId === '' || $joinUrl === '') {
            throw new RuntimeException('Meeting ohne id oder join_url erhalten');
        }

        $course['zoomMeetingId'] = $meetingId;
        $course['zoomPassword'] = (string) ($response['password'] ?? '');
        $course['zoomLink'] = $joinUrl;
        $course['zoomProvider'] = self::API_PROVIDER;
        $course['zoomStatus'] = 'api-ready';
        $course['zoomLastSyncedAt'] = date('c');

        return $course;
    }

    private function createPlaceholderMeeting(array $course, string $status): array
    {
        $seed = (string) ($course['id'] ?? $course['title'] ?? random_bytes(8));
        $meetingId = (string) ($course['zoomMeetingId'] ?? $this->meetingId($seed));
        $password = (string) ($course['zoomPassword'] ?? $this->password($seed));

        $course['zoomMeetingId'] = $meetingId;
        $course['zoomPassword'] = $password;
        $course['zoomLink'] = $this->meetingLink($meetingId, $password);
        $course['zoomProvider'] = self::PLACEHOLDER_PROVIDER;
        $course['zoomStatus'] = $status;
        $course['zoomLastSyncedAt'] = date('c');

        return $course;
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $headers, ?array $payload = null): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('cURL konnte nicht gestartet werden');
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException($error !== '' ? $error : 'Zoom-Request fehlgeschlagen');
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['message'] ?? $body);
            throw new RuntimeException(sprintf('Zoom HTTP %d: %s', $status, $message));
        }

        return $decoded;
    }

    private function env(string $key): string
    {
        return trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? ''));
    }

    private function meetingId(string $seed): string
    {
        $digits = preg_replace('/\D+/', '', sha1($seed));
        while (strlen($digits) < 10) {
            $digits .= preg_replace('/\D+/', '', sha1($digits.$seed));
        }

        return substr($digits, 0, 10);
    }

    private function password(string $seed): string
    {
        return substr(hash('sha256', $seed.'federleicht-zoom'), 0, 10);
    }

    private function meetingLink(string $meetingId, string $password): string
    {
        return sprintf('https://zoom.us/j/%s?pwd=%s', $meetingId, $password);
    }
}
