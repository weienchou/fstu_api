<?php
require_once '../vendor/autoload.php';

$app = new \App\Libraries\App();
$app->setupLogger(__DIR__ . '/../storage/logs/', 'app', 'debug')
    ->loadConfigs(['../config/config.ini', '../config/routes.ini'])
    ->setupContainer([
        'App\Libraries\Request' => ['shared' => true],
        'App\Libraries\DpopHandler' => [
            'shared' => true,
            'constructParams' => [
                __DIR__ . '/../keys/private.pem',
                __DIR__ . '/../keys/public.pem',
            ],
        ],
        // 'App\Libraries\DB' => [
        //     'shared' => true,
        //     'constructParams' => [[
        //         'type' => 'mysql',
        //         'host' => env('DB_HOST'),
        //         'database' => env('DB_DATABASE'),
        //         'username' => env('DB_USERNAME'),
        //         'password' => env('DB_PASSWORD'),
        //         'prefix' => env('DB_PREFIX'),
        //     ]],
        // ],
    ])
    ->loadEnvFile(__DIR__ . '/../')
    ->setupDpop(
        __DIR__ . '/../keys/private.pem',
        __DIR__ . '/../keys/public.pem'
    )
    ->addDpopMiddleware()
    ->setupErrorHandler()
    ->run();
