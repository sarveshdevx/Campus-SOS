<?php
declare(strict_types=1);

namespace CampusSOS\Services;

use CampusSOS\Config\Database;
use CampusSOS\Models\Responder;

class SosRoutingService
{
    // Canonical Predefined Emergency Categories
    public const CATEGORY_FIRE = 'Fire / Smoke';
    public const CATEGORY_VEHICLE_COLLISION = 'Vehicle Collision';
    public const CATEGORY_INJURY_ACCIDENT = 'Injury / Accident';
    public const CATEGORY_MEDICAL_EMERGENCY = 'Medical Emergency';
    public const CATEGORY_ASSAULT_FIGHT = 'Physical Assault / Fight';
    public const CATEGORY_HARASSMENT_THREAT = 'Harassment / Threat';
    public const CATEGORY_SUSPICIOUS_ACTIVITY = 'Suspicious Activity';
    public const CATEGORY_THEFT_ROBBERY = 'Theft / Robbery';
    public const CATEGORY_ELECTRICAL_HAZARD = 'Electrical Hazard';
    public const CATEGORY_GAS_LEAK = 'Gas Leak';
    public const CATEGORY_OTHER = 'Other Emergency';
    public const CATEGORY_CUSTOM = 'Custom';

    /**
     * Map category to designated responder / department routing rules.
     */
    public static function route(string $emergencyType, ?string $customDescription = null): array
    {
        $normalizedType = trim($emergencyType);

        // Normalize case variations (e.g. "Fire / Smoke", "fire", "Fire/Smoke")
        $canonicalType = self::canonicalizeCategory($normalizedType);

        $detectedCategory = null;
        $classificationSource = 'deterministic';
        $confidence = 1.0;
        $rationale = null;

        // If Custom, attempt AI-assisted classification with deterministic rule fallback
        if ($canonicalType === self::CATEGORY_CUSTOM) {
            $classification = self::classifyCustomEmergency(trim((string) $customDescription));
            $detectedCategory = $classification['detectedCategory'];
            $classificationSource = $classification['source'];
            $confidence = $classification['confidence'];
            $rationale = $classification['rationale'];

            if ($detectedCategory && $detectedCategory !== self::CATEGORY_OTHER && $confidence >= 0.70) {
                // Successfully classified with high confidence
                $canonicalType = $detectedCategory;
            } else {
                // Ambiguous or unclassified -> Route to Admin only for manual triage
                return [
                    'canonicalCategory' => self::CATEGORY_CUSTOM,
                    'detectedCategory' => $detectedCategory ?: self::CATEGORY_OTHER,
                    'assignedResponderId' => null,
                    'assignedResponderName' => null,
                    'assignedDepartment' => null,
                    'triageStatus' => 'awaiting_triage',
                    'routingDecision' => 'Awaiting Admin Triage — Custom emergency requires manual triage.',
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale ?: 'Custom emergency description ambiguous or low confidence.',
                ];
            }
        }

        // Apply authoritative routing mapping for the canonical category
        switch ($canonicalType) {
            case self::CATEGORY_FIRE:
                // Fire Marshal Vikram Singh — Fire Marshal (Responder #3, category 'fire')
                $responder = self::getResponderByEmailOrCategory('fire@campussos.edu', 'fire');
                return [
                    'canonicalCategory' => self::CATEGORY_FIRE,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => $responder['id'] ?? 3,
                    'assignedResponderName' => 'Fire Marshal Vikram Singh — Fire Marshal',
                    'assignedDepartment' => 'fire',
                    'triageStatus' => 'routed',
                    'routingDecision' => 'Routed to Fire Marshal Vikram Singh — Fire Marshal only',
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];

            case self::CATEGORY_VEHICLE_COLLISION:
                // Medical Response only
                $responder = self::getResponderByEmailOrCategory('medical@campussos.edu', 'medical');
                return [
                    'canonicalCategory' => self::CATEGORY_VEHICLE_COLLISION,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => $responder['id'] ?? 1,
                    'assignedResponderName' => 'Medical Response Unit',
                    'assignedDepartment' => 'medical',
                    'triageStatus' => 'routed',
                    'routingDecision' => 'Routed to Medical Response department only',
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];

            case self::CATEGORY_INJURY_ACCIDENT:
                // Medical Response only
                $responder = self::getResponderByEmailOrCategory('medical@campussos.edu', 'medical');
                return [
                    'canonicalCategory' => self::CATEGORY_INJURY_ACCIDENT,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => $responder['id'] ?? 1,
                    'assignedResponderName' => 'Medical Response Unit',
                    'assignedDepartment' => 'medical',
                    'triageStatus' => 'routed',
                    'routingDecision' => 'Routed to Medical Response department only',
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];

            case self::CATEGORY_MEDICAL_EMERGENCY:
                // Dr. Priya Varma — Chief Medical Officer only
                $responder = self::getResponderByEmailOrCategory('medical@campussos.edu', 'medical');
                return [
                    'canonicalCategory' => self::CATEGORY_MEDICAL_EMERGENCY,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => $responder['id'] ?? 1,
                    'assignedResponderName' => 'Dr. Priya Varma — Chief Medical Officer',
                    'assignedDepartment' => 'medical',
                    'triageStatus' => 'routed',
                    'routingDecision' => 'Routed to Dr. Priya Varma — Chief Medical Officer only',
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];

            case self::CATEGORY_ASSAULT_FIGHT:
            case self::CATEGORY_HARASSMENT_THREAT:
            case self::CATEGORY_SUSPICIOUS_ACTIVITY:
            case self::CATEGORY_THEFT_ROBBERY:
                // Officer Rajesh Kumar — Security Superintendent only
                $responder = self::getResponderByEmailOrCategory('security@campussos.edu', 'security');
                return [
                    'canonicalCategory' => $canonicalType,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => $responder['id'] ?? 2,
                    'assignedResponderName' => 'Officer Rajesh Kumar — Security Superintendent',
                    'assignedDepartment' => 'security',
                    'triageStatus' => 'routed',
                    'routingDecision' => "Routed to Officer Rajesh Kumar — Security Superintendent only ({$canonicalType})",
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];

            case self::CATEGORY_ELECTRICAL_HAZARD:
            case self::CATEGORY_GAS_LEAK:
                // Anil Desai — Chief Works Engineer, Facility Maintenance only
                $responder = self::getResponderByEmailOrCategory('maintenance@campussos.edu', 'maintenance');
                return [
                    'canonicalCategory' => $canonicalType,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => $responder['id'] ?? 4,
                    'assignedResponderName' => 'Anil Desai — Chief Works Engineer, Facility Maintenance',
                    'assignedDepartment' => 'maintenance',
                    'triageStatus' => 'routed',
                    'routingDecision' => "Routed to Anil Desai — Chief Works Engineer, Facility Maintenance only ({$canonicalType})",
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];

            case self::CATEGORY_OTHER:
            default:
                // Admin only — Awaiting Admin Triage
                return [
                    'canonicalCategory' => self::CATEGORY_OTHER,
                    'detectedCategory' => $detectedCategory,
                    'assignedResponderId' => null,
                    'assignedResponderName' => null,
                    'assignedDepartment' => null,
                    'triageStatus' => 'awaiting_triage',
                    'routingDecision' => 'Awaiting Admin Triage — Unassigned category reserved for Admin review.',
                    'classificationSource' => $classificationSource,
                    'confidence' => $confidence,
                    'rationale' => $rationale,
                ];
        }
    }

    /**
     * Check if a responder is authorized to view or manage a specific SOS alert.
     */
    public static function isResponderAuthorized(array $responder, array $alert): bool
    {
        // Alerts awaiting triage are strictly Admin-only
        if (($alert['triage_status'] ?? '') === 'awaiting_triage') {
            return false;
        }

        $responderId = (int) ($responder['id'] ?? 0);
        $responderCategory = strtolower((string) ($responder['category'] ?? ''));

        // Direct responder assignment match
        if (!empty($alert['assigned_responder_id']) && (int) $alert['assigned_responder_id'] === $responderId) {
            return true;
        }

        // Direct department assignment match
        $assignedDept = strtolower((string) ($alert['assigned_department'] ?? ''));
        if (!empty($assignedDept) && $assignedDept === $responderCategory) {
            return true;
        }

        // If alert is linked to an incident, check the incident's category
        $incidentId = (int) ($alert['incident_id'] ?? ($alert['incidentId'] ?? 0));
        if ($incidentId > 0) {
            $pdo = Database::getConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT category FROM incidents WHERE id = :inc_id");
                $stmt->execute([':inc_id' => $incidentId]);
                $incCat = strtolower((string) $stmt->fetchColumn());
                if (!empty($incCat) && $incCat === $responderCategory) {
                    return true;
                }
            }
        }

        // Category authorization rules
        $category = self::canonicalizeCategory((string) ($alert['emergency_type'] ?? ($alert['emergencyType'] ?? '')));
        $detectedCat = (string) ($alert['detected_category'] ?? ($alert['detectedCategory'] ?? ''));
        if (!empty($detectedCat)) {
            $detectedCanonical = self::canonicalizeCategory($detectedCat);
            if ($detectedCanonical !== self::CATEGORY_OTHER) {
                $category = $detectedCanonical;
            }
        }

        if ($responderCategory === 'fire') {
            return $category === self::CATEGORY_FIRE;
        }

        if ($responderCategory === 'maintenance') {
            return in_array($category, [self::CATEGORY_ELECTRICAL_HAZARD, self::CATEGORY_GAS_LEAK], true);
        }

        if ($responderCategory === 'security') {
            return in_array($category, [
                self::CATEGORY_ASSAULT_FIGHT,
                self::CATEGORY_HARASSMENT_THREAT,
                self::CATEGORY_SUSPICIOUS_ACTIVITY,
                self::CATEGORY_THEFT_ROBBERY,
            ], true);
        }

        if ($responderCategory === 'medical') {
            return in_array($category, [
                self::CATEGORY_VEHICLE_COLLISION,
                self::CATEGORY_INJURY_ACCIDENT,
                self::CATEGORY_MEDICAL_EMERGENCY,
            ], true);
        }

        return false;
    }

    /**
     * Normalize category strings into canonical constant values.
     */
    public static function canonicalizeCategory(string $category): string
    {
        $c = strtolower(trim($category));

        if (strpos($c, 'fire') !== false || strpos($c, 'smoke') !== false) {
            return self::CATEGORY_FIRE;
        }
        if (strpos($c, 'vehicle') !== false || strpos($c, 'collision') !== false) {
            return self::CATEGORY_VEHICLE_COLLISION;
        }
        if (strpos($c, 'injury') !== false || strpos($c, 'accident') !== false) {
            return self::CATEGORY_INJURY_ACCIDENT;
        }
        if (strpos($c, 'medical') !== false) {
            return self::CATEGORY_MEDICAL_EMERGENCY;
        }
        if (strpos($c, 'assault') !== false || strpos($c, 'fight') !== false) {
            return self::CATEGORY_ASSAULT_FIGHT;
        }
        if (strpos($c, 'harass') !== false || strpos($c, 'threat') !== false) {
            return self::CATEGORY_HARASSMENT_THREAT;
        }
        if (strpos($c, 'suspicious') !== false) {
            return self::CATEGORY_SUSPICIOUS_ACTIVITY;
        }
        if (strpos($c, 'theft') !== false || strpos($c, 'robbery') !== false || strpos($c, 'stolen') !== false) {
            return self::CATEGORY_THEFT_ROBBERY;
        }
        if (strpos($c, 'electrical') !== false) {
            return self::CATEGORY_ELECTRICAL_HAZARD;
        }
        if (strpos($c, 'gas') !== false) {
            return self::CATEGORY_GAS_LEAK;
        }
        if ($c === 'custom') {
            return self::CATEGORY_CUSTOM;
        }
        if (strpos($c, 'other') !== false) {
            return self::CATEGORY_OTHER;
        }

        return $category ?: self::CATEGORY_OTHER;
    }

    /**
     * AI-assisted Custom emergency classifier with deterministic keyword fallback.
     */
    public static function classifyCustomEmergency(string $description): array
    {
        if (empty($description)) {
            return [
                'detectedCategory' => self::CATEGORY_OTHER,
                'confidence' => 0.0,
                'source' => 'deterministic',
                'rationale' => 'Empty custom description provided.',
            ];
        }

        // Try Gemini if enabled
        if (GeminiClassificationService::isGeminiEnabled()) {
            try {
                $geminiResult = self::callGeminiForSos($description);
                if ($geminiResult !== null && !empty($geminiResult['detectedCategory'])) {
                    return $geminiResult;
                }
            } catch (\Throwable $e) {
                error_log('[CampusSOS SOS Gemini Warning]: ' . $e->getMessage() . '. Falling back to deterministic rules.');
            }
        }

        // Deterministic keyword classifier
        return self::classifyDeterministicSos($description);
    }

    /**
     * Call Gemini API to classify custom emergency text.
     */
    private static function callGeminiForSos(string $description): ?array
    {
        $apiKey = GeminiClassificationService::getApiKey();
        $model = GeminiClassificationService::getModel();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $allowedCategories = [
            self::CATEGORY_FIRE,
            self::CATEGORY_VEHICLE_COLLISION,
            self::CATEGORY_INJURY_ACCIDENT,
            self::CATEGORY_MEDICAL_EMERGENCY,
            self::CATEGORY_ASSAULT_FIGHT,
            self::CATEGORY_HARASSMENT_THREAT,
            self::CATEGORY_SUSPICIOUS_ACTIVITY,
            self::CATEGORY_THEFT_ROBBERY,
            self::CATEGORY_ELECTRICAL_HAZARD,
            self::CATEGORY_GAS_LEAK,
            self::CATEGORY_OTHER,
        ];

        $prompt = "You are an urgent campus emergency dispatcher triage assistant. "
            . "Classify this emergency description into EXACTLY one of these categories: "
            . implode(', ', $allowedCategories) . ". "
            . "If the description is ambiguous, unclear, or lacks emergency context, classify as \"Other Emergency\". "
            . "Output JSON with EXACTLY these fields: "
            . "\"category\" (string, matching one of allowed), "
            . "\"confidence\" (float 0.0 to 1.0), "
            . "\"rationale\" (short one-sentence explanation).";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt . "\n\nEmergency Description:\n" . mb_substr($description, 0, 400)]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1,
                'maxOutputTokens' => 150,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,        // Strict 3.0s timeout to never stall emergency
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        $rawJson = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$rawJson) return null;

        $parsed = json_decode($rawJson, true);
        if (!is_array($parsed)) return null;

        $cat = self::canonicalizeCategory((string) ($parsed['category'] ?? ''));
        $confidence = max(0.1, min(1.0, (float) ($parsed['confidence'] ?? 0.85)));
        $rationale = trim((string) ($parsed['rationale'] ?? 'Classified via Gemini AI triage engine.'));

        return [
            'detectedCategory' => $cat,
            'confidence' => round($confidence, 2),
            'source' => 'gemini',
            'rationale' => $rationale,
        ];
    }

    /**
     * Deterministic keyword classifier for custom emergency descriptions.
     */
    public static function classifyDeterministicSos(string $description): array
    {
        $text = strtolower(' ' . trim($description) . ' ');

        // Fire / Smoke
        if (preg_match('/\b(fire|smoke|burning|flame|blaze|fire alarm|combustion)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_FIRE,
                'confidence' => 0.95,
                'source' => 'deterministic',
                'rationale' => 'Detected critical fire/combustion keywords in custom description.',
            ];
        }

        // Vehicle Collision
        if (preg_match('/\b(car crash|bike crash|vehicle collision|vehicle crash|car accident|hit by car|hit by vehicle|bike collision|scooter crash|collision)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_VEHICLE_COLLISION,
                'confidence' => 0.95,
                'source' => 'deterministic',
                'rationale' => 'Detected campus vehicular collision keywords in custom description.',
            ];
        }

        // Medical Emergency (Acute life-threatening)
        if (preg_match('/\b(heart attack|unconscious|cardiac|seizure|fainted|choking|stroke|breathless|not breathing|chest pain|collapsed)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_MEDICAL_EMERGENCY,
                'confidence' => 0.95,
                'source' => 'deterministic',
                'rationale' => 'Detected acute life-safety medical emergency indicators.',
            ];
        }

        // Injury / Accident (Physical trauma)
        if (preg_match('/\b(injury|fracture|broken bone|bleeding|deep cut|fell down|sprain|head injury|wound|severely hurt|blood)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_INJURY_ACCIDENT,
                'confidence' => 0.92,
                'source' => 'deterministic',
                'rationale' => 'Detected physical trauma or non-cardiac injury keywords.',
            ];
        }

        // Physical Assault / Fight
        if (preg_match('/\b(assault|fight|physical assault|fist fight|beaten up|punched|violence|brawl|attacked|attacking)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_ASSAULT_FIGHT,
                'confidence' => 0.92,
                'source' => 'deterministic',
                'rationale' => 'Detected physical assault or violent altercation keywords.',
            ];
        }

        // Harassment / Threat
        if (preg_match('/\b(harassment|threat|threatening|stalking|stalker|intimidation|blackmail|threatened)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_HARASSMENT_THREAT,
                'confidence' => 0.92,
                'source' => 'deterministic',
                'rationale' => 'Detected personal safety harassment or threat indicators.',
            ];
        }

        // Theft / Robbery
        if (preg_match('/\b(theft|robbery|stolen|thief|stealing|robbed|burglar|pickpocket|snatched)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_THEFT_ROBBERY,
                'confidence' => 0.92,
                'source' => 'deterministic',
                'rationale' => 'Detected property theft or active robbery keywords.',
            ];
        }

        // Suspicious Activity
        if (preg_match('/\b(suspicious|prowler|lurking|trespassing|intruder|tampering|suspicious person)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_SUSPICIOUS_ACTIVITY,
                'confidence' => 0.90,
                'source' => 'deterministic',
                'rationale' => 'Detected security trespass or suspicious activity markers.',
            ];
        }

        // Electrical Hazard
        if (preg_match('/\b(electrical|short circuit|electric shock|exposed wire|sparking|live wire|power surge|electrical fire)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_ELECTRICAL_HAZARD,
                'confidence' => 0.95,
                'source' => 'deterministic',
                'rationale' => 'Detected electrical hazard or short circuit keywords.',
            ];
        }

        // Gas Leak
        if (preg_match('/\b(gas leak|lpg|gas cylinder|gas smell|smell of gas|leaking cylinder)\b/', $text)) {
            return [
                'detectedCategory' => self::CATEGORY_GAS_LEAK,
                'confidence' => 0.95,
                'source' => 'deterministic',
                'rationale' => 'Detected combustible gas leak keywords.',
            ];
        }

        // Ambiguous / No confident match -> Other Emergency
        return [
            'detectedCategory' => self::CATEGORY_OTHER,
            'confidence' => 0.40,
            'source' => 'deterministic',
            'rationale' => 'Custom description is ambiguous or does not match predefined emergency categories.',
        ];
    }

    /**
     * Helper to lookup responder by email or category.
     */
    private static function getResponderByEmailOrCategory(string $email, string $category): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        $stmt = $pdo->prepare("
            SELECT r.id, r.user_id, r.category, r.department, u.full_name, u.email
            FROM responders r
            JOIN users u ON r.user_id = u.id
            WHERE u.email = :email OR r.category = :category
            LIMIT 1
        ");
        $stmt->execute([':email' => $email, ':category' => $category]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
