<?php
namespace App\Models;

class Role extends BaseModel
{
    public static $table = 'roles';

    protected static $fillable = [
        'name',
    ];

    const SUPER_ADMIN = 1;
    const ADMIN = 2;
    const DRIVER = 3;
    const USER = 4;

    public static function getUsers(int $role_id = self::USER)
    {
        $stmt = static::$conn->select(User::$table . '(u)', [
            '[><]' . User::$table_rel . '(ur)' => ['id' => 'ur.user_id'],
        ], 'u.*', [
            'u.status[!]' => User::STATUS_DELETED,
            'ur.role_id' => $role_id,
        ]);

        return $stmt;
    }

    // Assign role to user
    public static function assignToUser(int $user_id, int $role_id = self::USER)
    {
        static::$conn->insert(User::$table_rel, [
            'user_id' => $user_id,
            'role_id' => $role_id,
        ]);

        return static::$conn->id() > 0;
    }

    // Remove role from user
    public static function removeFromUser(int $user_id, int $role_id = self::USER)
    {
        $data = static::$conn->delete(User::$table_rel, [
            'user_id' => $user_id,
            'role_id' => $role_id,
        ]);

        return $data->rowCount() > 0;
    }
}
