<?php
namespace App\Exceptions;

class DpopException extends BaseException
{
    const ERROR_CODES = [
        // System and Initialization Errors (100-119)
        100 => ['message' => 'Required key files are missing', 'http_code' => 500],
        101 => ['message' => 'Missing DPoP proof', 'http_code' => 401],

        // DPoP Basic Validation Errors (120-139)
        120 => ['message' => 'DPoP verification failed', 'http_code' => 401],

        // Proof Related Errors (140-159)
        140 => ['message' => 'Invalid DPoP proof format', 'http_code' => 401],
        141 => ['message' => 'Invalid token parts in DPoP proof', 'http_code' => 401],
        142 => ['message' => 'Missing JWK in header', 'http_code' => 401],
        143 => ['message' => 'Invalid payload structure', 'http_code' => 401],
        144 => ['message' => 'DPoP proof verification failed', 'http_code' => 401],
        145 => ['message' => 'Missing required claims in proof', 'http_code' => 401],
        146 => ['message' => 'HTTP method mismatch in proof', 'http_code' => 401],
        147 => ['message' => 'URI mismatch in proof', 'http_code' => 401],
        148 => ['message' => 'Proof timestamp is outdated', 'http_code' => 401],
        149 => ['message' => 'DPoP proof has expired', 'http_code' => 401],

        // Signature and Encryption Security Errors (160-179)
        160 => ['message' => 'Invalid JWK format detected', 'http_code' => 400],
        161 => ['message' => 'Signature verification failed', 'http_code' => 401],
        162 => ['message' => 'Invalid public key PEM format', 'http_code' => 400],
        163 => ['message' => 'Invalid signature length detected', 'http_code' => 400],
        164 => ['message' => 'Signature validation unsuccessful', 'http_code' => 401],
        165 => ['message' => 'Signature processing error occurred', 'http_code' => 500],

        // Token Related Errors (180-199)
        180 => ['message' => 'Invalid access token provided', 'http_code' => 401],
        181 => ['message' => 'Token not bound to DPoP key', 'http_code' => 401],
        182 => ['message' => 'Token binding verification failed', 'http_code' => 500],
        183 => ['message' => 'Failed to create access token', 'http_code' => 500],
        184 => ['message' => 'Access token validation failed', 'http_code' => 401],
        185 => ['message' => 'Failed to extract JWK from proof', 'http_code' => 400],
    ];

    public function __construct($code = 100, $details = [], \Exception $previous = null)
    {
        $error = self::ERROR_CODES[$code] ?? ['message' => 'Authentication error', 'http_code' => 403];
        parent::__construct($error['message'], $code, $details, $error['http_code'], $previous);
    }
}
