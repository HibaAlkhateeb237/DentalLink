<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    private const SCOPES = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FCM_V1_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    public function send(array $deviceTokens, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return;
        }

        $projectId = $this->getProjectId();
        if ($projectId === null) {
            return;
        }

        $url = sprintf(self::FCM_V1_URL, $projectId);

        foreach ($deviceTokens as $token) {
            $this->sendMessage($accessToken, $url, $token, $title, $body, $data);
        }
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->send($tokens, $title, $body, $data);
    }

    private function sendMessage(string $accessToken, string $url, string $token, string $title, string $body, array $data): void
    {
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if ($data !== []) {
            $payload['message']['data'] = $data;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post($url, $payload);

            if ($response->successful()) {
                return;
            }

            $responseBody = $response->json();
            $errorCode = $responseBody['error']['details'][0]['errorCode']
                ?? $responseBody['error']['status']
                ?? 'UNKNOWN';

            if (in_array($errorCode, ['UNREGISTERED', 'NOT_FOUND'], true)) {
                DeviceToken::where('token', $token)->delete();
                Log::info('FCM: removed unregistered device token', ['token' => $token]);
            } else {
                Log::error('FCM send failed', [
                    'token' => substr($token, 0, 16).'...',
                    'status' => $response->status(),
                    'error' => $errorCode,
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('FCM send exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(55), function () {
            return $this->fetchAccessToken();
        });
    }

    private function fetchAccessToken(): ?string
    {
        $credentials = $this->getCredentials();
        if ($credentials === null) {
            return null;
        }

        $jwt = $this->createAssertion($credentials);

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

            if ($response->failed()) {
                Log::error('FCM: failed to fetch access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        } catch (\Exception $e) {
            Log::error('FCM: access token exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getCredentials(): ?array
    {
        $path = config('services.fcm.credentials_path');

        if (empty($path) || ! file_exists($path)) {
            Log::warning('FCM: credentials file not found at: '.($path ?? 'not set'));

            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            Log::error('FCM: could not read credentials file', ['path' => $path]);

            return null;
        }

        $credentials = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('FCM: invalid JSON in credentials file', ['error' => json_last_error_msg()]);

            return null;
        }

        return $credentials;
    }

    private function getProjectId(): ?string
    {
        $credentials = $this->getCredentials();

        return $credentials['project_id'] ?? null;
    }

    private function createAssertion(array $credentials): string
    {
        $now = time();

        $header = self::base64urlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $payload = self::base64urlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPES,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if ($privateKey === false) {
            throw new \RuntimeException('FCM: invalid private key in credentials');
        }

        $signature = '';
        openssl_sign("$header.$payload", $signature, $privateKey, 'sha256WithRSAEncryption');
        openssl_free_key($privateKey);

        return "$header.$payload.".self::base64urlEncode($signature);
    }

    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
