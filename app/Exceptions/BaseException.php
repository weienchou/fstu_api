<?php
namespace App\Exceptions;

class BaseException extends \Exception
{
    protected $error_code;
    protected $error_details;
    protected $http_code;

    public function __construct($message = '', $code = 0, $details = [], $http_code = 500, Exception $previous = null)
    {
        $this->error_code = $code;
        $this->error_details = $details;
        $this->http_code = $http_code;
        parent::__construct($message, $code, $previous);
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }

    public function getErrorResponse()
    {
        return [
            'status' => 'error',
            'code' => $this->error_code,
            'message' => $this->getMessage(),
            'details' => $this->error_details,
        ];
    }

}
