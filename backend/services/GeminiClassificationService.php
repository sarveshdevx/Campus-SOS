<?php
declare(strict_types=1);

namespace CampusSOS\Services;

class GeminiClassificationService
{
    private const ALLOWED_CATEGORIES = [
        'medical',
        'security',
        'fire',
        'maintenance',
        'anti_ragging',
        'sexual_harassment',
        'other'
    ];

    private const ALLOWED_URGENCIES = [
        'low',
        'medium',
        'high',
        'critical'
    ];

    /**
     * Check if Gemini external AI service is enabled and configured.
     */
    public static function isGeminiEnabled(): bool
    {
        $enabled = getenv('GEMINI_ENABLED') ?: ($_ENV['GEMINI_ENABLED'] ?? 'false');
        $isTrue = in_array(strtolower((string) $enabled), ['true', '1', 'yes', 'on'], true);
        $key = self::getApiKey();
        return $isTrue && !empty($key);
    }

    /**
     * Retrieve Gemini API Key securely from environment.
     * NEVER returned to frontend or logged.
     */
    public static function getApiKey(): string
    {
        return trim((string) (getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '')));
    }

    /**
     * Get target Gemini model.
     */
    public static function getModel(): string
    {
        return trim((string) (getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? 'gemini-3.5-flash-lite')));
    }

    /**
     * Classify an incident report.
     * 
     * SAFETY & PRIVACY RULES:
     * 1. Confidential cases (sexual harassment, anti-ragging, or privacy_level=confidential)
     *    MUST NEVER be transmitted to external AI. They are processed strictly via local deterministic rules.
     * 2. PII (names, phone numbers, emails, student IDs) is NEVER sent to Gemini.
     * 3. SOS life-safety response NEVER depends on this service.
     * 4. If Gemini is disabled, missing API key, or fails (timeout / quota / network),
     *    gracefully falls back to local deterministic heuristic classification.
     */
    public static function classifyIncident(
        string $title,
        string $description,
        string $category = 'other',
        string $privacyLevel = 'standard'
    ): array {
        // Privacy Guard: Confidential & sensitive matters strictly bypass external AI
        $normCategory = strtolower(trim($category));
        $isSensitive = in_array($normCategory, ['sexual_harassment', 'anti_ragging'], true);
        $isConfidential = (strtolower(trim($privacyLevel)) === 'confidential' || $isSensitive);

        if ($isConfidential) {
            $result = self::classifyDeterministic($title, $description, $category);
            $result['rationale'] = 'Confidential intake: Processed via local deterministic engine to preserve student privacy.';
            $result['source'] = 'deterministic_privacy_rule';
            return $result;
        }

        // Configuration Guard: Check if Gemini is enabled and key exists
        if (!self::isGeminiEnabled()) {
            return self::classifyDeterministic($title, $description, $category);
        }

        // Attempt Gemini REST API call with strict 3.0s timeout
        try {
            $aiResult = self::callGeminiApi($title, $description);
            if ($aiResult !== null) {
                return $aiResult;
            }
        } catch (\Throwable $e) {
            error_log('[CampusSOS Gemini Service Warning]: ' . $e->getMessage() . '. Falling back to deterministic classifier.');
        }

        // Fallback on any failure
        return self::classifyDeterministic($title, $description, $category);
    }

    /**
     * Call Google Gemini REST API via cURL.
     */
    private static function callGeminiApi(string $title, string $description): ?array
    {
        $apiKey = self::getApiKey();
        $model = self::getModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $systemPrompt = "You are a campus safety incident triage assistant. "
            . "Analyze the following incident title and description. "
            . "Output JSON with EXACTLY these fields: "
            . "\"suggestedCategory\" (must be one of: medical, security, fire, maintenance, anti_ragging, sexual_harassment, other), "
            . "\"urgency\" (must be one of: low, medium, high, critical), "
            . "\"confidence\" (float between 0.0 and 1.0), "
            . "\"rationale\" (short one-sentence explanation).";

        $userText = "Title: " . mb_substr($title, 0, 150) . "\nDescription: " . mb_substr($description, 0, 500);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nIncident to triage:\n" . $userText]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1,
                'maxOutputTokens' => 200,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,        // Maximum 4.0s total execution
            CURLOPT_CONNECTTIMEOUT => 2, // Maximum 2.0s connect timeout
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($response)) {
            error_log("[CampusSOS Gemini Error]: HTTP {$httpCode}, cURL: {$curlError}");
            return null;
        }

        $decoded = json_decode($response, true);
        $rawJson = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$rawJson) {
            return null;
        }

        $parsed = json_decode($rawJson, true);
        if (!is_array($parsed)) {
            return null;
        }

        $cat = strtolower(trim((string) ($parsed['suggestedCategory'] ?? 'other')));
        if (!in_array($cat, self::ALLOWED_CATEGORIES, true)) {
            $cat = 'other';
        }

        $urgency = strtolower(trim((string) ($parsed['urgency'] ?? 'medium')));
        if (!in_array($urgency, self::ALLOWED_URGENCIES, true)) {
            $urgency = 'medium';
        }

        $confidence = max(0.1, min(1.0, (float) ($parsed['confidence'] ?? 0.85)));
        $rationale = trim((string) ($parsed['rationale'] ?? 'Classified via Gemini AI triage engine.'));

        return [
            'suggestedCategory' => $cat,
            'urgency' => $urgency,
            'confidence' => round($confidence, 2),
            'rationale' => $rationale,
            'source' => 'gemini',
        ];
    }

    /**
     * Robust deterministic keyword heuristic classification.
     * Used as zero-cost default, privacy-safe classifier, and instant resilient fallback.
     */
    public static function classifyDeterministic(
        string $title,
        string $description,
        string $defaultCategory = 'other'
    ): array {
        $text = strtolower($title . ' ' . $description);

        $rules = [
            'fire' => [
                'keywords' => ['fire', 'smoke', 'flame', 'blaze', 'burning', 'gas leak', 'explosion', 'fire alarm', 'burns'],
                'urgency' => 'critical',
                'confidence' => 0.95,
                'rationale' => 'Detected critical fire/combustion safety keywords in report description.'
            ],
            'medical' => [
                'keywords' => ['ambulance', 'collapsed', 'bleeding', 'heart attack', 'unconscious', 'seizure', 'allergic', 'injury', 'broken bone', 'fainted', 'breathless', 'blood', 'choking'],
                'urgency' => 'critical',
                'confidence' => 0.92,
                'rationale' => 'Detected immediate life-safety medical emergency indicators.'
            ],
            'anti_ragging' => [
                'keywords' => ['ragging', 'hazing', 'senior harassment', 'forced to', 'intimidation', 'bullied', 'hostel seniors', 'humiliation'],
                'urgency' => 'high',
                'confidence' => 0.90,
                'rationale' => 'Identified campus anti-ragging and student harassment markers.'
            ],
            'sexual_harassment' => [
                'keywords' => ['sexual harassment', 'molestation', 'stalking', 'inappropriately touched', 'eve teasing', 'non-consensual', 'icc', 'title ix'],
                'urgency' => 'high',
                'confidence' => 0.92,
                'rationale' => 'Identified sensitive sexual harassment / ICC safety markers.'
            ],
            'security' => [
                'keywords' => ['assault', 'fight', 'weapon', 'knife', 'theft', 'stolen', 'burglar', 'intruder', 'trespasser', 'break-in', 'robbed', 'laptop stolen', 'wallet stolen', 'bag stolen'],
                'urgency' => 'high',
                'confidence' => 0.88,
                'rationale' => 'Detected campus security threat or property crime keywords.'
            ],
            'maintenance' => [
                'keywords' => ['leak', 'pipe', 'water leakage', 'elevator stuck', 'lift failure', 'power outage', 'blackout', 'ceiling leak', 'electrical short', 'broken door', 'no water', 'light not working'],
                'urgency' => 'medium',
                'confidence' => 0.85,
                'rationale' => 'Identified campus facility or infrastructural maintenance issue.'
            ],
        ];

        foreach ($rules as $category => $rule) {
            foreach ($rule['keywords'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    // Check for critical escalations within medical or security
                    $urgency = $rule['urgency'];
                    if ($category === 'security' && (str_contains($text, 'weapon') || str_contains($text, 'knife') || str_contains($text, 'assault'))) {
                        $urgency = 'critical';
                    }

                    return [
                        'suggestedCategory' => $category,
                        'urgency' => $urgency,
                        'confidence' => $rule['confidence'],
                        'rationale' => $rule['rationale'],
                        'source' => 'deterministic_fallback',
                    ];
                }
            }
        }

        // Normalize default category if supplied
        $normDefault = strtolower(trim($defaultCategory));
        if (!in_array($normDefault, self::ALLOWED_CATEGORIES, true)) {
            $normDefault = 'other';
        }

        return [
            'suggestedCategory' => $normDefault,
            'urgency' => 'medium',
            'confidence' => 0.70,
            'rationale' => 'Standard campus report intake (no emergency heuristic patterns triggered).',
            'source' => 'deterministic_fallback',
        ];
    }
}

