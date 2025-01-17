<?php
namespace App\Libraries;

use App\Exceptions\LineException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class LineApi
{
    private const BASE_URL = 'https://api.line.me';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 5.0,
        ]);
    }

    public function verifyAccessToken(string $accessToken)
    {
        try {
            $response = $this->client->get('/oauth2/v2.1/verify', [
                'query' => ['access_token' => $accessToken],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new LineException(420, ['token' => $accessToken]);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (!$data) {
                throw new LineException(401);
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new LineException(400, [], $e);
        }
    }

    public function getUserProfile(string $accessToken)
    {
        try {
            $response = $this->client->get('/v2/profile', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new LineException(440);
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (!$data || !isset($data['userId'])) {
                throw new LineException(401);
            }

            return [
                'userId' => $data['userId'],
                'displayName' => $data['displayName'],
                'pictureUrl' => $data['pictureUrl'] ?? null,
                'statusMessage' => $data['statusMessage'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new LineException(400, [], $e);
        }
    }

}
