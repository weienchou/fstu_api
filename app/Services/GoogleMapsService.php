<?php
namespace App\Services;

use App\Models\Place;
use App\Models\Route;
use GuzzleHttp\Client;
use Monolog\Logger;

class GoogleMapsService
{
    protected $api_key;
    protected $base_url = 'https://places.googleapis.com';
    protected $geocode_url = 'https://maps.googleapis.com/maps/api/geocode/json';
    protected $directions_url = 'https://routes.googleapis.com/directions/v2:computeRoutes';
    protected $client;
    protected $logger;

    public function __construct(Logger $logger = null)
    {
        $this->api_key = env('GOOGLE_MAP_KEY', '');
        $this->client = new Client([
            'base_uri' => $this->base_url,
            'timeout' => 10,
        ]);
        $this->logger = $logger;

        $this->logDebug('GoogleMapsService initialized', [
            'base_url' => $this->base_url,
            'api_key_exists' => !empty($this->api_key),
        ]);
    }

    /**
     * Log a debug message if logger is available
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Log an error message if logger is available
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }

    public function place_suggestions(string $address, string $lang = 'nan-Hant-TW')
    {
        $this->logDebug('Searching place suggestions', [
            'address' => $address,
            'language' => $lang,
        ]);

        $cached = $this->getCachedPlaceSuggestions($address);
        if (!empty($cached)) {
            $this->logDebug('Using cached place suggestions', [
                'address' => $address,
                'count' => count($cached),
            ]);
            return $cached;
        }

        $suggestions = $this->fetchPlaceSuggestionsFromApi($address, $lang);
        $this->cachePlaceSuggestions($suggestions);

        $this->logDebug('Fetched place suggestions from API', [
            'address' => $address,
            'count' => count($suggestions),
        ]);

        return $suggestions;
    }

    private function getCachedPlaceSuggestions(string $address): array
    {
        $this->logDebug('Looking for cached place suggestions', [
            'address' => $address,
        ]);

        $cachedPlaces = Place::findByAddress($address);
        if (!empty($cachedPlaces)) {
            $this->logDebug('Found cached place suggestions', [
                'count' => count($cachedPlaces),
            ]);

            return array_map(function ($place) {
                return [
                    'id' => $place['place_id'],
                    'name' => $place['name'],
                    'place' => $place['address'],
                ];
            }, $cachedPlaces);
        }

        $this->logDebug('No cached place suggestions found');
        return [];
    }

    private function fetchPlaceSuggestionsFromApi(string $address, string $lang): array
    {
        $this->logDebug('Fetching place suggestions from API', [
            'address' => $address,
            'language' => $lang,
        ]);

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

            $this->logDebug('API response received for place suggestions', [
                'status_code' => $response->getStatusCode(),
                'has_suggestions' => isset($body['suggestions']),
                'suggestions_count' => isset($body['suggestions']) ? count($body['suggestions']) : 0,
            ]);

            return $this->place_format($body);
        } catch (\Exception $e) {
            $response = $e->getResponse();
            $this->logError('Failed to fetch place suggestions from API', [
                'address' => $address,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'body' => $response->getBody()->getContents(),
            ]);

            error_log('Failed to fetch place suggestions: ' . $e->getMessage());
            return [];
        }
    }

    private function cachePlaceSuggestions(array $suggestions): void
    {
        if (empty($suggestions)) {
            $this->logDebug('No place suggestions to cache');
            return;
        }

        $this->logDebug('Caching place suggestions', [
            'count' => count($suggestions),
        ]);

        foreach ($suggestions as $place) {
            Place::add($place['id'], $place['name'], $place['place'], 0, 0);
        }

        $this->logDebug('Place suggestions cached successfully');
    }

    private function place_format(array $suggestions)
    {
        if (\__::size($suggestions['suggestions'] ?? []) === 0) {
            $this->logDebug('No suggestions to format');
            return [];
        }

        $formatted = \__::map($suggestions['suggestions'], function ($place) {
            $item = $place['placePrediction'];

            return [
                'id' => $item['placeId'],
                'name' => $item['structuredFormat']['mainText']['text'] ?? '',
                'place' => $item['structuredFormat']['secondaryText']['text'] ?? '',
            ];
        });

        $this->logDebug('Formatted place suggestions', [
            'count' => count($formatted),
        ]);

        return $formatted;
    }

    public function geocode_tranx(array $location, string $lang = 'nan-Hant-TW')
    {
        $this->logDebug('Geocoding coordinates', [
            'location' => $location,
            'language' => $lang,
        ]);

        $cached = $this->getCachedGeocode($location);
        if ($cached) {
            $this->logDebug('Using cached geocode result', [
                'location' => $location,
                'place_id' => $cached['id'],
            ]);
            return $cached;
        }

        $geocode = $this->fetchGeocodeFromApi($location, $lang);
        $this->cacheGeocode($geocode, $location);

        $this->logDebug('Fetched geocode from API', [
            'location' => $location,
            'place_id' => $geocode['id'],
        ]);

        return $geocode;
    }

    private function getCachedGeocode(array $location): ?array
    {
        $this->logDebug('Looking for cached geocode', [
            'latitude' => $location[0],
            'longitude' => $location[1],
        ]);

        $cachedPlace = Place::findByLatLng($location[0], $location[1]);
        if ($cachedPlace) {
            $this->logDebug('Found cached geocode', [
                'place_id' => $cachedPlace['place_id'],
            ]);

            return [
                'name' => $cachedPlace['address'],
                'id' => $cachedPlace['place_id'],
            ];
        }

        $this->logDebug('No cached geocode found');
        return null;
    }

    private function fetchGeocodeFromApi(array $location, string $lang): array
    {
        $this->logDebug('Fetching geocode from API', [
            'location' => $location,
            'language' => $lang,
        ]);

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

            $this->logDebug('API response received for geocode', [
                'status_code' => $response->getStatusCode(),
                'api_status' => $result['status'],
                'results_count' => count($result['results'] ?? [])
            ]);

            if ($result['status'] !== 'OK') {
                $error_message = "Geocoding failed with status: {$result['status']}";

                $this->logError($error_message, [
                    'location' => $location,
                    'api_status' => $result['status'],
                ]);

                throw new \Exception($error_message);
            }

            return $this->formatGeocodeResult($result['results'][0]);
        } catch (\Exception $e) {
            $this->logError('Failed to fetch geocode from API', [
                'location' => $location,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            error_log('Failed to fetch geocode: ' . $e->getMessage());
            throw new \Exception("Geocoding request failed: {$e->getMessage()}");
        }
    }

    private function formatGeocodeResult(array $result)
    {
        $formatted = [
            'name' => $result['formatted_address'],
            'id' => $result['place_id'],
        ];

        $this->logDebug('Formatted geocode result', [
            'place_id' => $formatted['id'],
        ]);

        return $formatted;
    }

    private function cacheGeocode(array $geocode, array $location): void
    {
        $this->logDebug('Caching geocode result', [
            'place_id' => $geocode['id'],
            'latitude' => $location[0],
            'longitude' => $location[1],
        ]);

        Place::add($geocode['id'], $geocode['name'], $geocode['name'], $location[0], $location[1]);

        $this->logDebug('Geocode result cached successfully');
    }

    public function calculateRouteToAirport(string $place_id, string $airport_place_id = 'ChIJ1RXSYsCfQjQRCbG1qZC2o3A', string $mode = 'DRIVE')
    {
        $this->logDebug('Calculating route to airport', [
            'origin_place_id' => $place_id,
            'airport_place_id' => $airport_place_id,
            'travel_mode' => $mode,
        ]);

        $cached = $this->getCachedRoute($place_id, $airport_place_id);
        if ($cached && $this->isCacheValid($cached['updated_at'])) {
            $this->logDebug('Using cached route', [
                'origin_place_id' => $place_id,
                'airport_place_id' => $airport_place_id,
                'distance' => $cached['distance'],
                'duration' => $cached['duration'],
            ]);
            return $cached;
        }

        $route = $this->fetchRouteFromApi($place_id, $airport_place_id, $mode);
        $this->cacheRoute($route, $place_id, $airport_place_id);

        $this->logDebug('Fetched route from API', [
            'origin_place_id' => $place_id,
            'airport_place_id' => $airport_place_id,
            'distance' => $route['distance'],
            'duration' => $route['duration'],
        ]);

        return $route;
    }

    private function getCachedRoute(string $place_id, string $airport_place_id): ?array
    {
        $this->logDebug('Looking for cached route', [
            'origin_place_id' => $place_id,
            'airport_place_id' => $airport_place_id,
        ]);

        $cachedRoute = Route::findByPlaceAndAirport($place_id, $airport_place_id);
        if ($cachedRoute) {
            $this->logDebug('Found cached route', [
                'distance_km' => $cachedRoute['distance_km'],
                'duration_seconds' => $cachedRoute['duration_seconds'],
                'updated_at' => $cachedRoute['updated_at'],
            ]);

            return [
                'distance' => $cachedRoute['distance_km'] * 1000,
                'duration' => $cachedRoute['duration_seconds'],
                'encodedPolyline' => $cachedRoute['polyline'],
                'updated_at' => $cachedRoute['updated_at'],
            ];
        }

        $this->logDebug('No cached route found');
        return null;
    }

    private function fetchRouteFromApi(string $place_id, string $airport_place_id, string $mode): array
    {
        $this->logDebug('Fetching route from API', [
            'origin_place_id' => $place_id,
            'airport_place_id' => $airport_place_id,
            'travel_mode' => $mode,
        ]);

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

            $this->logDebug('API response received for route', [
                'status_code' => $response->getStatusCode(),
                'routes_count' => count($result['routes'] ?? [])
            ]);

            if (count($result['routes']) === 0) {
                $error_message = 'Directions request failed: no routes returned';

                $this->logError($error_message, [
                    'origin_place_id' => $place_id,
                    'airport_place_id' => $airport_place_id,
                ]);

                throw new \Exception($error_message);
            }

            return $this->formatDirectionsResult($result);
        } catch (\Exception $e) {
            $this->logError('Failed to fetch route from API', [
                'origin_place_id' => $place_id,
                'airport_place_id' => $airport_place_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            error_log('Failed to fetch route: ' . $e->getMessage());
            throw new \Exception("Directions request failed: {$e->getMessage()}");
        }
    }

    private function cacheRoute(array $route, string $place_id, string $airport_place_id): void
    {
        $this->logDebug('Caching route', [
            'origin_place_id' => $place_id,
            'airport_place_id' => $airport_place_id,
            'distance_km' => $route['distance'] / 1000,
            'duration_seconds' => $route['duration'],
        ]);

        Route::add($place_id, $airport_place_id, $route['distance'] / 1000, $route['duration'], $route['encodedPolyline']);

        $this->logDebug('Route cached successfully');
    }

    private function isCacheValid($updated_at): bool
    {
        $expiration = strtotime('-30 days');
        $is_valid = strtotime($updated_at) > $expiration;

        $this->logDebug('Checking if cache is valid', [
            'updated_at' => $updated_at,
            'expiration' => date('Y-m-d H:i:s', $expiration),
            'is_valid' => $is_valid,
        ]);

        return $is_valid;
    }

    private function formatDirectionsResult(array $result)
    {
        $route = $result['routes'][0];
        $formatted = [
            'distance' => $route['distanceMeters'],
            'duration' => intval($route['duration']),
            'encodedPolyline' => $route['polyline']['encodedPolyline'],
        ];

        $this->logDebug('Formatted directions result', [
            'distance_meters' => $formatted['distance'],
            'duration_seconds' => $formatted['duration'],
            'has_polyline' => !empty($formatted['encodedPolyline']),
        ]);

        return $formatted;
    }
}
