<?php
namespace App\Exceptions;

class LineException extends BaseException
{
    const ERROR_CODES = [
        // API Call Errors (200-219)
        200 => ['message' => 'Failed to connect to Line API', 'http_code' => 503],
        201 => ['message' => 'Invalid API response format', 'http_code' => 502],

        // Token Validation Errors (220-239)
        220 => ['message' => 'Access token verification failed', 'http_code' => 401],
        221 => ['message' => 'Access token has expired', 'http_code' => 401],

        // User Profile Errors (240-259)
        240 => ['message' => 'Failed to retrieve user profile', 'http_code' => 500],
        241 => ['message' => 'User profile not found', 'http_code' => 404],
    ];

    public function __construct($code = 400, $details = [], \Exception $previous = null)
    {
        $error = self::ERROR_CODES[$code] ?? ['message' => 'Line API error', 'http_code' => 500];
        parent::__construct($error['message'], $code, $details, $error['http_code'], $previous);
    }
}
