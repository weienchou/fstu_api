<?php
namespace App\Services;

use GuzzleHttp\Client;

class GoogleMapsService
{
    protected $api_key;
    protected $base_url = 'https://places.googleapis.com';
    protected $geocode_url = 'https://maps.googleapis.com/maps/api/geocode/json';
    protected $client;

    public function __construct()
    {
        $this->api_key = env('GOOGLE_MAP_KEY', '');
        $this->client = new Client([
            'base_uri' => $this->base_url,
            'timeout' => 10,
        ]);
    }

    public function place_suggestions(string $address, string $lang = 'nan-Hant-TW')
    {
        $response = $this->client->request('POST', '/v1/places:autocomplete', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->api_key,
            ],
            'json' => [
                'input' => $address,
                'languageCode' => $lang,
                'includeQueryPredictions' => true,
                'includedPrimaryTypes' => ['street_address', 'intersection', 'neighborhood', 'premise', 'point_of_interest'],
                'locationRestriction' => [
                    'rectangle' => [
                        'low' => [
                            'latitude' => '24.7614',
                            'longitude' => '121.3127',
                        ],
                        'high' => [
                            'latitude' => '25.2841',
                            'longitude' => '121.9127',
                        ],
                    ],
                ],
            ],
        ]);

        $body = $response->getBody();

        return $this->place_format(json_decode($body, true));
    }

    private function place_format(array $suggestions)
    {
        if (\__::size($suggestions['suggestions'] ?? []) === 0) {
            return [];
        }

        return \__::map($suggestions['suggestions'], function ($place) {
            $item = $place['placePrediction'];

            return [
                'id' => $item['placeId'],
                'name' => $item['structuredFormat']['mainText']['text'] ?? '',
                'place' => $item['structuredFormat']['secondaryText']['text'] ?? '',
            ];
        });
    }

    public function geocode_tranx(array $location, string $lang = 'nan-Hant-TW')
    {
        $params = [
            'key' => $this->api_key,
            'language' => $lang,
        ];

        $params['latlng'] = implode(',', $location);
        // https://maps.googleapis.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&key=YOUR_API_KEY

        try {
            $response = $this->client->request('GET', $this->geocode_url, [
                'query' => $params,
            ]);

            $result = json_decode($response->getBody(), true);

            if ($result['status'] !== 'OK') {
                throw new \Exception("Geocoding failed with status: {$result['status']}");
            }

            return $this->formatGeocodeResult($result['results'][0]);

        } catch (\Exception $e) {
            throw new \Exception("Geocoding request failed: {$e->getMessage()}");
        }

    }

    private function formatGeocodeResult(array $result)
    {
        return [
            'name' => $result['formatted_address'],
            'id' => $result['place_id'],
        ];
    }

}
