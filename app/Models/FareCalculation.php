<?php
namespace App\Models;

class FareCalculation extends BaseModel
{
    public static $table = 'fare_calculations';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'origin_coordinates',
        'destination_coordinates',
        'route_coordinates',
        'polyline',
        'distance_km',
        'duration_minutes',
        'base_fare',
        'area_adjustments',
        'toll_fees',
        'distance_cost',
        'early_bird_discount',
        'insurance_fee',
        'total_fare',
        'booking_date',
        'travel_date',
        'passenger_count',
        'vehicle_type',
        'status',
    ];
    protected static $has_timestamps = true;

    /**
     * 創建新的車資計算記錄
     *
     * @param array $origin 起點座標 [lat, lng]
     * @param array $destination 終點座標 [lat, lng]
     * @param array $route 路線座標點
     * @param float $distance_km 距離（公里）
     * @param array $fare_details 車資明細
     * @param array $request_data 請求資料
     * @param string|null $polyline Polyline 編碼字串（可選）
     * @return array 創建後的記錄
     */
    public static function createCalculation(
        array $origin,
        array $destination,
        array $route,
        float $distance_km,
        array $fare_details,
        array $request_data,
        string $polyline = null
    ) {
        $data = [
            'origin_coordinates' => json_encode($origin),
            'destination_coordinates' => json_encode($destination),
            'route_coordinates' => json_encode($route),
            'distance_km' => $distance_km,
            'duration_minutes' => $fare_details['duration_minutes'] ?? 0,
            'base_fare' => $fare_details['base_fare'],
            'area_adjustments' => json_encode($fare_details['area_adjustments']),
            'toll_fees' => json_encode($fare_details['toll_fees']),
            'distance_cost' => $fare_details['distance_cost'],
            'early_bird_discount' => $fare_details['early_bird_discount'],
            'insurance_fee' => $fare_details['insurance_fee'],
            'total_fare' => $fare_details['total_fare'],
            'booking_date' => $request_data['booking_date'],
            'travel_date' => $request_data['travel_date'],
            'passenger_count' => $request_data['passenger_count'],
            'vehicle_type' => $request_data['vehicle_type'],
            'status' => 'calculated',
        ];

        if ($polyline !== null) {
            $data['polyline'] = $polyline;
        }

        return static::create($data);
    }

    /**
     * 根據日期範圍查找車資計算記錄
     *
     * @param string $start_date 起始日期 (Y-m-d)
     * @param string $end_date 結束日期 (Y-m-d)
     * @return array 符合條件的記錄列表
     */
    public static function findByDateRange(string $start_date, string $end_date)
    {
        return static::$conn->select(static::$table, '*', [
            'booking_date[>=]' => $start_date,
            'booking_date[<=]' => $end_date,
            'ORDER' => ['created_at' => 'DESC'],
        ]);
    }

    /**
     * 查找特定使用者的計算記錄
     *
     * @param int $user_id 使用者ID
     * @param int $limit 結果數量限制
     * @param int $offset 結果偏移量
     * @return array 符合條件的記錄列表
     */
    public static function findByUser(int $user_id, int $limit = 10, int $offset = 0)
    {
        return static::$conn->select(static::$table, '*', [
            'user_id' => $user_id,
            'LIMIT' => [$offset, $limit],
            'ORDER' => ['created_at' => 'DESC'],
        ]);
    }

    /**
     * 獲取計算結果的統計資料
     *
     * @param string $start_date 起始日期 (Y-m-d)
     * @param string $end_date 結束日期 (Y-m-d)
     * @return array 統計資料
     */
    public static function getStatistics(string $start_date, string $end_date)
    {
        $query = 'SELECT
                  COUNT(*) as total_count,
                  AVG(total_fare) as average_fare,
                  MAX(total_fare) as max_fare,
                  MIN(total_fare) as min_fare,
                  SUM(distance_km) as total_distance,
                  AVG(distance_km) as average_distance
                  FROM ' . static::$table . '
                  WHERE booking_date BETWEEN :start_date AND :end_date';

        $stmt = static::$conn->query($query, [
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 解析JSON欄位
     *
     * @param array $calculation 計算記錄
     * @return array 解析後的記錄
     */
    public static function parseJsonFields(array $calculation)
    {
        $json_fields = ['origin_coordinates', 'destination_coordinates', 'route_coordinates', 'area_adjustments', 'toll_fees'];

        foreach ($json_fields as $field) {
            if (isset($calculation[$field]) && is_string($calculation[$field])) {
                $calculation[$field] = json_decode($calculation[$field], true);
            }
        }

        return $calculation;
    }

    /**
     * 更新計算記錄狀態
     *
     * @param int $id 記錄ID
     * @param string $status 新狀態
     * @return bool 更新是否成功
     */
    public static function updateStatus(int $id, string $status)
    {
        return static::update($id, ['status' => $status]);
    }
}
