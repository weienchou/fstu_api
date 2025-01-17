<?php
namespace App\Libraries;

use Sabre\HTTP;

// use Sabre\HTTP\Sapi;

class Request extends HTTP\RequestDecorator
{
    protected $request = null;

    public function __construct()
    {
        $this->request = HTTP\Sapi::getRequest();
    }

    public function json()
    {
        $json = $this->request->getBodyAsString();
        $payload = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $payload = [];
        }

        return $payload;
    }
}
