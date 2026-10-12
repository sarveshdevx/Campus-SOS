<?php
declare(strict_types=1);

namespace CampusSOS\Config;

class App
{
    public const NAME = 'CampusSOS';
    public const VERSION = '1.0.0';
    public const API_PREFIX = '/api';

    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            // Fallback to system temp directory if default session save path is non-writable
            $savePath = session_save_path();
            if (empty($savePath) || !is_dir($savePath) || !is_writable($savePath)) {
                @session_save_path(sys_get_temp_dir());
            }

            if (PHP_SAPI !== 'cli') {
                session_set_cookie_params([
                    'lifetime' => 86400, // 24 hours
                    'path' => '/',
                    'domain' => '',
                    'secure' => false,
                    'httponly' => true, // Prevents XSS script access
                    'samesite' => 'Lax', // Protects against cross-site request forgery
                ]);
            }

            @session_start();
        }
    }
}
