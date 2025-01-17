<?php
namespace App\Models;

class UserAuth extends BaseModel
{
    public static $table = 'user_auth';
    protected static $has_timestamps = true;

    protected static $fillable = [
        'user_id',
        'auth_type',
        'identifier',
        'credential',
    ];

    const TYPE_PASSWORD = 1;
    const TYPE_LINE = 1;

    public static function findByIdentifier($identifier, $type = self::TYPE_PASSWORD)
    {
        $stmt = static::$conn->get(static::$table, '*', [
            'identifier' => $identifier,
            'auth_type' => $type,
        ]);
        return $stmt;
    }

    public static function getUser()
    {
        return User::findById(static::$user_id);
    }
}
