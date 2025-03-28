<?php
namespace App\Models;

class Area extends BaseModel
{
    public static $table = 'areas';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'city_id',
        'area_code',
        'city',
        'district',
        'village',
        'area_name',
        'parent_area_code',
        'geometry',
        'status',
    ];
    protected static $has_timestamps = true;

    /**
     * 查找包含指定座標點的地區
     *
     * @param float $latitude 緯度
     * @param float $longitude 經度
     * @return array|null 找到的地區資料
     */
    public static function findByCoordinates(float $latitude, float $longitude)
    {
        $point = "POINT($longitude $latitude)";

        $query = 'SELECT * FROM <' . static::$table . '>
                  WHERE ST_Contains(geometry, ST_GeomFromText(:point, 4326))
                  AND status = 1
                  LIMIT 1';

        $stmt = static::$conn->query($query, [
            'point' => $point,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 查找與指定線段相交的所有區域
     *
     * @param string $linestring WKT格式的LineString
     * @return array 相交的區域清單
     */
    public static function findByLineString(string $linestring)
    {
        $query = 'SELECT DISTINCT id, area_name, city, district
                  FROM ' . static::$table . '
                  WHERE ST_Intersects(
                      ST_GeomFromText(geometry, 4326),
                      ST_GeomFromText(:linestring, 4326)
                  )
                  AND status = 1';

        $stmt = static::$conn->query($query, [
            'linestring' => $linestring,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 獲取區域的機場定價資料
     *
     * @param int $area_id 區域ID
     * @return array 該區域的機場定價資料
     */
    public static function getAirportPricing(int $area_id)
    {
        return AreaAirportPricing::findByAreaId($area_id);
    }

    /**
     * 檢查點是否在某個區域內
     *
     * @param int $area_id 區域ID
     * @param float $latitude 緯度
     * @param float $longitude 經度
     * @return bool 如果點在區域內則返回true
     */
    public static function isPointInArea(int $area_id, float $latitude, float $longitude)
    {
        $area = self::findById($area_id);
        if (!$area) {
            return false;
        }

        $point = "POINT($longitude $latitude)";

        $query = 'SELECT ST_Contains(
                      ST_GeomFromText(:geometry, 4326),
                      ST_GeomFromText(:point, 4326)
                  ) as is_contained';

        $stmt = static::$conn->query($query, [
            'geometry' => $area['geometry'],
            'point' => $point,
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result && $result['is_contained'];
    }
}
