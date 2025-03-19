<?php
namespace App\Exceptions;

class ValidationException extends BaseException
{
    const ERROR_CODES = [
        500 => ['message' => 'Validation failed', 'http_code' => 422],
    ];

    public function __construct($code = 500, $details = [], \Exception $previous = null)
    {
        $error = self::ERROR_CODES[$code] ?? ['message' => 'Line API error', 'http_code' => 500];
        parent::__construct($error['message'], $code, $details, $error['http_code'], $previous);
    }
}
