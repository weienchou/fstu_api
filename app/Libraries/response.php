<?php
namespace App\Libraries;

use Sabre\HTTP\Response as SResponse;
use Sabre\HTTP\Sapi;

class Response
{
    private $response;

    public function __construct()
    {
        $this->response = new SResponse();
    }

    public function success(array $data = [])
    {
        $payload = ['code' => 1];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        $this->response->setStatus(200);
        $this->response->setHeader('Content-type', 'application/json');
        $this->response->setBody(json_encode($payload));
        return $this;
    }

    public function error(int $code, string $message, array $data = [], int $http_code = 500)
    {
        $payload = ['code' => $code, 'message' => $message];

        if (!empty($data)) {
            $payload['data'] = $data;
        }

        $this->response->setStatus($http_code);
        $this->response->setHeader('Content-type', 'application/json');
        $this->response->setBody(json_encode($payload));
        return $this;
    }

    public function send()
    {
        Sapi::sendResponse($this->response);
    }
}
