<?php
declare(strict_types=1);

namespace CampusSOS\Services;

use CampusSOS\Config\Database;
use CampusSOS\Models\IncidentMatch;
use CampusSOS\Services\LocationService;
use CampusSOS\Services\SosRoutingService;
use PDO;

class IncidentMatcherService
{
    public const THRESHOLD_CANDIDATE = 0.60;
    public const THRESHOLD_HIGH_CONFIDENCE = 0.80;

    public const WEIGHT_CATEGORY = 0.25;
    public const WEIGHT_LOCATION = 0.35;
    public const WEIGHT_TEMPORAL = 0.20;
    public const WEIGHT_LEXICAL = 0.20;

    private const STOP_WORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'is', 'was', 'are', 'were',
        'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'up',
        'about', 'into', 'over', 'after', 'beneath', 'under', 'above',
        'this', 'that', 'these', 'those', 'i', 'my', 'me', 'we', 'our',
        'us', 'you', 'your', 'he', 'she', 'it', 'they', 'them', 'their',
        'there', 'here', 'have', 'has', 'had', 'do', 'does', 'did', 'be',
        'been', 'being', 'not', 'can', 'could', 'should', 'would', 'will',
        'reported', 'incident', 'emergency', 'help', 'need', 'please'
    ];

    /**
     * Pure deterministic similarity calculation between any two records (Incident or SOS).
     * Evaluates Category (0.25), Location (0.35), Temporal (0.20), and Lexical (0.20).
     */
    public static function calculateSimilarity(array $recA, array $recB): array
    {
        // 1. Confidentiality & Sensitive Category Guardrail
        $isConfidentialA = !empty($recA['is_confidential']) || ($recA['privacy_level'] ?? 'standard') !== 'standard';
        $isConfidentialB = !empty($recB['is_confidential']) || ($recB['privacy_level'] ?? 'standard') !== 'standard';

        if ($isConfidentialA || $isConfidentialB) {
            return [
                'score' => 0.0,
                'isCandidate' => false,
                'confidenceLevel' => 'none',
                'rationale' => 'Confidential incident reports are strictly isolated from cross-report automated duplicate matching.',
                'breakdown' => [
                    'category' => 0.0,
                    'location' => 0.0,
                    'temporal' => 0.0,
                    'lexical' => 0.0,
                ],
            ];
        }

        $catA = self::normalizeRecordCategory($recA);
        $catB = self::normalizeRecordCategory($recB);

        $sensitiveCats = ['sexual_harassment', 'anti_ragging'];
        if (in_array($catA, $sensitiveCats, true) || in_array($catB, $sensitiveCats, true)) {
            return [
                'score' => 0.0,
                'isCandidate' => false,
                'confidenceLevel' => 'none',
                'rationale' => 'Sensitive disciplinary/Title IX reports are excluded from public cross-matching.',
                'breakdown' => [
                    'category' => 0.0,
                    'location' => 0.0,
                    'temporal' => 0.0,
                    'lexical' => 0.0,
                ],
            ];
        }

        // 2. Category Similarity (Weight: 0.25)
        $categoryScore = 0.0;
        if (!empty($catA) && $catA === $catB) {
            $categoryScore = self::WEIGHT_CATEGORY; // 0.25
        } elseif ($catA === 'other' || $catB === 'other') {
            $categoryScore = 0.05;
        }

        // 3. Location Similarity & Coordinate Proximity (Weight: 0.35)
        $locationScore = 0.0;
        $locA = trim(strtolower($recA['location_name'] ?? ''));
        $locB = trim(strtolower($recB['location_name'] ?? ''));

        $parsedA = LocationService::parseLocation($recA['location_name'] ?? '');
        $roomA = !empty($recA['room_number']) ? trim((string)$recA['room_number']) : ($parsedA['roomNumber'] ?? null);
        $baseA = $parsedA['baseLocation'] ?? '';

        $parsedB = LocationService::parseLocation($recB['location_name'] ?? '');
        $roomB = !empty($recB['room_number']) ? trim((string)$recB['room_number']) : ($parsedB['roomNumber'] ?? null);
        $baseB = $parsedB['baseLocation'] ?? '';

        $sameBuilding = (!empty($baseA) && !empty($baseB) && strcasecmp($baseA, $baseB) === 0 && LocationService::requiresRoomNumber($baseA));

        if ($sameBuilding) {
            if ($roomA !== null && $roomB !== null) {
                if (strcasecmp($roomA, $roomB) === 0) {
                    $locationScore = self::WEIGHT_LOCATION; // Exact same room: 0.35
                } else {
                    // Same building but different rooms: discount to 0.05
                    $locationScore = 0.05;
                }
            } else {
                $locationScore = 0.20; // Same building, room unspecified
            }
        } else {
            $latA = isset($recA['coordinates_lat']) ? (float) $recA['coordinates_lat'] : null;
            $lngA = isset($recA['coordinates_lng']) ? (float) $recA['coordinates_lng'] : null;
            $latB = isset($recB['coordinates_lat']) ? (float) $recB['coordinates_lat'] : null;
            $lngB = isset($recB['coordinates_lng']) ? (float) $recB['coordinates_lng'] : null;

            if ($latA !== null && $lngA !== null && $latB !== null && $lngB !== null && ($latA !== 0.0 || $lngA !== 0.0)) {
                $distanceMeters = self::calculateHaversineDistance($latA, $lngA, $latB, $lngB);
                if ($distanceMeters <= 50) {
                    $locationScore = self::WEIGHT_LOCATION; // 0.35
                } elseif ($distanceMeters <= 200) {
                    $locationScore = 0.28;
                } elseif ($distanceMeters <= 500) {
                    $locationScore = 0.18;
                } elseif ($distanceMeters <= 1000) {
                    $locationScore = 0.08;
                } else {
                    $locationScore = 0.0;
                }
            } elseif (!empty($locA) && !empty($locB)) {
                if ($locA === $locB) {
                    $locationScore = self::WEIGHT_LOCATION; // 0.35
                } elseif (str_contains($locA, $locB) || str_contains($locB, $locA)) {
                    $locationScore = 0.25;
                }
            }
        }

        // 4. Temporal Proximity (Weight: 0.20)
        $temporalScore = 0.0;
        $timeA = strtotime((string)($recA['occurred_at'] ?? $recA['triggered_at'] ?? $recA['created_at'] ?? 'now'));
        $timeB = strtotime((string)($recB['occurred_at'] ?? $recB['triggered_at'] ?? $recB['created_at'] ?? 'now'));
        $timeDiffSeconds = abs($timeA - $timeB);
        $timeDiffMinutes = $timeDiffSeconds / 60.0;

        if ($timeDiffMinutes <= 30) {
            $temporalScore = self::WEIGHT_TEMPORAL; // 0.20
        } elseif ($timeDiffMinutes <= 60) {
            $temporalScore = 0.16;
        } elseif ($timeDiffMinutes <= 120) {
            $temporalScore = 0.12;
        } elseif ($timeDiffMinutes <= 180) {
            $temporalScore = 0.08;
        } elseif ($timeDiffMinutes <= 360) {
            $temporalScore = 0.04;
        } else {
            $temporalScore = 0.0;
        }

        // 5. Lexical & Description Keyword Overlap (Weight: 0.20)
        $textA = self::extractRecordText($recA);
        $textB = self::extractRecordText($recB);
        $tokensA = self::tokenizeAndFilter($textA);
        $tokensB = self::tokenizeAndFilter($textB);

        $lexicalScore = 0.0;
        $intersection = [];
        if (!empty($tokensA) && !empty($tokensB)) {
            $intersection = array_intersect($tokensA, $tokensB);
            $union = array_unique(array_merge($tokensA, $tokensB));
            $jaccard = count($union) > 0 ? (count($intersection) / count($union)) : 0.0;
            $lexicalScore = round(min(1.0, $jaccard * 1.5) * self::WEIGHT_LEXICAL, 4);
        }

        // Total weighted score
        $totalScore = round($categoryScore + $locationScore + $temporalScore + $lexicalScore, 2);
        $totalScore = min(1.0, max(0.0, $totalScore));

        // Rationale generation
        $reasons = [];
        if ($categoryScore > 0) {
            $reasons[] = "Matching category '{$catA}'";
        }
        if ($locationScore >= 0.25) {
            $reasons[] = "High spatial proximity ('" . ($recA['location_name'] ?? 'campus') . "')";
        } elseif ($locationScore > 0) {
            $reasons[] = "Partial spatial overlap";
        }
        if ($temporalScore >= 0.16) {
            $reasons[] = "Occurred within " . round($timeDiffMinutes) . " mins";
        } elseif ($temporalScore > 0) {
            $reasons[] = "Occurred within " . round($timeDiffMinutes / 60, 1) . " hours";
        }
        if ($lexicalScore > 0.04 && !empty($intersection)) {
            $reasons[] = count($intersection) . " overlapping keywords (" . implode(', ', array_slice($intersection, 0, 3)) . ")";
        }

        $rationale = !empty($reasons)
            ? implode('; ', $reasons) . " (Deterministic score: {$totalScore})"
            : "Low correlation across spatial, temporal, and lexical dimensions ({$totalScore})";

        $confidenceLevel = 'none';
        if ($totalScore >= self::THRESHOLD_HIGH_CONFIDENCE) {
            $confidenceLevel = 'high';
        } elseif ($totalScore >= self::THRESHOLD_CANDIDATE) {
            $confidenceLevel = 'possible';
        }

        return [
            'score' => $totalScore,
            'isCandidate' => ($totalScore >= self::THRESHOLD_CANDIDATE),
            'confidenceLevel' => $confidenceLevel,
            'rationale' => $rationale,
            'breakdown' => [
                'category' => round($categoryScore, 2),
                'location' => round($locationScore, 2),
                'temporal' => round($temporalScore, 2),
                'lexical' => round($lexicalScore, 2),
            ],
        ];
    }

    /**
     * Detect and persist candidate matches for a newly created or updated incident.
     * Evaluates incident vs open incidents, and incident vs open SOS alerts.
     */
    public static function detectAndPersistForIncident(int $incidentId, array $incidentData): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return [];
        }

        // Confidential reports are strictly isolated from automated duplicate matching
        if (!empty($incidentData['is_confidential']) || ($incidentData['privacy_level'] ?? '') === 'confidential') {
            return [];
        }

        $occurredAt = $incidentData['occurred_at'] ?? $incidentData['occurredAt'] ?? date('Y-m-d H:i:s');
        $persisted = [];

        // 1. Compare with other recent, open incidents (within 24 hours)
        $stmtInc = $pdo->prepare("
            SELECT id, incident_number, title, description, location_name, room_number,
                   coordinates_lat, coordinates_lng, category, occurred_at, created_at,
                   is_confidential, privacy_level, status
            FROM incidents
            WHERE id != :id
              AND (merged_into_incident_id IS NULL)
              AND is_confidential = 0
              AND privacy_level = 'standard'
              AND status IN ('reported', 'under_review', 'in_progress', 'dispatched')
              AND occurred_at >= DATE_SUB(:occurred_at, INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 25
        ");
        $stmtInc->execute([
            ':id' => $incidentId,
            ':occurred_at' => $occurredAt,
        ]);
        $incCandidates = $stmtInc->fetchAll();

        foreach ($incCandidates as $cand) {
            $candId = (int) $cand['id'];
            $sim = self::calculateSimilarity($incidentData, $cand);

            if ($sim['isCandidate']) {
                IncidentMatch::createCandidate(
                    'incident', $incidentId,
                    'incident', $candId,
                    $sim['score'],
                    $sim['rationale'],
                    $sim['breakdown']
                );
                $persisted[] = [
                    'targetType' => 'incident',
                    'targetId' => $candId,
                    'targetNumber' => $cand['incident_number'],
                    'similarityScore' => $sim['score'],
                    'rationale' => $sim['rationale'],
                ];
            }
        }

        // 2. Compare with recent, active SOS alerts (within 24 hours)
        $stmtSos = $pdo->prepare("
            SELECT id, alert_code, location_name, room_number, emergency_type,
                   emergency_details, detected_category, assigned_department,
                   triggered_at, created_at, status
            FROM sos_alerts
            WHERE (merged_into_incident_id IS NULL AND merged_into_sos_id IS NULL)
              AND status IN ('triggered', 'acknowledged', 'dispatched')
              AND triggered_at >= DATE_SUB(:occurred_at, INTERVAL 24 HOUR)
            ORDER BY triggered_at DESC
            LIMIT 25
        ");
        $stmtSos->execute([':occurred_at' => $occurredAt]);
        $sosCandidates = $stmtSos->fetchAll();

        foreach ($sosCandidates as $candSos) {
            $sosId = (int) $candSos['id'];
            $sim = self::calculateSimilarity($incidentData, $candSos);

            if ($sim['isCandidate']) {
                IncidentMatch::createCandidate(
                    'incident', $incidentId,
                    'sos', $sosId,
                    $sim['score'],
                    $sim['rationale'],
                    $sim['breakdown']
                );
                $persisted[] = [
                    'targetType' => 'sos',
                    'targetId' => $sosId,
                    'targetNumber' => $candSos['alert_code'],
                    'similarityScore' => $sim['score'],
                    'rationale' => $sim['rationale'],
                ];
            }
        }

        return $persisted;
    }

    /**
     * Detect and persist candidate matches for a newly triggered SOS alert.
     * Evaluates SOS vs active SOS alerts, and SOS vs open incidents.
     * Guaranteed never to throw or delay emergency response.
     */
    public static function detectAndPersistForSos(int $sosId, array $sosData): array
    {
        try {
            $pdo = Database::getConnection();
            if (!$pdo) {
                return [];
            }

            $triggeredAt = $sosData['triggered_at'] ?? date('Y-m-d H:i:s');
            $persisted = [];

            // 1. Compare with other active SOS alerts (excluding self)
            $stmtOtherSos = $pdo->prepare("
                SELECT id, alert_code, location_name, room_number, emergency_type,
                       emergency_details, detected_category, assigned_department,
                       triggered_at, created_at, status
                FROM sos_alerts
                WHERE id != :sos_id
                  AND (merged_into_sos_id IS NULL AND merged_into_incident_id IS NULL)
                  AND status IN ('triggered', 'acknowledged', 'dispatched')
                  AND triggered_at >= DATE_SUB(:triggered_at, INTERVAL 24 HOUR)
                ORDER BY triggered_at DESC
                LIMIT 25
            ");
            $stmtOtherSos->execute([
                ':sos_id' => $sosId,
                ':triggered_at' => $triggeredAt,
            ]);
            $otherSos = $stmtOtherSos->fetchAll();

            foreach ($otherSos as $candSos) {
                $candSosId = (int) $candSos['id'];
                $sim = self::calculateSimilarity($sosData, $candSos);

                if ($sim['isCandidate']) {
                    IncidentMatch::createCandidate(
                        'sos', $sosId,
                        'sos', $candSosId,
                        $sim['score'],
                        $sim['rationale'],
                        $sim['breakdown']
                    );
                    $persisted[] = [
                        'targetType' => 'sos',
                        'targetId' => $candSosId,
                        'targetNumber' => $candSos['alert_code'],
                        'similarityScore' => $sim['score'],
                        'rationale' => $sim['rationale'],
                    ];
                }
            }

            // 2. Compare with open incidents
            $stmtInc = $pdo->prepare("
                SELECT id, incident_number, title, description, location_name, room_number,
                       coordinates_lat, coordinates_lng, category, occurred_at, created_at,
                       is_confidential, privacy_level, status
                FROM incidents
                WHERE (merged_into_incident_id IS NULL)
                  AND is_confidential = 0
                  AND privacy_level = 'standard'
                  AND status IN ('reported', 'under_review', 'in_progress', 'dispatched')
                  AND occurred_at >= DATE_SUB(:triggered_at, INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 25
            ");
            $stmtInc->execute([':triggered_at' => $triggeredAt]);
            $incidents = $stmtInc->fetchAll();

            foreach ($incidents as $candInc) {
                $incId = (int) $candInc['id'];
                $sim = self::calculateSimilarity($sosData, $candInc);

                if ($sim['isCandidate']) {
                    // Normalize: incident is first, sos is second
                    IncidentMatch::createCandidate(
                        'incident', $incId,
                        'sos', $sosId,
                        $sim['score'],
                        $sim['rationale'],
                        $sim['breakdown']
                    );
                    $persisted[] = [
                        'targetType' => 'incident',
                        'targetId' => $incId,
                        'targetNumber' => $candInc['incident_number'],
                        'similarityScore' => $sim['score'],
                        'rationale' => $sim['rationale'],
                    ];
                }
            }

            return $persisted;

        } catch (\Throwable $e) {
            // Safety: Never fail SOS dispatch on background match error
            error_log('[CampusSOS Matcher Warning]: SOS matching error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Backward-compatible wrapper for incident detection.
     */
    public static function detectAndPersistMatches(int $incidentId, array $incidentData): array
    {
        return self::detectAndPersistForIncident($incidentId, $incidentData);
    }

    public static function detectSimilarIncidents(int $incidentId, array $incidentData): array
    {
        return self::detectAndPersistForIncident($incidentId, $incidentData);
    }

    /**
     * Normalize category from incident or SOS alert.
     */
    private static function normalizeRecordCategory(array $rec): string
    {
        if (!empty($rec['category'])) {
            return strtolower(trim((string)$rec['category']));
        }

        // For SOS records: map emergency_type / detected_category
        $emType = (string) ($rec['emergency_type'] ?? ($rec['emergencyType'] ?? ''));
        $detCat = (string) ($rec['detected_category'] ?? ($rec['detectedCategory'] ?? ''));
        $assignedDept = (string) ($rec['assigned_department'] ?? ($rec['assignedDepartment'] ?? ''));

        if (!empty($assignedDept)) {
            return strtolower(trim($assignedDept));
        }

        $canonical = SosRoutingService::canonicalizeCategory($emType ?: $detCat);
        switch ($canonical) {
            case SosRoutingService::CATEGORY_FIRE:
                return 'fire';
            case SosRoutingService::CATEGORY_VEHICLE_COLLISION:
            case SosRoutingService::CATEGORY_INJURY_ACCIDENT:
            case SosRoutingService::CATEGORY_MEDICAL_EMERGENCY:
                return 'medical';
            case SosRoutingService::CATEGORY_ASSAULT_FIGHT:
            case SosRoutingService::CATEGORY_HARASSMENT_THREAT:
            case SosRoutingService::CATEGORY_SUSPICIOUS_ACTIVITY:
            case SosRoutingService::CATEGORY_THEFT_ROBBERY:
                return 'security';
            case SosRoutingService::CATEGORY_ELECTRICAL_HAZARD:
            case SosRoutingService::CATEGORY_GAS_LEAK:
                return 'maintenance';
            default:
                return 'other';
        }
    }

    /**
     * Extract descriptive text from incident or SOS alert for keyword matching.
     */
    private static function extractRecordText(array $rec): string
    {
        $title = (string) ($rec['title'] ?? '');
        $desc = (string) ($rec['description'] ?? '');
        $emType = (string) ($rec['emergency_type'] ?? ($rec['emergencyType'] ?? ''));
        $emDetails = (string) ($rec['emergency_details'] ?? ($rec['emergencyDetails'] ?? ''));

        return trim("{$title} {$desc} {$emType} {$emDetails}");
    }

    /**
     * Tokenize text into unique non-stopword tokens.
     */
    private static function tokenizeAndFilter(string $text): array
    {
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', strtolower($text));
        $words = preg_split('/\s+/', (string) $clean, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return [];
        }

        $filtered = [];
        foreach ($words as $w) {
            if (strlen($w) >= 3 && !in_array($w, self::STOP_WORDS, true)) {
                $filtered[] = $w;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Calculate Haversine distance in meters between two lat/lng coordinates.
     */
    private static function calculateHaversineDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371000; // meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
