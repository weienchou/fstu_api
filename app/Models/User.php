<?php
namespace App\Models;

class User extends BaseModel
{
    public static $table = 'users';
    public static $table_rel = 'user_roles';
    protected static $has_timestamps = true;

    protected static $fillable = [
        'name',
        'email',
        'mobile',
        'avatar',
        'status',
    ];

    const STATUS_DELETED = -1;
    const STATUS_NOT_VERIFIED = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_BLOCKED = 2;

    public static function processLineLogin($profile)
    {
        $existing_auth = UserAuth::findByIdentifier($profile['userId'], UserAuth::TYPE_LINE);

        if (!$existing_auth) {
            $user = static::create([
                'name' => $profile['displayName'],
                'avatar' => $profile['pictureUrl'],
                'status' => self::STATUS_ACTIVE,
            ]);

            UserAuth::create([
                'user_id' => $user['id'],
                'auth_type' => UserAuth::TYPE_LINE,
                'identifier' => $profile['userId'],
            ]);

            Role::assignToUser($user['id']);

            return $user;
        }

        self::update($existing_auth['user_id'], [
            'name' => $profile['displayName'],
            'avatar' => $profile['pictureUrl'],
        ]);

        return self::findById($existing_auth['user_id']);
    }
}
