<?php
namespace App\Models;

class TollStation extends BaseModel
{
    public static $table = 'toll_stations';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'highway',
        'direction',
        'station_code',
        'toll_zone_code',
        'start_interchange',
        'end_interchange',
        'distance',
        'car_fee',
        'truck_fee',
        'trailer_fee',
        'lat',
        'lng',
    ];
    protected static $has_timestamps = true;

    /**
     * 獲取所有收費站
     *
     * @return array 所有收費站資料
     */
    public static function getAllStations()
    {
        return static::$conn->select(static::$table, '*');
    }

    /**
     * 根據高速公路名稱查找收費站
     *
     * @param string $highway 高速公路名稱
     * @return array 符合條件的收費站列表
     */
    public static function findByHighway(string $highway)
    {
        return static::$conn->select(static::$table, '*', [
            'highway' => $highway,
        ]);
    }

    /**
     * 根據車輛類型獲取過路費
     *
     * @param int $station_id 收費站ID
     * @param string $vehicle_type 車輛類型
     * @return float 過路費
     */
    public static function getFeeByVehicleType(int $station_id, string $vehicle_type)
    {
        $station = self::findById($station_id);
        if (!$station) {
            return 0;
        }

        switch ($vehicle_type) {
            case 'car':
                return (float) $station['car_fee'];
            case 'truck':
                return (float) $station['truck_fee'];
            case 'trailer':
                return (float) $station['trailer_fee'];
            default:
                return (float) $station['car_fee'];
        }
    }

    /**
     * 查找靠近特定坐標的收費站
     *
     * @param float $latitude 緯度
     * @param float $longitude 經度
     * @param float $distance_km 距離（公里）
     * @return array 符合條件的收費站列表
     */
    public static function findNearCoordinates(float $latitude, float $longitude, float $distance_km = 0.5)
    {
                              // 使用 Haversine 公式計算距離
        $earth_radius = 6371; // 地球半徑（公里）

        $query = "SELECT *,
                  ($earth_radius * acos(cos(radians(:lat)) * cos(radians(lat)) *
                  cos(radians(lng) - radians(:lng)) + sin(radians(:lat)) *
                  sin(radians(lat)))) AS distance
                  FROM " . static::$table . '
                  HAVING distance < :distance
                  ORDER BY distance';

        $stmt = static::$conn->query($query, [
            'lat' => $latitude,
            'lng' => $longitude,
            'distance' => $distance_km,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 檢查路線是否經過收費站
     *
     * @param array $start_point 起點座標 [lat, lng]
     * @param array $end_point 終點座標 [lat, lng]
     * @param array $toll_point 收費站座標 [lat, lng]
     * @param float $tolerance 容差（公里）
     * @return bool 如果路線經過收費站則返回true
     */
    public static function isRoutePassingStation($start_point, $end_point, $station_point, $threshold = 0.02)
    {
        // 將經緯度轉換為數值
        $lat1 = $start_point[0];
        $lng1 = $start_point[1];
        $lat2 = $end_point[0];
        $lng2 = $end_point[1];
        $lat_s = $station_point[0];
        $lng_s = $station_point[1];

        // 計算點到線段的最短距離（使用 Haversine 公式或簡單近似）
        $distance = self::pointToLineDistance($lat_s, $lng_s, $lat1, $lng1, $lat2, $lng2);

        // 如果距離小於閾值，認為路線經過該站
        return $distance <= $threshold;
    }

    // 點到線段的最短距離（簡單歐幾里得距離近似）
    public static function pointToLineDistance($lat_p, $lng_p, $lat1, $lng1, $lat2, $lng2)
    {
        $numerator = abs(($lat2 - $lat1) * ($lng1 - $lng_p) - ($lat1 - $lat_p) * ($lng2 - $lng1));
        $denominator = sqrt(pow($lat2 - $lat1, 2) + pow($lng2 - $lng1, 2));

        // 如果分母為零（線段長度為零），直接返回點到起點的距離
        if ($denominator == 0) {
            return sqrt(pow($lat_p - $lat1, 2) + pow($lng_p - $lng1, 2));
        }

        $distance = $numerator / $denominator;

        // 檢查點是否在線段範圍內
        $dot = (($lat_p - $lat1) * ($lat2 - $lat1) + ($lng_p - $lng1) * ($lng2 - $lng1)) / pow($denominator, 2);
        if ($dot < 0 || $dot > 1) {
            // 如果點不在線段範圍內，取最近端點的距離
            $dist1 = sqrt(pow($lat_p - $lat1, 2) + pow($lng_p - $lng1, 2));
            $dist2 = sqrt(pow($lat_p - $lat2, 2) + pow($lng_p - $lng2, 2));
            $distance = min($dist1, $dist2);
        }

        return $distance;
    }

    /**
     * 計算 Haversine 距離
     *
     * @param float $lat1 第一點緯度（弧度）
     * @param float $lng1 第一點經度（弧度）
     * @param float $lat2 第二點緯度（弧度）
     * @param float $lng2 第二點經度（弧度）
     * @return float 距離（公里）
     */
    public static function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑（公里）
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public static function calculateHighwayDirection($start_point, $end_point)
    {
        $lat_diff = $end_point[0] - $start_point[0];
        if ($lat_diff > 0) {
            return 'N'; // 北上
        } elseif ($lat_diff < 0) {
            return 'S'; // 南下
        }
        return null; // 如果無明顯方向，返回 null
    }

    public static function isDirectionMatching(string $station_direction, string $driving_direction)
    {
        // 如果收費站沒有指定方向（為空），表示雙向都收費
        if (empty($station_direction)) {
            return true;
        }

        // 檢查行駛方向是否與收費站方向匹配
        return $station_direction === $driving_direction;
    }

}
