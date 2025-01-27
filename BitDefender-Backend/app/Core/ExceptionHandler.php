<?php

namespace App\Core;

class ExceptionHandler
{
    public static function register()
    {
        set_exception_handler(function ($exception) {
            $errorMessage = "Exceção não capturada: " . $exception->getMessage() .
                           " em " . $exception->getFile() .
                           " na linha " . $exception->getLine();

            error_log($errorMessage);

            echo json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => $exception->getCode() ?: -32603,
                    'message' => $exception->getMessage()
                ]
            ]);

            exit(1);
        });
    }
}