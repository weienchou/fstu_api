<?php
namespace App\Utilities;

class PolylineUtility
{
    /**
     * 將解碼後的座標轉換為標準格式的座標陣列
     *
     * @param array $decoded_points 解碼後的點集合
     * @return array 標準格式的座標陣列 [[lat, lng], [lat, lng], ...]
     */
    public static function toStandardCoordinates(array $decoded_points)
    {
        $coordinates = [];
        foreach ($decoded_points as $point) {
            $coordinates[] = [$point['lat'], $point['lng']];
        }
        return $coordinates;
    }

    /**
     * 將解碼後的座標轉換為適合在 MySQL 空間查詢中使用的 LINESTRING 格式
     *
     * @param array $points 座標點陣列
     * @return string WKT 格式的 LINESTRING
     */
    public static function pointsToLineString(array $points)
    {
        $lineStringPoints = [];

        foreach ($points as $point) {
            $lineStringPoints[] = $point['lng'] . ' ' . $point['lat']; // 注意 MySQL 中是 lng lat 順序
        }

        return 'LINESTRING(' . implode(',', $lineStringPoints) . ')';
    }

    /**
     * 計算路線總距離
     *
     * @param array $points 座標點陣列 [['lat' => lat, 'lng' => lng], ...]
     * @return float 距離（公里）
     */
    public static function calculateDistance(array $points)
    {
        $distance = 0;
        $count = count($points);

        for ($i = 0; $i < $count - 1; $i++) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];

            $distance += self::haversineDistance(
                $p1['lat'], $p1['lng'],
                $p2['lat'], $p2['lng']
            );
        }

        return $distance;
    }

    /**
     * 使用 Haversine 公式計算兩點間的距離
     *
     * @param float $lat1 第一點緯度
     * @param float $lng1 第一點經度
     * @param float $lat2 第二點緯度
     * @param float $lng2 第二點經度
     * @return float 距離（公里）
     */
    public static function haversineDistance($lat1, $lng1, $lat2, $lng2)
    {
        // 將緯度和經度轉換為弧度
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        // Haversine 公式
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // 地球半徑（公里）
        $earthRadius = 6371;

        return $earthRadius * $c;
    }

    /**
     * 簡化路線點
     * 減少路線中的點數以降低計算量
     *
     * @param array $points 座標點陣列
     * @param int $factor 簡化因子（保留點的比例的倒數，如 5 表示每 5 個點保留 1 個）
     * @return array 簡化後的座標點陣列
     */
    public static function simplifyRoute(array $points, int $factor = 5)
    {
        if ($factor <= 1 || count($points) <= $factor) {
            return $points;
        }

        $simplified = [];

        // 至少保留起點和終點
        $simplified[] = $points[0];

        for ($i = 1; $i < count($points) - 1; $i += $factor) {
            $simplified[] = $points[$i];
        }

        // 確保添加終點
        if ($simplified[count($simplified) - 1] !== $points[count($points) - 1]) {
            $simplified[] = $points[count($points) - 1];
        }

        return $simplified;
    }

    /**
     * 檢查點是否在多邊形內
     *
     * @param array $point 待檢查的點 ['lat' => lat, 'lng' => lng]
     * @param string $polygon_wkt WKT 格式的多邊形
     * @return bool 點是否在多邊形內
     */
    public static function isPointInPolygon(array $point, string $polygon_wkt)
    {
        // 此方法需要調用空間SQL函數，建議在 Area 模型中實現該功能
        if (!static::$conn) {
            return false;
        }

        $point_wkt = "POINT({$point['lng']} {$point['lat']})";

        $query = 'SELECT ST_Contains(
                    ST_GeomFromText(:polygon_wkt, 4326),
                    ST_GeomFromText(:point_wkt, 4326)
                ) as is_contained';

        $stmt = static::$conn->query($query, [
            'polygon_wkt' => $polygon_wkt,
            'point_wkt' => $point_wkt,
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result && $result['is_contained'];
    }

    /**
     * 計算旅行時間（分鐘）
     * 根據距離和平均速度估算
     *
     * @param float $distance_km 距離（公里）
     * @param float $avg_speed_kph 平均速度（公里/小時）
     * @return int 旅行時間（分鐘）
     */
    public static function estimateTravelTime(float $distance_km, float $avg_speed_kph = 60.0)
    {
        if ($avg_speed_kph <= 0) {
            return 0;
        }

        // 時間（小時）= 距離 / 速度
        $travel_time_hours = $distance_km / $avg_speed_kph;

        // 轉換為分鐘
        return round($travel_time_hours * 60);
    }

    /* Google Polyline解碼函數
     * 將 GoogleMapsPolyline編碼字串轉換為經緯度座標點陣列
     *
     * @param string $encoded編碼的polyline字串
     * @return array 解碼後的座標點陣列[['lat' => lat, 'lng' => lng], ...]
     */
    public static function decodePolyline(string $encoded)
    {
        $points = [];
        $index = $i = 0;
        $lat = $lng = 0;

        while ($i < strlen($encoded)) {
            // 解碼緯度
            $shift = $result = 0;

            do {
                $b = ord($encoded[$i++]) - 63; // 63 是 ASCII 中的 '?'
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20); // 如果第6位設定了，則繼續讀取

            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            // 解碼經度
            $shift = $result = 0;

            do {
                $b = ord($encoded[$i++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            // 將解碼後的座標點添加到陣列中
            $points[] = [
                'lat' => $lat * 1e-5, // 轉換為實際的經緯度值
                'lng' => $lng * 1e-5,
            ];
        }

        return $points;
    }
}
