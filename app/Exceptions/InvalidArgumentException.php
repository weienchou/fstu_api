<?php
namespace App\Exceptions;

class InvalidArgumentException extends BaseException
{
    const ERROR_CODES = [
        // General Validation Errors (400-419)
        400 => ['message' => 'Invalid request parameter', 'http_code' => 400],
        401 => ['message' => 'Parameter type error', 'http_code' => 400],
        402 => ['message' => 'Required parameter missing', 'http_code' => 400],
        403 => ['message' => 'Parameter format error', 'http_code' => 400],
        404 => ['message' => 'Parameter out of range', 'http_code' => 400],
        405 => ['message' => 'Parameter contains invalid characters', 'http_code' => 400],
        406 => ['message' => 'Parameter length error', 'http_code' => 400],

        // Logical Parameter Errors (420-429)
        420 => ['message' => 'Parameter logic conflict', 'http_code' => 400],
        421 => ['message' => 'Dependent parameter missing', 'http_code' => 400],
        422 => ['message' => 'Invalid time range', 'http_code' => 400],

        // Data Format Errors (430-439)
        430 => ['message' => 'Invalid data format', 'http_code' => 400],
        431 => ['message' => 'JSON format error', 'http_code' => 400],
        432 => ['message' => 'XML format error', 'http_code' => 400],
        433 => ['message' => 'Date format error', 'http_code' => 400],

        // Validator Related Errors (440-449)
        440 => ['message' => 'Validator not found', 'http_code' => 500],
        441 => ['message' => 'Invalid validation rule', 'http_code' => 500],
        442 => ['message' => 'Validation process failed', 'http_code' => 500],

        // File Errors (450-459)
        450 => ['message' => 'File size exceeds limit', 'http_code' => 400],
        451 => ['message' => 'Unsupported file type', 'http_code' => 400],
        452 => ['message' => 'File upload failed', 'http_code' => 400],
    ];

    public function __construct($code = 400, $details = [], \Exception $previous = null)
    {
        $error = self::ERROR_CODES[$code] ?? ['message' => 'Redis operation failed', 'http_code' => 500];
        parent::__construct($error['message'], $code, $details, $error['http_code'], $previous);
    }
}
