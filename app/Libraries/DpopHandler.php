<?php
namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class DpopHandler
{
    private $private_key;
    private $public_key;
    private $jti_cache;
    private $algorithm = 'ES256';

    const EXPIRE_TIME = 60 * 60; // 1hr

    public function __construct(string $private_key_path, string $public_key_path)
    {
        $this->initializeKeys($private_key_path, $public_key_path);
    }

    private function initializeKeys(string $private_key_path, string $public_key_path): void
    {
        if (!file_exists($private_key_path) || !file_exists($public_key_path)) {
            throw new \App\Exceptions\DpopException(100);
        }

        $this->private_key = file_get_contents($private_key_path);
        $this->public_key = file_get_contents($public_key_path);
    }

    public function verifyRequestDpop(string $dpop, string $method, string $requestUri)
    {
        if (!$this->verifyDpopProof($dpop, $method, $requestUri)) {
            throw new \App\Exceptions\DpopException(120);
        }

        return true;
    }

    public function validateAccessToken(string $token, string $dpopProof)
    {
        $payload = $this->verifyAccessToken($token);
        if (!$payload) {
            throw new \App\Exceptions\DpopException(180);
        }

        try {
            $jwk = $this->extractJwkFromProof($dpopProof);
            if (!$jwk) {
                throw new \App\Exceptions\DpopException(140);
            }

            $thumbprint = $this->base64url_encode(hash('sha256', json_encode($jwk), true));

            if (!isset($payload->cnf) || !isset($payload->cnf->jkt) ||
                $payload->cnf->jkt !== $thumbprint) {
                throw new \App\Exceptions\DpopException(181);
            }

            return $payload;

        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\DpopException) {
                throw $e;
            }
            throw new \App\Exceptions\DpopException(182);
        }
    }

    public function verifyDpopProof(string $proof, string $method, string $uri): bool
    {
        try {
            $tokenParts = explode('.', $proof);
            if (count($tokenParts) != 3) {
                throw new \App\Exceptions\DpopException(141);
            }

            $headerJson = base64_decode(strtr($tokenParts[0], '-_', '+/') . str_repeat('=', (4 - (strlen($tokenParts[0]) % 4)) % 4));
            $header = json_decode($headerJson);

            if (!$header || !isset($header->jwk)) {
                throw new \App\Exceptions\DpopException(142);
            }

            $payloadJson = base64_decode(strtr($tokenParts[1], '-_', '+/') . str_repeat('=', (4 - (strlen($tokenParts[1]) % 4)) % 4));
            $payload = json_decode($payloadJson);

            if (!$this->validateProofPayload($payload, $method, $uri)) {
                throw new \App\Exceptions\DpopException(143);
            }

            $jwk = $header->jwk;
            $pem = $this->createPublicKeyPem($jwk);

            if (!$this->verifySignature($pem, $tokenParts[0] . '.' . $tokenParts[1], $tokenParts[2])) {
                throw new \App\Exceptions\DpopException(161);
            }

            return true;

        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\DpopException) {
                throw $e;
            }
            throw new \App\Exceptions\DpopException(144);
        }
    }

    private function validateProofPayload($payload, string $method, string $uri): bool
    {
        if (!isset($payload->htm) || !isset($payload->htu) || !isset($payload->jti) ||
            !isset($payload->iat) || !isset($payload->exp)) {
            throw new \App\Exceptions\DpopException(145);
        }

        if ($payload->htm !== $method) {
            throw new \App\Exceptions\DpopException(146);
        }

        if ($payload->htu !== $uri) {
            throw new \App\Exceptions\DpopException(147);
        }

        $currentTime = time();
        if (abs($currentTime - $payload->iat) > 60) {
            throw new \App\Exceptions\DpopException(148);
        }

        if ($currentTime > $payload->exp) {
            throw new \App\Exceptions\DpopException(149);
        }

        return true;
    }

    private function createPublicKeyPem($jwk): string
    {
        if (!isset($jwk->kty) || $jwk->kty !== 'EC' ||
            !isset($jwk->crv) || $jwk->crv !== 'P-256' ||
            !isset($jwk->x) || !isset($jwk->y)) {
            throw new \App\Exceptions\DpopException(160);
        }

        $x = base64_decode(strtr($jwk->x, '-_', '+/') . str_repeat('=', (4 - (strlen($jwk->x) % 4)) % 4));
        $y = base64_decode(strtr($jwk->y, '-_', '+/') . str_repeat('=', (4 - (strlen($jwk->y) % 4)) % 4));

        $der = "\x30\x59"
            . "\x30\x13"
            . "\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01"
            . "\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07"
            . "\x03\x42"
            . "\x00"
            . "\x04"
            . $x . $y;

        return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function verifySignature(string $publicKeyPem, string $data, string $signature): bool
    {
        try {
            $key = openssl_pkey_get_public($publicKeyPem);
            if ($key === false) {
                throw new \App\Exceptions\DpopException(162);
            }

            $signature = base64_decode(strtr($signature, '-_', '+/') . str_repeat('=', (4 - (strlen($signature) % 4)) % 4));

            if (strlen($signature) != 64) {
                throw new \App\Exceptions\DpopException(163);
            }

            $r = substr($signature, 0, 32);
            $s = substr($signature, 32);

            $derSignature = $this->convertSignatureToDER($r, $s);

            $result = openssl_verify($data, $derSignature, $key, OPENSSL_ALGO_SHA256);

            if ($result !== 1) {
                throw new \App\Exceptions\DpopException(164);
            }

            return true;

        } catch (\Exception $e) {
            if ($e instanceof \App\Exceptions\DpopException) {
                throw $e;
            }
            throw new \App\Exceptions\DpopException(165);
        }
    }

    private function convertSignatureToDER(string $r, string $s): string
    {
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        if (ord($r[0]) > 0x7f) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) > 0x7f) {
            $s = "\x00" . $s;
        }

        $rLen = chr(strlen($r));
        $sLen = chr(strlen($s));
        $totalLen = chr(strlen($r) + strlen($s) + 4);

        return "\x30" . $totalLen .
            "\x02" . $rLen . $r .
            "\x02" . $sLen . $s;
    }

    public function createAccessToken(array $payload, string $dpopProof): ?string
    {
        try {
            $tokenParts = explode('.', $dpopProof);
            $header = json_decode(base64_decode(strtr($tokenParts[0], '-_', '+/') . str_repeat('=', (4 - (strlen($tokenParts[0]) % 4)) % 4)));
            $jwk = $header->jwk;

            $thumbprint = $this->base64url_encode(hash('sha256', json_encode($jwk), true));

            $payload['cnf'] = [
                'jkt' => $thumbprint,
            ];

            $payload['iat'] = time();
            $payload['exp'] = time() + self::EXPIRE_TIME;

            return JWT::encode($payload, $this->private_key, $this->algorithm);
        } catch (\Exception $e) {
            throw new \App\Exceptions\DpopException(183);
        }
    }

    public function verifyAccessToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->public_key, $this->algorithm));
        } catch (\Exception $e) {
            throw new \App\Exceptions\DpopException(184);
        }
    }

    public function extractJwkFromProof(string $proof): ?object
    {
        try {
            $tokenParts = explode('.', $proof);
            $header = json_decode(base64_decode(strtr($tokenParts[0], '-_', '+/') . str_repeat('=', (4 - (strlen($tokenParts[0]) % 4)) % 4)));
            return $header->jwk ?? null;
        } catch (\Exception $e) {
            throw new \App\Exceptions\DpopException(185);
        }
    }

    private function base64url_encode(string $data): string
    {
        $base64 = base64_encode($data);
        $base64url = strtr($base64, '+/', '-_');
        return rtrim($base64url, '=');
    }
}
