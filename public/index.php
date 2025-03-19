<?php
require_once '../vendor/autoload.php';

$base_dir = __DIR__ . '/../';
$private_key_path = $base_dir . 'keys/private.pem';
$public_key_path = $base_dir . 'keys/public.pem';

$app = new \App\Libraries\App();
$app->initialize($base_dir, ['config/config.ini', 'config/routes.ini'])
    ->setupLogger($base_dir . 'storage/logs/', 'app', 'debug')
    ->setupContainer([
        'App\Libraries\Request' => ['shared' => true],
    ])
    ->setupDpop($private_key_path, $public_key_path)
    ->addDpopMiddleware()
    ->setupErrorHandler()
    ->run();
