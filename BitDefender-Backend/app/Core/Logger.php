<?php

namespace App\Core;

use App\Config\Environment;

class Logger
{
    private static $logFile;

    public static function init()
    {
        self::$logFile = Environment::get('LOG_FILE', __DIR__ . '/../../logs/app.log');
        
        // Cria o diretório de logs se não existir
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    public static function info($message, array $context = [])
    {
        self::log('INFO', $message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug($message, array $context = [])
    {
        if (Environment::get('APP_DEBUG', false)) {
            self::log('DEBUG', $message, $context);
        }
    }

    private static function log($level, $message, array $context = [])
    {
        if (!self::$logFile) {
            self::init();
        }

        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$date] [$level] $message$contextStr" . PHP_EOL;

        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
}