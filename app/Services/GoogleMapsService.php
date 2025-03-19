<?php
namespace App\Services;

use App\Models\Place;
use App\Models\Route;
use GuzzleHttp\Client;

class GoogleMapsService
{
    protected $api_key;
    protected $base_url = 'https://places.googleapis.com';
    protected $geocode_url = 'https://maps.googleapis.com/maps/api/geocode/json';
    protected $directions_url = 'https://routes.googleapis.com/directions/v2:computeRoutes';
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
        $cached = $this->getCachedPlaceSuggestions($address);
        if (!empty($cached)) {
            return $cached;
        }

        $suggestions = $this->fetchPlaceSuggestionsFromApi($address, $lang);
        $this->cachePlaceSuggestions($suggestions);

        return $suggestions;
    }

    private function getCachedPlaceSuggestions(string $address): array
    {
        $cachedPlaces = Place::findByAddress($address);
        if (!empty($cachedPlaces)) {
            return array_map(function ($place) {
                return [
                    'id' => $place['place_id'],
                    'name' => $place['name'],
                    'place' => $place['address'],
                ];
            }, $cachedPlaces);
        }
        return [];
    }

    private function fetchPlaceSuggestionsFromApi(string $address, string $lang): array
    {
        try {
            $response = $this->client->request('POST', '/v1/places:autocomplete', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->api_key,
                ],
                'json' => [
                    'input' => $address,
                    'languageCode' => $lang,
                    'includeQueryPredictions' => true,
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

            $body = json_decode($response->getBody(), true);
            return $this->place_format($body);
        } catch (\Exception $e) {
            // 假設有簡單日誌函數，若無可移除
            error_log('Failed to fetch place suggestions: ' . $e->getMessage());
            return [];
        }
    }

    private function cachePlaceSuggestions(array $suggestions): void
    {
        foreach ($suggestions as $place) {
            Place::add($place['id'], $place['name'], $place['place'], 0, 0);
        }
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
        $cached = $this->getCachedGeocode($location);
        if ($cached) {
            return $cached;
        }

        $geocode = $this->fetchGeocodeFromApi($location, $lang);
        $this->cacheGeocode($geocode, $location);

        return $geocode;
    }

    private function getCachedGeocode(array $location): ?array
    {
        $cachedPlace = Place::findByLatLng($location[0], $location[1]);
        if ($cachedPlace) {
            return [
                'name' => $cachedPlace['address'],
                'id' => $cachedPlace['place_id'],
            ];
        }
        return null;
    }

    private function fetchGeocodeFromApi(array $location, string $lang): array
    {
        $params = [
            'key' => $this->api_key,
            'language' => $lang,
            'latlng' => implode(',', $location),
        ];

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
            error_log('Failed to fetch geocode: ' . $e->getMessage());
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

    private function cacheGeocode(array $geocode, array $location): void
    {
        Place::add($geocode['id'], $geocode['name'], $geocode['name'], $location[0], $location[1]);
    }

    public function calculateRouteToAirport(string $place_id, string $airport_place_id = 'ChIJ1RXSYsCfQjQRCbG1qZC2o3A', string $mode = 'DRIVE')
    {
        $cached = $this->getCachedRoute($place_id, $airport_place_id);
        if ($cached && $this->isCacheValid($cached['updated_at'])) {
            return $cached;
        }

        $route = $this->fetchRouteFromApi($place_id, $airport_place_id, $mode);
        $this->cacheRoute($route, $place_id, $airport_place_id);

        return $route;
    }

    private function getCachedRoute(string $place_id, string $airport_place_id): ?array
    {
        $cachedRoute = Route::findByPlaceAndAirport($place_id, $airport_place_id);
        if ($cachedRoute) {
            return [
                'distance' => $cachedRoute['distance_km'] * 1000,
                'duration' => $cachedRoute['duration_seconds'],
                'encodedPolyline' => $cachedRoute['polyline'],
                'updated_at' => $cachedRoute['updated_at'],
            ];
        }
        return null;
    }

    private function fetchRouteFromApi(string $place_id, string $airport_place_id, string $mode): array
    {
        $params = [
            'origin' => ['place_id' => $place_id],
            'destination' => ['place_id' => $airport_place_id],
            'travelMode' => $mode,
            'languageCode' => 'nan-Hant-TW',
        ];

        try {
            $response = $this->client->request('POST', $this->directions_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $this->api_key,
                    'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline',
                ],
                'json' => $params,
            ]);

            $result = json_decode($response->getBody(), true);
            if (count($result['routes']) === 0) {
                throw new \Exception("Directions request failed with status: {$result['status']}");
            }

            return $this->formatDirectionsResult($result);
        } catch (\Exception $e) {
            error_log('Failed to fetch route: ' . $e->getMessage());
            throw new \Exception("Directions request failed: {$e->getMessage()}");
        }
    }

    private function cacheRoute(array $route, string $place_id, string $airport_place_id): void
    {
        Route::add($place_id, $airport_place_id, $route['distance'] / 1000, $route['duration'], $route['encodedPolyline']);
    }

    private function isCacheValid($updated_at): bool
    {
        $expiration = strtotime('-30 days');
        return strtotime($updated_at) > $expiration;
    }

    private function formatDirectionsResult(array $result)
    {
        $route = $result['routes'][0];
        return [
            'distance' => $route['distanceMeters'],
            'duration' => intval($route['duration']),
            'encodedPolyline' => $route['polyline']['encodedPolyline'],
        ];
    }

}
