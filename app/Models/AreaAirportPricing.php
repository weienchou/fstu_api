<?php
namespace App\Models;

class AreaAirportPricing extends BaseModel
{
    public static $table = 'area_airport_pricing';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'area_id',
        'city_id',
        'airport_id',
        'price',
    ];
    protected static $has_timestamps = true;

    /**
     * 根據區域ID查找定價資料
     *
     * @param int $area_id 區域ID
     * @return array|null 區域機場定價資料
     */
    public static function findByAreaId(int $area_id)
    {
        return static::$conn->get(static::$table, '*', [
            'area_id' => $area_id,
        ]);
    }

    /**
     * 根據機場ID和城市ID查找定價資料
     *
     * @param int $airport_id 機場ID
     * @param int $city_id 城市ID
     * @return array 符合條件的定價資料
     */
    public static function findByAirportAndCity(int $airport_id, int $city_id)
    {
        return static::$conn->select(static::$table, '*', [
            'airport_id' => $airport_id,
            'city_id' => $city_id,
        ]);
    }

    /**
     * 根據機場ID查找所有定價資料
     *
     * @param int $airport_id 機場ID
     * @return array 符合條件的定價資料
     */
    public static function findByAirport(int $airport_id)
    {
        return static::$conn->select(static::$table, '*', [
            'airport_id' => $airport_id,
        ]);
    }

    /**
     * 更新或創建區域機場定價
     *
     * @param int $area_id 區域ID
     * @param int $city_id 城市ID
     * @param int $airport_id 機場ID
     * @param float $price 價格
     * @return array 創建或更新後的定價資料
     */
    public static function updateOrCreate(int $area_id, int $city_id, int $airport_id, float $price)
    {
        return static::createOrUpdate(
            [
                'area_id' => $area_id,
                'city_id' => $city_id,
                'airport_id' => $airport_id,
            ],
            [
                'price' => $price,
            ]
        );
    }
}
