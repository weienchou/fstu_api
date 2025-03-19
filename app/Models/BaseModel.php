<?php
namespace App\Models;

use App\Libraries\DB;

class BaseModel
{
    // Change to static property
    protected static $conn;

    public static $table;
    protected static $primary_key = 'id';
    protected static $fillable = [];
    protected static $has_timestamps = false;

    // Static method to set DB connection
    public static function setConnection(DB $conn)
    {
        static::$conn = $conn;
    }

    public static function findById(int $id)
    {
        $stmt = static::$conn->get(static::$table, '*', [
            static::$primary_key => (int) $id,
        ]);
        return $stmt;
    }

    public static function create(array $data)
    {
        $fields = array_intersect_key($data, array_flip(static::$fillable));

        if (static::$has_timestamps) {
            $current_time = self::getCurrentTimestamp();
            $fields['created_at'] = $current_time;
            $fields['updated_at'] = $current_time;
        }

        static::$conn->insert(static::$table, $fields);
        return static::findById(static::$conn->id());
    }

    public static function update(int $id, array $data)
    {
        $fields = array_intersect_key($data, array_flip(static::$fillable));

        if (static::$has_timestamps) {
            $current_time = self::getCurrentTimestamp();
            $fields['updated_at'] = $current_time;
        }

        $data = static::$conn->update(static::$table, $fields, [
            static::$primary_key => $id,
        ]);

        return $data->rowCount() > 0;
    }

    public static function delete(int $id, bool $force = false)
    {
        if ($force === false) {
            return static::update($id, ['status' => -1]);
        }

        $data = static::$conn->delete(static::$table, [
            static::$primary_key => $id,
        ]);

        return $data->rowCount() > 0;
    }

    public static function createOrUpdate(array $conditions, array $data)
    {
        $fields = array_intersect_key($data, array_flip(static::$fillable));

        $existing = static::$conn->get(static::$table, '*', $conditions);

        if ($existing) {
            if (static::$has_timestamps) {
                $fields['updated_at'] = self::getCurrentTimestamp();
            }
            static::$conn->update(static::$table, $fields, $conditions);
            return static::findById($existing[static::$primary_key]);
        } else {
            if (static::$has_timestamps) {
                $current_time = self::getCurrentTimestamp();
                $fields['created_at'] = $current_time;
                $fields['updated_at'] = $current_time;
            }
            $fields = array_merge($conditions, $fields);
            static::$conn->insert(static::$table, $fields);
            return static::findById(static::$conn->id());
        }
    }

    protected static function getCurrentTimestamp()
    {
        return date('Y-m-d H:i:s');
    }

}
