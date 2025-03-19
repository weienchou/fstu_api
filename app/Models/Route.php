<?php
namespace App\Models;

class Route extends BaseModel
{
    public static $table = 'routes';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'place_id',
        'airport_id',
        'distance_km',
        'duration_seconds',
        'polyline',
    ];
    protected static $has_timestamps = true;

    public static function findByPlaceAndAirport(string $place_id, string $airport_place_id)
    {
        return static::$conn->get(static::$table, '*', [
            'place_id' => $place_id,
            'airport_place_id' => $airport_place_id,
        ]);
    }

    public static function add(string $placeId, string $airport_place_id, float $distance_km, int $duration_seconds, string $polyline)
    {
        return static::createOrUpdate(
            ['place_id' => $placeId, 'airport_place_id' => $airport_place_id],
            [
                'distance_km' => $distance_km,
                'duration_seconds' => $duration_seconds,
                'polyline' => $polyline,
            ]
        );
    }
}
