<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleVisionService
{
    protected array $credentials = [];

    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);

        if (function_exists('base_path')) {
            try {
                $basePath = base_path();
            } catch (\Throwable $e) {
                $basePath = dirname(__DIR__, 2);
            }
        }

        $path = $basePath . DIRECTORY_SEPARATOR . ltrim((string) env('GOOGLE_VISION_CREDENTIALS_PATH', ''), DIRECTORY_SEPARATOR);

        if (!empty($path) && file_exists($path)) {
            $contents = @file_get_contents($path);
            if ($contents !== false) {
                $this->credentials = json_decode($contents, true) ?? [];
            }
        }
    }

    /**
     * استخراج النص من صورة Base64
     * يحاول DOCUMENT_TEXT_DETECTION أولاً، ثم TEXT_DETECTION كاحتياط
     */
    public function extractRawText(string $base64Image): ?string
    {
        try {

            if (str_contains($base64Image, 'base64,')) {
                $base64Image = explode('base64,', $base64Image)[1];
            }

            $token = $this->getAccessToken();

            // المحاولة الأولى: DOCUMENT_TEXT_DETECTION (أفضل للبطاقات والوثائق)
            $response = $this->sendVisionRequest($base64Image, 'DOCUMENT_TEXT_DETECTION', $token, true);

            if ($response->successful()) {
                $text = data_get(
                    $response->json(),
                    'responses.0.fullTextAnnotation.text'
                );

                if ($text && strlen(trim($text)) > 0) {
                    return trim($text);
                }
            }

            // المحاولة الثانية: TEXT_DETECTION كاحتياط
            Log::info('Vision OCR: DOCUMENT_TEXT_DETECTION returned empty, trying TEXT_DETECTION');

            $response = $this->sendVisionRequest($base64Image, 'TEXT_DETECTION', $token, true);

            if (!$response->successful()) {

                Log::error('Vision API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return data_get(
                $response->json(),
                'responses.0.textAnnotations.0.description'
            );

        } catch (\Throwable $e) {

            Log::error('Vision OCR failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function sendVisionRequest(string $base64Image, string $featureType, string $token, bool $verifySsl = true)
    {
        try {
            return Http::withToken($token)
                ->timeout(60)
                ->withOptions($this->getHttpOptions($verifySsl))
                ->post(
                    'https://vision.googleapis.com/v1/images:annotate',
                    [
                        'requests' => [
                            [
                                'image' => [
                                    'content' => $base64Image,
                                ],
                                'features' => [
                                    [
                                        'type' => $featureType,
                                    ],
                                ],
                            ],
                        ],
                    ]
                );
        } catch (\Throwable $e) {
            if ($verifySsl) {
                Log::warning('Vision OCR request failed with SSL verification; retrying without verification', [
                    'message' => $e->getMessage(),
                ]);

                return Http::withToken($token)
                    ->timeout(60)
                    ->withOptions($this->getHttpOptions(false))
                    ->post(
                        'https://vision.googleapis.com/v1/images:annotate',
                        [
                            'requests' => [
                                [
                                    'image' => [
                                        'content' => $base64Image,
                                    ],
                                    'features' => [
                                        [
                                            'type' => $featureType,
                                        ],
                                    ],
                                ],
                            ],
                        ]
                    );
            }

            throw $e;
        }
    }

    /**
     * استخراج Access Token مع Cache
     */
    protected function getAccessToken(): string
    {
        return Cache::remember(
            'google_vision_access_token',
            now()->addMinutes(55),
            function () {

                $jwt = $this->createJwt();

                try {
                    return $this->requestAccessToken($jwt, true);
                } catch (\Throwable $e) {
                    Log::warning('Google Vision token request failed; retrying without SSL verification', [
                        'message' => $e->getMessage(),
                    ]);

                    return $this->requestAccessToken($jwt, false);
                }
            }
        );
    }

    protected function requestAccessToken(string $jwt, bool $verifySsl = true): string
    {
        $response = Http::asForm()
            ->withOptions($this->getHttpOptions($verifySsl))
            ->post(
                'https://oauth2.googleapis.com/token',
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]
            );

        if (!$response->successful()) {
            throw new \Exception($response->body());
        }

        return $response['access_token'];
    }

    protected function getHttpOptions(bool $verifySsl = true): array
    {
        return [
            'verify' => $verifySsl,
        ];
    }

    /**
     * إنشاء JWT
     */
    protected function createJwt(): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $now = time();

        $payload = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [];

        $segments[] = $this->base64UrlEncode(json_encode($header));

        $segments[] = $this->base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);

        openssl_sign(
            $signingInput,
            $signature,
            $this->credentials['private_key'],
            OPENSSL_ALGO_SHA256
        );

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    protected function base64UrlEncode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * استخراج الرقم القومي المصري من النص المستخرج من OCR
     * يدعم حالات حيث يكون الرقم مفصول بمسافات أو أحرف أخرى
     */
    public function extractEgyptianNationalId(string $rawText): ?string
    {
        $digitsOnly = $this->normalizeDigits($rawText);

        if (preg_match_all('/[23]\d{13}/', $digitsOnly, $matches)) {
            foreach ($matches[0] as $candidate) {
                if ($this->isValidEgyptianNationalId($candidate)) {
                    return $candidate;
                }
            }
        }

        if (preg_match_all('/[23][\d\s\-\.]{13,}/', $rawText, $matches)) {
            foreach ($matches[0] as $candidate) {
                $digitsOnlyCandidate = $this->normalizeDigits($candidate);
                if (strlen($digitsOnlyCandidate) === 14 && $this->isValidEgyptianNationalId($digitsOnlyCandidate)) {
                    return $digitsOnlyCandidate;
                }
            }
        }

        return null;
    }

    protected function normalizeDigits(string $text): string
    {
        $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];

        $text = strtr($text, $map);
        return preg_replace('/[^\d]/u', '', $text) ?? '';
    }

    protected function isValidEgyptianNationalId(string $id): bool
    {
        if (strlen($id) !== 14) {
            return false;
        }

        $century = (int) $id[0];
        $month = (int) substr($id, 3, 2);
        $day = (int) substr($id, 5, 2);
        $governorate = (int) substr($id, 6, 2);

        // القرن: 2 = 1900s, 3 = 2000s
        if (!in_array($century, [2, 3])) {
            return false;
        }

        // الشهر
        if ($month < 1 || $month > 12) {
            return false;
        }

        // اليوم
        if ($day < 1 || $day > 31) {
            return false;
        }

        // كود المحافظة (01-29 للمحافظات، 88 للأجانب، 99 لغير مصريين)
        if ($governorate < 1 || ($governorate > 29 && $governorate !== 88 && $governorate !== 99)) {
            return false;
        }

        return true;
    }
}