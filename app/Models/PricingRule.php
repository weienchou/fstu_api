<?php
namespace App\Models;

class PricingRule extends BaseModel
{
    public static $table = 'pricing_rules';
    protected static $primary_key = 'id';
    protected static $fillable = [
        'rule_name',
        'rule_type',
        'is_global',
        'apply_cities',
        'charge_amount',
        'charge_type',
        'time_constraint',
        'vehicle_types',
        'conditions',
        'priority',
        'status',
        'valid_from',
        'valid_until',
        'created_by',
        'approved_by',
        'approved_at',
    ];
    protected static $has_timestamps = true;

    /**
     * 根據規則類型查找活躍的定價規則
     *
     * @param string $rule_type 規則類型
     * @return array|null 找到的規則資料
     */
    public static function findActiveByType(string $rule_type)
    {
        return static::$conn->get(static::$table, '*', [
            'rule_type' => $rule_type,
            'status' => 'active',
            'ORDER' => ['priority' => 'DESC'],
        ]);
    }

    /**
     * 根據規則類型查找所有規則
     *
     * @param string $rule_type 規則類型
     * @return array 符合條件的規則列表
     */
    public static function findAllByType(string $rule_type)
    {
        return static::$conn->select(static::$table, '*', [
            'rule_type' => $rule_type,
            'ORDER' => ['priority' => 'DESC'],
        ]);
    }

    /**
     * 查找適用於特定城市的定價規則
     *
     * @param string $rule_type 規則類型
     * @param int $city_id 城市ID
     * @return array|null 找到的規則資料
     */
    public static function findForCity(string $rule_type, int $city_id)
    {
        // 首先嘗試查找特定城市規則
        $query = 'SELECT * FROM ' . static::$table . "
                  WHERE rule_type = :rule_type
                  AND status = 'active'
                  AND (is_global = 1 OR JSON_CONTAINS(apply_cities, :city_id))
                  ORDER BY priority DESC
                  LIMIT 1";

        $stmt = static::$conn->query($query, [
            'rule_type' => $rule_type,
            'city_id' => json_encode($city_id),
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 檢查規則是否存在
     *
     * @param string $rule_type 規則類型
     * @return bool 如果規則存在則返回true
     */
    public static function ruleExists(string $rule_type)
    {
        $count = static::$conn->count(static::$table, [
            'rule_type' => $rule_type,
        ]);

        return $count > 0;
    }

    /**
     * 解析JSON條件字段
     *
     * @param array $rule 規則資料
     * @return array 解析後的條件
     */
    public static function parseConditions(array $rule)
    {
        if (isset($rule['conditions']) && !empty($rule['conditions'])) {
            if (is_string($rule['conditions'])) {
                return json_decode($rule['conditions'], true) ?? [];
            } elseif (is_array($rule['conditions'])) {
                return $rule['conditions'];
            }
        }

        return [];
    }

    /**
     * 創建新的定價規則
     *
     * @param string $rule_name 規則名稱
     * @param string $rule_type 規則類型
     * @param float $charge_amount 收費金額
     * @param string $charge_type 收費類型(fixed/percentage)
     * @param int $priority 優先級
     * @param array $conditions 條件 (可選)
     * @param bool $is_global 是否全局 (可選)
     * @param array $apply_cities 適用城市 (可選)
     * @return array 創建後的規則資料
     */
    public static function createRule(
        string $rule_name,
        string $rule_type,
        float $charge_amount,
        string $charge_type,
        int $priority,
        array $conditions = null,
        bool $is_global = true,
        array $apply_cities = null
    ) {
        $data = [
            'rule_name' => $rule_name,
            'rule_type' => $rule_type,
            'is_global' => $is_global ? 1 : 0,
            'charge_amount' => $charge_amount,
            'charge_type' => $charge_type,
            'priority' => $priority,
            'status' => 'active',
            'valid_from' => date('Y-m-d H:i:s'),
            'created_by' => 1, // 假設管理員ID為1
        ];

        if ($conditions !== null) {
            $data['conditions'] = json_encode($conditions);
        }

        if ($apply_cities !== null) {
            $data['apply_cities'] = json_encode($apply_cities);
        }

        return static::create($data);
    }
}
