<?php
namespace App\Libraries;

use App\Exceptions\RedisException;

class Redis
{
    private static $instance = null;
    private static $client;

    private static $config = [
        'host' => null,
        'port' => null,
        'password' => null,
        'database' => null,
        'prefix' => null,
    ];

    private function __construct()
    {
        try {
            self::loadConfig();

            self::$client = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => self::$config['host'],
                'port' => self::$config['port'],
                'password' => self::$config['password'],
                'database' => self::$config['database'],
                'prefix' => self::$config['prefix'],
            ]);
        } catch (\Exception $e) {
            throw new RedisException(300);
        }
    }

    private static function loadConfig()
    {
        self::$config = [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => getenv('REDIS_DATABASE') ?: 0,
            'prefix' => getenv('REDIS_PREFIX') ?: '',
        ];
    }

    private static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setConfig($config = [])
    {
        self::$config = array_merge(self::$config, $config);
    }

    public static function set($key, $value)
    {
        try {
            self::getInstance();
            return self::$client->set($key, $value);
        } catch (\Exception $e) {
            throw new RedisException(323);
        }
    }

    public static function get($key)
    {
        try {
            self::getInstance();
            return self::$client->get($key);
        } catch (\Exception $e) {
            throw new RedisException(324);
        }
    }

    public static function setex($key, $seconds, $value)
    {
        try {
            self::getInstance();
            return self::$client->setex($key, $seconds, $value);
        } catch (\Exception $e) {
            throw new RedisException(340);
        }
    }

    public static function delete($key)
    {
        try {
            self::getInstance();
            return self::$client->del($key);
        } catch (\Exception $e) {
            throw new RedisException(341);
        }
    }

    public static function incr($key)
    {
        try {
            self::getInstance();
            return self::$client->incr($key);
        } catch (\Exception $e) {
            throw new RedisException(342);
        }
    }

    public static function decr($key)
    {
        try {
            self::getInstance();
            return self::$client->decr($key);
        } catch (\Exception $e) {
            throw new RedisException(343);
        }
    }

    public static function hset($key, $field, $value)
    {
        try {
            self::getInstance();
            return self::$client->hset($key, field, $value);
        } catch (\Exception $e) {
            throw new RedisException(344);
        }
    }

    public static function hget($key, $field)
    {
        try {
            self::getInstance();
            return self::$client->hget($key, $field);
        } catch (\Exception $e) {
            throw new RedisException(344);
        }
    }

    public static function clear()
    {
        try {
            self::getInstance();
            return self::$client->flushall();
        } catch (\Exception $e) {
            throw new RedisException(323);
        }
    }
}
