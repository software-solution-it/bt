<?php

namespace App\Core;

class Logger
{
    private static $logFile;
    private static $initialized = false;

    private static function initialize()
    {
        if (self::$initialized) return;

        // Usar caminho absoluto para o diretório de logs
        $logDir = dirname(dirname(__DIR__)) . '/logs';
        self::$logFile = $logDir . '/app.log';

        // Criar diretório se não existir
        if (!is_dir($logDir)) {
            $oldmask = umask(0);
            mkdir($logDir, 0755, true);
            umask($oldmask);
        }

        // Verificar permissões
        if (!is_writable($logDir)) {
            error_log("Log directory is not writable: " . $logDir);
        }

        self::$initialized = true;
    }

    public static function debug($message, array $context = [])
    {
        self::log('DEBUG', $message, $context);
    }

    public static function info($message, array $context = [])
    {
        self::log('INFO', $message, $context);
    }

    public static function error($message, array $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    private static function log($level, $message, array $context = [])
    {
        self::initialize();

        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message $contextString\n";

        if (!@file_put_contents(self::$logFile, $logMessage, FILE_APPEND)) {
            error_log("Failed to write to log file: " . self::$logFile);
            error_log($logMessage); // Fallback para error_log do PHP
        }
    }
}