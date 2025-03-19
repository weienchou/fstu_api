<?php
namespace App\Models;

class Place extends BaseModel
{
    public static $table = 'places';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'place_id',
        'name',
        'address',
        'latitude',
        'longitude',
    ];
    protected static $has_timestamps = true;

    public static function findByPlaceId(string $place_id)
    {
        return static::$conn->get(static::$table, '*', [
            'place_id' => $place_id,
        ]);
    }

    public static function findByLatLng(float $latitude, float $longitude)
    {
        return static::$conn->get(static::$table, '*', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public static function findByAddress(string $address): array
    {
        return static::$conn->select(static::$table, '*', [
            'address[~]' => $address, // 模糊查詢
        ]);
    }

    public static function add(string $place_id, string $name, string $address, float $latitude = 0, float $longitude = 0)
    {
        return static::createOrUpdate(
            ['place_id' => $place_id],
            [
                'name' => $name,
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]
        );
    }
}
