<?php
declare(strict_types=1);

namespace CampusSOS\Services;

class ResponseService
{
    public static function json(
        mixed $data = null,
        int $statusCode = 200,
        ?string $message = null,
        ?array $meta = null
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'data' => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        $response['meta'] = array_merge([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $meta ?? []);

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit(0);
    }

    public static function error(
        string $message,
        int $statusCode = 400,
        ?array $errors = null,
        ?string $errorCode = null
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'success' => false,
            'message' => $message,
            'meta' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($errorCode !== null) {
            $response['errorCode'] = $errorCode;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit(0);
    }
}

