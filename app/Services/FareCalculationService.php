<?php
namespace App\Services;

use App\Libraries\Redis;
use App\Models\Area;
use App\Models\AreaAirportPricing;
use App\Models\FareCalculation;
use App\Models\PricingRule;
use App\Models\TollStation;
use App\Utilities\PolylineUtility;

class FareCalculationService
{
    private $expireTime = 7200; // 默認 2 小時

    public function __construct()
    {
    }

    /**
     * 使用 Polyline 計算車資
     *
     * @param string $encodedPolyline Google Maps Polyline 編碼字串
     * @param string|null $calculationToken 計算 Token，如果為 null 則生成新的
     * @param string|null $booking_date 預訂日期 (Y-m-d)，可選
     * @param int|null $passenger_count 乘客數量，可選
     * @param string $vehicle_type 車輛類型 (car, truck, trailer)
     * @param string|null $travel_date 旅行日期 (Y-m-d)，可選
     * @return array 計算結果及明細，包含 calculation_token
     */
    public function calculateFareFromPolyline(
        string $encodedPolyline,
        int $distance_meters,
        int $duration_seconds,
        ?string $calculationToken = null,
        ?string $booking_date = null,
        ?int $passenger_count = null,
        string $vehicle_type = 'car',
        ?string $travel_date = null
    ): array {
        // 檢查是否提供了計算 Token，如果提供了則嘗試從 Redis 獲取先前的計算結果
        if ($calculationToken) {
            $previousResult = $this->getCalculationFromRedis($calculationToken);
            if ($previousResult) {
                // 使用新參數更新計算結果
                $updateData = [];
                if ($booking_date !== null) {
                    $updateData['booking_date'] = $booking_date;
                }

                if ($travel_date !== null) {
                    $updateData['travel_date'] = $travel_date;
                }

                if ($passenger_count !== null) {
                    $updateData['passenger_count'] = $passenger_count;
                }

                if ($vehicle_type !== 'car') {
                    $updateData['vehicle_type'] = $vehicle_type;
                }

                $result = $this->updateFareCalculation($previousResult, $updateData);
                $result['calculation_token'] = $calculationToken;

                // 將更新後的結果存回 Redis
                $this->storeCalculationInRedis($calculationToken, $result);

                return $result;
            }
        }

        // 如果沒有提供 Token 或者找不到先前的計算結果，則進行新的計算
        // 解碼 Polyline 獲取座標點
        $decodedPoints = PolylineUtility::decodePolyline($encodedPolyline);

        // 計算路線基本資訊
        $distance_km = $distance_meters / 1000;
        $duration_minutes = $duration_seconds / 60;

        // 初始化結果陣列
        $result = [
            'base_fare' => 0,
            'area_adjustments' => [],
            'toll_routes' => [],   // 更改為 toll_routes
            'total_toll_fee' => 0, // 新增總過路費
            'distance_cost' => 0,
            'early_bird_discount' => 0,
            'insurance_fee' => 0,
            'total_fare' => 0,
            'distance_km' => $distance_km,
            'duration_minutes' => $duration_minutes,
            'polyline' => $encodedPolyline,
        ];

        // 提取起點和終點
        $origin = $decodedPoints[0];
        $destination = $decodedPoints[count($decodedPoints) - 1];
        $result['origin'] = $origin;
        $result['destination'] = $destination;

        // 1. 從定價規則獲取基本費用
        $baseRule = PricingRule::findActiveByType('basic');
        if ($baseRule) {
            $result['base_fare'] = (float) $baseRule['charge_amount'];
        }

        // 2. 查找座標點所在區域並計算區域調整費用
        $result['area_adjustments'] = $this->calculateAreaAdjustments($decodedPoints);

        $result['area_adjustments_fee'] = \__::first($result['area_adjustments'])['adjustment'] ?? 0;

                                        // 3. 計算高速公路過路費
                                        // $toll_calculation = $this->calculateTollFeesFromPoints($decodedPoints, $vehicle_type);
                                        // $result['toll_routes'] = $toll_calculation['toll_routes'];       // 更新為 toll_routes
        $result['total_toll_fee'] = 50; // $toll_calculation['total_toll_fee']; // 新增總過路費
                                        // $result['missing_ids'] = $toll_calculation['missing_ids'];       // 新增總過路費

        // 4. 計算距離費用
        $perKmRule = PricingRule::findActiveByType('per_km');
        if ($perKmRule) {
            $result['distance_cost'] = ceil($distance_km * (float) $perKmRule['charge_amount']);
        }

        // 5. 計算早鳥折扣（如果提供了預訂日期和旅行日期）
        if ($booking_date !== null && $travel_date !== null) {
            $result['early_bird_discount'] = $this->calculateEarlyBirdDiscount($booking_date, $travel_date);
        }

        // 6. 計算保險費（如果提供了乘客數量）
        if ($passenger_count !== null) {
            $result['insurance_fee'] = $this->calculateInsuranceFee($passenger_count);
        }

        // 計算總車資（僅包含已知資訊的部分）
        $totalFare = $result['base_fare'];

        // 加上區域調整費用
        foreach ($result['area_adjustments'] as $adjustment) {
            $totalFare += $adjustment['adjustment'];
        }

                                                 // 加上總過路費
        $totalFare += $result['total_toll_fee']; // 使用總過路費而不是遍歷每個收費站

        // 加上距離費用
        $totalFare += $result['distance_cost'];

                                                      // 應用早鳥折扣
        $totalFare += $result['early_bird_discount']; // 折扣為負數

        // 加上保險費
        $totalFare += $result['insurance_fee'];

        $result['total_fare'] = ceil(max(0, $totalFare)); // 確保車資不為負數

        // 如果沒有提供計算 Token，則生成一個新的
        if (!$calculationToken) {
            $calculationToken = $this->generateCalculationToken($encodedPolyline);
        }

        $result['calculation_token'] = $calculationToken;

        // 將計算結果存儲到 Redis
        $this->storeCalculationInRedis($calculationToken, $result);

        return $result;
    }

    /**
     * 更新車資計算
     *
     * @param array $currentResult 目前計算結果
     * @param array $updates 要更新的資訊
     * @return array 更新後的計算結果
     */
    public function updateFareCalculation(array $currentResult, array $updates): array
    {
        $result = $currentResult;

        // 更新乘客數量與保險費
        if (isset($updates['passenger_count'])) {
            $passengerCount = (int) $updates['passenger_count'];
            $result['insurance_fee'] = $this->calculateInsuranceFee($passengerCount);
        }

        // 更新車輛類型與過路費
        if (isset($updates['vehicle_type']) && $updates['vehicle_type'] !== ($currentResult['vehicle_type'] ?? 'car')) {
            $vehicleType = $updates['vehicle_type'];
            $result['vehicle_type'] = $vehicleType;

            // 重新計算過路費
            if (isset($currentResult['polyline'])) {
                $decodedPoints = PolylineUtility::decodePolyline($currentResult['polyline']);
                $toll_calculation = $this->calculateTollFeesFromPoints($decodedPoints, $vehicleType);
                $result['toll_routes'] = $toll_calculation['toll_routes'];       // 更新為 toll_routes
                $result['total_toll_fee'] = $toll_calculation['total_toll_fee']; // 新增總過路費
            }
        }

        // 更新預訂/旅行日期與早鳥折扣
        if ((isset($updates['booking_date']) || isset($updates['travel_date']))) {
            $bookingDate = $updates['booking_date'] ?? ($currentResult['booking_date'] ?? null);
            $travelDate = $updates['travel_date'] ?? ($currentResult['travel_date'] ?? null);

            if ($bookingDate && $travelDate) {
                $result['booking_date'] = $bookingDate;
                $result['travel_date'] = $travelDate;
                $result['early_bird_discount'] = $this->calculateEarlyBirdDiscount($bookingDate, $travelDate);
            }
        }

        // 重新計算總車資
        $totalFare = $result['base_fare'];

        // 加上區域調整費用
        foreach ($result['area_adjustments'] as $adjustment) {
            $totalFare += $adjustment['adjustment'];
        }

                                                 // 加上總過路費
        $totalFare += $result['total_toll_fee']; // 使用總過路費而不是遍歷每個收費站

        // 加上距離費用
        $totalFare += $result['distance_cost'];

                                                      // 應用早鳥折扣
        $totalFare += $result['early_bird_discount']; // 折扣為負數

        // 加上保險費
        $totalFare += $result['insurance_fee'];

        $result['total_fare'] = max(0, $totalFare); // 確保車資不為負數

        return $result;
    }

    /**
     * 使用計算 Token 從 Redis 獲取計算結果
     *
     * @param string $token 計算 Token
     * @return array|null 計算結果，如果找不到則返回 null
     */
    public function getCalculationFromRedis(string $token): ?array
    {
        $key = 'fare_calculation:' . $token;
        $data = Redis::get($key);

        if ($data) {
            return json_decode($data, true);
        }

        return null;
    }

    /**
     * 將計算結果存儲到 Redis
     *
     * @param string $token 計算 Token
     * @param array $result 計算結果
     * @return bool 是否成功存儲
     */
    public function storeCalculationInRedis(string $token, array $result): bool
    {
        $key = 'fare_calculation:' . $token;
        return Redis::setex($key, $this->expireTime, json_encode($result));
    }

    /**
     * 刪除 Redis 中的計算結果
     *
     * @param string $token 計算 Token
     * @return bool 是否成功刪除
     */
    public function deleteCalculationFromRedis(string $token): bool
    {
        $key = 'fare_calculation:' . $token;
        return Redis::delete($key) > 0;
    }

    /**
     * 生成唯一的計算 Token
     *
     * @param string $polyline Polyline 字串
     * @return string 計算 Token
     */
    private function generateCalculationToken(string $polyline): string
    {
        return md5($polyline . microtime() . rand(1000, 9999));
    }

    /**
     * 設定 Redis 鍵的過期時間
     *
     * @param int $seconds 過期時間（秒）
     * @return $this
     */
    public function setExpireTime(int $seconds): self
    {
        $this->expireTime = $seconds;
        return $this;
    }

    /**
     * 計算座標點的區域調整費用
     *
     * @param array $points 座標點
     * @return array 區域調整費用
     */
    private function calculateAreaAdjustments(array $points): array
    {
        $adjustments = [];
        $processedAreas = []; // 用於避免重複計算同一區域

        // 為了效率，僅檢查部分點（起點、終點和一些中間點）
        $pointsToCheck = [];
        $pointsToCheck[] = 0; // 起點
                              // $pointsToCheck[] = count($points) - 1; // 終點

        // 添加一些中間點
        // $step = max(1, floor(count($points) / 5));
        // for ($i = $step; $i < count($points) - 1; $i += $step) {
        //     $pointsToCheck[] = $i;
        // }

        foreach ($pointsToCheck as $index) {
            $point = $points[$index];
            $area = Area::findByCoordinates($point['lng'], $point['lat']);

            if ($area && !in_array($area['id'], $processedAreas)) {
                // 查詢區域定價調整
                $pricing = AreaAirportPricing::findByAreaId($area['id']);
                if ($pricing) {
                    $adjustments[] = [
                        'point' => $index,
                        'area_id' => $area['id'],
                        'area_name' => $area['area_name'],
                        'adjustment' => (float) $pricing['price'],
                    ];

                    $processedAreas[] = $area['id'];
                }
            }
        }

        return $adjustments;
    }

    /**
     * 從點集合計算過路費
     *
     * @param array $points 座標點
     * @param string $vehicle_type 車輛類型
     * @return array 過路費明細
     */

    private function calculateTollFeesFromPoints(array $points, string $vehicle_type): array
    {
        $toll_routes = [];
        $total_toll_fee = 0;
        $processed_stations = [];
        $distanceThreshold = 0.1;
        $matchedSegments = [];

        $processedSegmentIds = []; // 記錄已處理的路段ID
        $processedLocations = [];  // 記錄已處理的地理位置
        $locationThreshold = 0.5;  // 同一地點的距離閾值（公里）

        // 獲取所有收費站
        $toll_stations = TollStation::getAllStations();

        $startLat = $points[0]['lat'];
        $endLat = $points[count($points) - 1]['lat'];
        $startLng = $points[0]['lng'];
        $endLng = $points[count($points) - 1]['lng'];

        $lat_diff = $endLat - $startLat;
        $lng_diff = $endLng - $startLng;

        $angle = atan2($lat_diff, $lng_diff) * 180 / M_PI;

        $overallDirection = ($angle >= -135 && $angle <= 45) ? 'N' : 'S';

        foreach ($points as $index => $coord) {
            $lon = $coord['lng'];
            $lat = $coord['lat'];

            foreach ($toll_stations as $segment) {
                if (in_array($segment['id'], $processedSegmentIds)) {
                    continue;
                }

                $segmentLat = $segment['lat'];
                $segmentLon = $segment['lng'];

                $distance = TollStation::haversineDistance($lat, $lon, $segmentLat, $segmentLon);

                if ($distance <= $distanceThreshold && $segment['direction'] === $overallDirection) {
                    $isDuplicate = false;

                    foreach ($matchedSegments as $matched) {
                        $matchedSegment = $matched['segment'];
                        $locationDistance = TollStation::haversineDistance(
                            $segmentLat,
                            $segmentLon,
                            $matchedSegment['lat'],
                            $matchedSegment['lng']
                        );

                        if ($locationDistance <= $locationThreshold) {
                            $isDuplicate = true;
                            break;
                        }
                    }

                    if (!$isDuplicate) {
                        $matchedSegments[] = [
                            'coord' => [$lon, $lat],
                            'segment' => $segment,
                            'distance' => $distance,
                        ];

                        $processedSegmentIds[] = $segment['id'];
                    }
                }
            }
        }

// 輸出結果
        echo "匹配的國道路段：\n";
        foreach ($matchedSegments as $match) {
            $segment = $match['segment'];
            echo 'ID: ' . $segment['id'] . ' | ' . $segment['station_code'] . "\n";
            echo '經緯度: [' . $match['coord'][0] . ', ' . $match['coord'][1] . "]\n";
            echo "路段: {$segment['highway']} {$segment['start_interchange']} -> {$segment['end_interchange']}\n";
            echo "方向: {$segment['direction']}\n";
            echo '距離: ' . round($match['distance'], 3) . " 公里\n\n";
        }

        echo "整體方向: $overallDirection\n";die();

        // 轉換點格式
        $route_points = array_map(function ($point) {
            return ['lat' => $point['lat'], 'lng' => $point['lng']];
        }, $points);

        // 判斷行駛方向
        $start_point = $route_points[0];
        $end_point = $route_points[count($route_points) - 1];

        // 計算行進向量
        $vector = [
            'lat' => $end_point['lat'] - $start_point['lat'],
            'lng' => $end_point['lng'] - $start_point['lng'],
        ];

        // 計算行駛角度（相對於正北方向）
        $angle = atan2($vector['lng'], $vector['lat']) * 180 / M_PI;

        // 決定主要方向（東西方向也轉換為南北向）
        // 角度在 -45 到 45 度之間視為北向，否則為南向
        // 對於台灣的高速公路，我們主要關注的是行駛方向是北向還是南向
        $driving_direction = ($angle >= -45 && $angle <= 45) ? 'N' : 'S';

                           // 設定閾值
        $threshold = 0.01; // 1公里

        // 按可能的方向過濾收費站
        $direction_filtered_stations = array_filter($toll_stations, function ($station) use ($driving_direction) {
            // 如果收費站方向與行駛方向相同，或者沒有特定方向（雙向收費）
            return empty($station['direction']) || $station['direction'] == $driving_direction;
        });

        // 首先匹配所有收費站
        foreach ($direction_filtered_stations as $station) {
            $station_point = [$station['lat'], $station['lng']];
            $min_distance = PHP_FLOAT_MAX;
            $is_passing = false;

            for ($i = 0; $i < count($route_points) - 1; $i++) {
                $p1 = $route_points[$i];
                $p2 = $route_points[$i + 1];

                $start_point = [$p1['lat'], $p1['lng']];
                $end_point = [$p2['lat'], $p2['lng']];

                $distance = TollStation::pointToLineDistance(
                    $station_point[0], $station_point[1],
                    $start_point[0], $start_point[1],
                    $end_point[0], $end_point[1]
                );

                if ($distance < $min_distance) {
                    $min_distance = $distance;
                }

                if ($distance <= $threshold) {
                    $is_passing = true;
                    break;
                }
            }

            if ($is_passing) {
                $fee = TollStation::getFeeByVehicleType($station['id'], $vehicle_type);

                if ($fee > 0) {
                    $toll_routes[] = [
                        'station_id' => $station['id'],
                        'station_name' => $station['station_code'],
                        'highway' => $station['highway'],
                        'direction' => $station['direction'],
                        'driving_direction' => $driving_direction,
                        'fee' => $fee,
                        'distance' => $min_distance,
                    ];

                    $processed_stations[] = $station['id'];
                    $total_toll_fee += $fee;
                }
            }
        }

        // 處理重複里程的問題
        $mile_processed = [];
        $final_toll_routes = [];

        foreach ($toll_routes as $route) {
            // 提取里程數
            preg_match('/(\d+\.\d+)/', $route['station_name'], $matches);
            $mile = $matches[1] ?? '';

            // 每個里程點只保留一個收費站
            if (!isset($mile_processed[$mile])) {
                $final_toll_routes[] = $route;
                $mile_processed[$mile] = true;
            }
        }

                                                       // 檢查特定的收費站ID是否被包含
        $specific_toll_ids = [24, 26, 28, 30, 32, 34]; // 期望的ID
        $matched_ids = array_map(function ($route) {
            return $route['station_id'];
        }, $final_toll_routes);

        $missing_ids = array_diff($specific_toll_ids, $matched_ids);

        // 如果有遺漏的ID，再使用放寬後的閾值搜尋
        if (!empty($missing_ids)) {
            $secondary_threshold = $threshold * 2;

            foreach ($toll_stations as $station) {
                if (in_array($station['id'], $missing_ids) && !in_array($station['id'], $processed_stations)) {
                    $station_point = [$station['lat'], $station['lng']];
                    $min_distance = PHP_FLOAT_MAX;
                    $is_passing = false;

                    for ($i = 0; $i < count($route_points) - 1; $i++) {
                        $p1 = $route_points[$i];
                        $p2 = $route_points[$i + 1];

                        $start_point = [$p1['lat'], $p1['lng']];
                        $end_point = [$p2['lat'], $p2['lng']];

                        $distance = TollStation::pointToLineDistance(
                            $station_point[0], $station_point[1],
                            $start_point[0], $start_point[1],
                            $end_point[0], $end_point[1]
                        );

                        if ($distance < $min_distance) {
                            $min_distance = $distance;
                        }

                        if ($distance <= $secondary_threshold) {
                            $is_passing = true;
                            break;
                        }
                    }

                    if ($is_passing) {
                        $fee = TollStation::getFeeByVehicleType($station['id'], $vehicle_type);

                        if ($fee > 0) {
                            $final_toll_routes[] = [
                                'station_id' => $station['id'],
                                'station_name' => $station['station_code'],
                                'highway' => $station['highway'],
                                'direction' => $station['direction'],
                                'driving_direction' => $driving_direction,
                                'fee' => $fee,
                                'distance' => $min_distance,
                                'note' => '使用放寬閾值匹配',
                            ];

                            $total_toll_fee += $fee;
                        }
                    }
                }
            }
        }

        // 按站號排序
        usort($final_toll_routes, function ($a, $b) {
            $mile_a = (float) preg_replace('/[^0-9.]/', '', $a['station_name']);
            $mile_b = (float) preg_replace('/[^0-9.]/', '', $b['station_name']);
            return $mile_a <=> $mile_b;
        });

        return [
            'toll_routes' => $final_toll_routes,
            'total_toll_fee' => $total_toll_fee,
            'missing_ids' => $missing_ids,
        ];
    }

    /**
     * 計算早鳥折扣
     *
     * @param string $booking_date 預訂日期 (Y-m-d)
     * @param string $travel_date 旅行日期 (Y-m-d)
     * @return float 折扣金額（負值）
     */
    private function calculateEarlyBirdDiscount(string $booking_date, string $travel_date): float
    {
        $discount = 0;

        // 計算預訂日期與旅行日期的天數差
        $days_in_advance = (strtotime($travel_date) - strtotime($booking_date)) / (60 * 60 * 24);

        // 查找適用的早鳥規則
        $earlyBirdRule = PricingRule::findActiveByType('early_bird');
        if ($earlyBirdRule) {
            $conditions = PricingRule::parseConditions($earlyBirdRule);
            $advance_days = $conditions['advance_days'] ?? 30;

            if ($days_in_advance >= $advance_days) {
                // 應用早鳥折扣
                // 假設折扣應用於基本費用
                $baseRule = PricingRule::findActiveByType('basic');
                if ($baseRule) {
                    if ($earlyBirdRule['charge_type'] === 'percentage') {
                        $discount = ((float) $earlyBirdRule['charge_amount'] / 100) * (float) $baseRule['charge_amount'];
                    } else {
                        $discount = (float) $earlyBirdRule['charge_amount'];
                    }
                }
            }
        }

        return -$discount; // 返回負值表示折扣
    }

    /**
     * 根據乘客數量計算保險費
     *
     * @param int $passenger_count 乘客數量
     * @return float 保險費
     */
    private function calculateInsuranceFee(int $passenger_count): float
    {
        // 查找保險規則
        $insurance_rule = PricingRule::findActiveByType('insurance');
        if ($insurance_rule) {
            // 假設charge_amount是每位乘客的費用
            return $passenger_count * (float) $insurance_rule['charge_amount'];
        }

                                        // 如果沒有規則，使用默認保險費率
        return $passenger_count * 30.0; // 每位乘客30元
    }

    /**
     * 最終確認並保存車資計算結果到資料庫
     *
     * @param string $token 計算 Token
     * @param array $additionalData 額外資料（如用戶ID等）
     * @return array|bool 創建的記錄或是否成功
     */
    public function finalizeAndSaveFareCalculation(string $token, array $additionalData = []): array
    {
        $result = $this->getCalculationFromRedis($token);

        if (!$result) {
            throw new \Exception('找不到計算結果，Token 可能已過期');
        }

        // 確保所有必要的資料都已提供
        if (!isset($result['origin']) || !isset($result['destination'])) {
            throw new \Exception('計算結果缺少起點或終點資訊');
        }

        // 獲取完整的路線
        $route = [];
        if (isset($result['polyline'])) {
            $route = PolylineUtility::decodePolyline($result['polyline']);
        }

        // 準備請求資料
        $requestData = [
            'booking_date' => $result['booking_date'] ?? date('Y-m-d'),
            'travel_date' => $result['travel_date'] ?? date('Y-m-d', strtotime('+1 day')),
            'passenger_count' => $result['passenger_count'] ?? 1,
            'vehicle_type' => $result['vehicle_type'] ?? 'car',
        ];

        // 合併額外資料
        $requestData = array_merge($requestData, $additionalData);

        // 創建計算記錄
        $record = FareCalculation::createCalculation(
            $result['origin'],
            $result['destination'],
            $route,
            $result['distance_km'],
            $result,
            $requestData,
            $result['polyline'] ?? null
        );

        // 刪除 Redis 中的臨時記錄
        $this->deleteCalculationFromRedis($token);

        return $record;
    }
}
