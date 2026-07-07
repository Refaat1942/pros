<?php

namespace App\Services\Notifications;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * مُرسِل إشعارات Firebase Cloud Messaging عبر HTTP v1 — بلا حزم خارجية.
 *
 * يوقّع JWT بمفتاح حساب الخدمة (RS256) ويستبدله بـ access token من Google،
 * ثم يرسل الرسائل إلى أجهزة (tokens). كل العمليات محميّة — أي فشل يُسجَّل
 * ولا يُعطّل سير العمل (الإشعار الداخلي يبقى محفوظاً ويُعرض عبر اللوحات).
 */
class FirebaseService
{
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'firebase.access_token';

    public function enabled(): bool
    {
        return (bool) config('firebase.enabled') && is_string($this->credentialsPath()) && is_file($this->credentialsPath());
    }

    /**
     * إرسال إشعار لمجموعة أجهزة (FCM tokens).
     *
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     * @return int عدد الرسائل المُرسلة بنجاح
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        if (! $this->enabled() || $tokens === []) {
            return 0;
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = (string) config('firebase.project_id');

            if ($accessToken === null || $projectId === '') {
                return 0;
            }

            $client = $this->httpClient();
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
            $sent = 0;

            foreach ($tokens as $token) {
                try {
                    $client->post($url, [
                        'headers' => [
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'message' => [
                                'token' => $token,
                                'notification' => ['title' => $title, 'body' => $body],
                                'data' => array_map('strval', $data),
                                'webpush' => [
                                    'notification' => ['icon' => '/favicon.ico'],
                                ],
                            ],
                        ],
                    ]);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('FCM send failed for a token', ['error' => $e->getMessage()]);
                }
            }

            return $sent;
        } catch (\Throwable $e) {
            Log::error('FCM dispatch failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * يحصل على access token صالح (يُخزَّن مؤقتاً حتى قُبيل انتهائه).
     */
    private function accessToken(): ?string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function () {
            $sa = $this->serviceAccount();

            if ($sa === null) {
                return null;
            }

            $jwt = $this->signJwt($sa);

            if ($jwt === null) {
                return null;
            }

            $response = $this->httpClient()->post(self::TOKEN_URI, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);

            return $payload['access_token'] ?? null;
        });
    }

    /**
     * يبني ويوقّع JWT (RS256) المطلوب لتبادل OAuth2.
     *
     * @param  array<string, mixed>  $sa
     */
    private function signJwt(array $sa): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URI,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [
            $this->base64Url(json_encode($header)),
            $this->base64Url(json_encode($claims)),
        ];

        $signingInput = implode('.', $segments);
        $signature = '';

        if (! openssl_sign($signingInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            Log::error('Firebase JWT signing failed');

            return null;
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serviceAccount(): ?array
    {
        $path = $this->credentialsPath();

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        return $json;
    }

    private function credentialsPath(): mixed
    {
        return config('firebase.credentials');
    }

    private function httpClient(): Client
    {
        return new Client(['timeout' => 10, 'connect_timeout' => 5]);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
