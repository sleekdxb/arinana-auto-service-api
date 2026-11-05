<?php

namespace App\Helpers;

use App\Models\ClientSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PushHelper
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public static function isConfigured(): bool
    {
        return self::credentials() !== null;
    }

    /**
     * Sends to every device the client is signed in on. Tokens Firebase
     * reports as gone are cleared so they are not tried again.
     */
    public static function sendToClient(string $clientId, string $title, ?string $body, array $data = []): int
    {
        $credentials = self::credentials();

        if (!$credentials) {
            return 0;
        }

        $tokens = ClientSession::where('client_id', $clientId)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('fcm_token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $accessToken = self::accessToken($credentials);
        $url = 'https://fcm.googleapis.com/v1/projects/' . $credentials['project_id'] . '/messages:send';
        $payload = array_map('strval', array_filter($data, fn($value) => $value !== null));
        $sent = 0;

        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => array_filter(['title' => $title, 'body' => $body]),
                    'data' => $payload,
                    'android' => [
                        'priority' => 'high',
                        'notification' => ['channel_id' => 'booking_updates'],
                    ],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

            if ($response->successful()) {
                $sent++;
                continue;
            }

            $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');

            if ($status === 'UNREGISTERED' || $response->status() === 404) {
                ClientSession::where('fcm_token', $token)->update(['fcm_token' => null]);
            } else {
                \Log::warning('Push Error: ' . $response->body());
            }
        }

        return $sent;
    }

    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (!$path || !is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        $json['project_id'] = config('services.fcm.project_id') ?: ($json['project_id'] ?? null);

        return $json['project_id'] ? $json : null;
    }

    private static function accessToken(array $credentials): string
    {
        return Cache::remember('fcm_access_token', now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            $segments = [
                self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                self::base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => $tokenUri,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], 'sha256WithRSAEncryption');
            $segments[] = self::base64Url($signature);

            return Http::asForm()->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
