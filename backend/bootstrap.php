<?php
declare(strict_types=1);

// Register PSR-4 Autoloader for CampusSOS namespace
spl_autoload_register(function (string $class) {
    $prefix = 'CampusSOS\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load .env file if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $trimmedKey = trim($key);
            $trimmedValue = trim($value);
            putenv($trimmedKey . '=' . $trimmedValue);
            $_ENV[$trimmedKey] = $trimmedValue;
            $_SERVER[$trimmedKey] = $trimmedValue;
        }
    }
}

// Global Exception Handler: Sanitize errors to avoid leaking stack traces or credentials
set_exception_handler(function (Throwable $e) {
    error_log('[CampusSOS Unhandled Exception]: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    \CampusSOS\Services\ResponseService::error(
        'An internal server error occurred. Please contact system support.',
        500,
        null,
        'INTERNAL_SERVER_ERROR'
    );
});

// Global Error Handler
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Initialize CORS
\CampusSOS\Config\Cors::handle();

// Initialize Session
\CampusSOS\Config\App::initSession();

// Enforce CSRF token validation on state-changing requests (POST, PUT, PATCH, DELETE)
\CampusSOS\Middleware\CsrfMiddleware::validate();
