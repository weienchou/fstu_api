<?php
namespace App\Libraries;

class DB extends \Medoo\Medoo
{
    public function __construct(array $config)
    {
        parent::__construct([
            'type' => $config['type'],
            'host' => $config['host'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'prefix' => $config['prefix'],

            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',

            'option' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_PERSISTENT => true,
            ],
        ]);
    }
}
