<?php
namespace App\Exceptions;

class RedisException extends BaseException
{
    const ERROR_CODES = [
        // Connection Errors (300-319)
        300 => ['message' => 'Redis connection failed', 'http_code' => 500],
        301 => ['message' => 'Redis authentication failed', 'http_code' => 401],
        302 => ['message' => 'Redis server unavailable', 'http_code' => 503],
        303 => ['message' => 'Redis connection timeout', 'http_code' => 504],
        304 => ['message' => 'Redis connection lost', 'http_code' => 500],

        // Operation Errors (320-339)
        320 => ['message' => 'Key does not exist', 'http_code' => 404],
        321 => ['message' => 'Invalid value type', 'http_code' => 400],
        322 => ['message' => 'Invalid parameters', 'http_code' => 400],
        323 => ['message' => 'Write operation failed', 'http_code' => 500],
        324 => ['message' => 'Read operation failed', 'http_code' => 500],

        // Special Operation Errors (340-359)
        340 => ['message' => 'Failed to set expiration', 'http_code' => 500],
        341 => ['message' => 'Failed to delete key', 'http_code' => 500],
        342 => ['message' => 'Failed to increment value', 'http_code' => 500],
        343 => ['message' => 'Failed to decrement value', 'http_code' => 500],
        344 => ['message' => 'Hash operation failed', 'http_code' => 500],
        345 => ['message' => 'List operation failed', 'http_code' => 500],
        346 => ['message' => 'Set operation failed', 'http_code' => 500],
    ];

    public function __construct($code = 300, $details = [], \Exception $previous = null)
    {
        $error = self::ERROR_CODES[$code] ?? ['message' => 'Redis operation failed', 'http_code' => 500];
        parent::__construct($error['message'], $code, $details, $error['http_code'], $previous);
    }
}
