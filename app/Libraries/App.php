<?php
namespace App\Libraries;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class App
{
    private $f3;
    private $dice;
    private $container_rules = [];
    private $dpop_handler;

    public function __construct()
    {
        $this->f3 = \Base::instance();
        $this->dice = new \Dice\Dice();
    }

    public function initialize(string $base_dir, array $config_files = []): self
    {
        $this->loadEnvFile($base_dir);
        $this->loadConfigs(array_map(fn($file) => $base_dir . $file, $config_files));
        return $this;
    }

    public function setupLogger(string $log_path, string $channel_name = 'app', string $log_level = 'debug'): self
    {
        // 創建 Logger 實例
        $logger = new Logger($channel_name);
        $output_format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output_format);

        // 檢查日誌級別是否有效，如果無效則記錄警告
        $level = strtolower($log_level);
        $valid_levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        if (!in_array($level, $valid_levels)) {
            $log_level = 'debug'; // 使用預設值
            $logger->warning("無效的日誌層級: {$level}，使用預設值 DEBUG");
        }

        $rotating_handler = new RotatingFileHandler(
            $log_path . '/' . $channel_name . '.log',
            7,
            $this->getLogLevel($log_level)
        );
        $rotating_handler->setFormatter($formatter);
        $logger->pushHandler($rotating_handler);

        // 將 Logger 註冊到 Dice 容器，設為共享實例
        $this->container_rules[Logger::class] = [
            'shared' => true,
            'instanceOf' => Logger::class,
            'constructParams' => [$channel_name],
            'call' => [
                ['pushHandler', [$rotating_handler]],
            ],
        ];
        $this->dice = $this->dice->addRules($this->container_rules);

        // 將 Logger 放入 F3 hive，以便全局存取（可選）
        $this->f3->set('LOGGER', $logger);

        return $this;
    }

    private function getLogLevel(string $level): int
    {
        $level = strtolower($level);
        $levels = [
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
        ];

        if (!isset($levels[$level])) {
            // 注意：這裡不應該提早使用 dice 容器獲取 Logger
            // 改為返回預設值，並在 setupLogger 中稍後記錄警告
            return Logger::DEBUG;
        }
        return $levels[$level];
    }

    public function getLogger(): Logger
    {
        return $this->dice->create(Logger::class);
    }

    public function loadConfigs(array $config_files): self
    {
        foreach ($config_files as $file) {
            $this->f3->config($file);
        }
        return $this;
    }

    public function setupContainer(array $rules): self
    {
        $this->container_rules = array_merge($this->container_rules, $rules);
        $this->dice = $this->dice->addRules($this->container_rules);
        $this->f3->set('CONTAINER', fn($class) => $this->dice->create($class));
        return $this;
    }

    public function loadEnvFile(string $path): self
    {
        $dotenv = \Dotenv\Dotenv::createImmutable($path);
        $dotenv->safeLoad();
        return $this;
    }

    public function setMiddleware(array | string $pattern, callable $handler): self
    {
        \Middleware::instance()->before($pattern, $handler);
        return $this;
    }

    public function setupErrorHandler(): self
    {
        $this->f3->set('ONERROR', function ($f3) {
            $logger = $this->dice->create(Logger::class);
            $error = $f3->get('EXCEPTION');

            if ($error instanceof \App\Exceptions\BaseException) {
                $response = $error->getErrorResponse();
                $http_code = $error->getHttpCode();
                $logger->error('Application exception: ' . $error->getMessage(), [
                    'code' => $response['code'],
                    'http_code' => $http_code,
                    'details' => $response['details'] ?? [],
                ]);
            } else {
                $error = $f3->get('ERROR');
                $response = [
                    'status' => 'error',
                    'code' => $error['code'],
                    'message' => $error['text'],
                ];
                $http_code = $error['code'];
                $logger->error('System error: ' . $error['text'], [
                    'code' => $error['code'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'trace' => $error['trace'],
                ]);
            }

            response()->error($response['code'], $response['message'], $response['details'] ?? [], $http_code)->send();
        });
        return $this;
    }

    public function run(): void
    {
        $logger = $this->dice->create(Logger::class);
        $logger->info('Application starting');
        \Middleware::instance()->run();

        $allowed_origins = ['https://local.fstu.com:5173', 'https://fstu.wuts.cc'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed_origins)) {
            $this->f3->set('CORS', [
                'origin' => $origin,
                'headers' => 'Access-Control-Allow-Origin, X-Requested-With, X-Requested-From, X-Requested-Token, Content-Type, Content-Range, Content-Disposition, Origin, Accept, Authorization, Dpop',
                'ttl' => 86400,
                'expose' => true,
                'credentials' => false,
            ]);
        }

        $this->setupDB();
        $this->setupRedis();
        \Falsum\Run::handler();
        $this->f3->run();
        $logger->info('Application finished');
    }

    public function setupDB(): void
    {
        $logger = $this->dice->create(Logger::class);
        $db = new \App\Libraries\DB([
            'type' => 'mysql',
            'host' => $this->getEnv('DB_HOST', 'localhost'),
            'database' => $this->getEnv('DB_DATABASE'),
            'username' => $this->getEnv('DB_USERNAME'),
            'password' => $this->getEnv('DB_PASSWORD'),
            'prefix' => $this->getEnv('DB_PREFIX', ''),
        ]);

        $logger->info('Database connection established', [
            'host' => $this->getEnv('DB_HOST'),
            'database' => $this->getEnv('DB_DATABASE'),
        ]);

        \App\Models\BaseModel::setConnection($db);
    }

    public function setupRedis(array $config = []): self
    {
        $logger = $this->dice->create(Logger::class);

        try {
            // 設置 Redis 配置
            // $redis_config = [
            //     'host' => $this->getEnv('REDIS_HOST', 'redis'),
            //     'port' => $this->getEnv('REDIS_PORT', 6379),
            //     'password' => $this->getEnv('REDIS_PASSWORD'),
            //     'database' => $this->getEnv('REDIS_DATABASE', 0),
            //     'prefix' => $this->getEnv('REDIS_PREFIX', ''),
            // ];

            // // 合併傳入的配置（如果有）
            // if (!empty($config)) {
            //     $redis_config = array_merge($redis_config, $config);
            // }

            // // 設置 Redis 配置
            // \App\Libraries\Redis::setConfig($redis_config);

            // 將 Redis 類註冊到 Dice 容器
            $this->container_rules['App\Libraries\Redis'] = [
                'shared' => true,
                'instanceOf' => 'App\Libraries\Redis',
            ];
            $this->dice = $this->dice->addRules($this->container_rules);

            // $logger->info('Redis connection established', [
            //     'host' => $redis_config['host'],
            //     'port' => $redis_config['port'],
            //     'database' => $redis_config['database'],
            // ]);

        } catch (\Exception $e) {
            $logger->error('Failed to establish Redis connection', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // 如果 Redis 連接失敗，可以選擇是否拋出異常
            // throw new \RuntimeException('無法建立 Redis 連接');
        }

        return $this;
    }

    private function getEnv(string $key, $default = null)
    {
        $value = env($key);
        if ($value === null) {
            $logger = $this->dice->create(Logger::class);
            $logger->warning("環境變數 {$key} 未設定，使用預設值", ['default' => $default]);
        }
        return $value ?? $default;
    }

    public function setupDpop(string $private_key_path, string $public_key_path): self
    {
        $logger = $this->dice->create(Logger::class);
        if (!file_exists($private_key_path) || !file_exists($public_key_path)) {
            $logger->error('DPoP 金鑰檔案不存在', [
                'private_key_path' => $private_key_path,
                'public_key_path' => $public_key_path,
            ]);
            throw new \RuntimeException('無法找到 DPoP 金鑰檔案');
        }

        $this->dpop_handler = new DpopHandler($private_key_path, $public_key_path);
        $this->container_rules['App\Libraries\DpopHandler'] = [
            'shared' => true,
            'constructParams' => [$private_key_path, $public_key_path],
        ];
        $this->dice = $this->dice->addRules($this->container_rules);

        $logger->info('DPoP handler initialized', [
            'private_key_path' => $private_key_path,
            'public_key_path' => $public_key_path,
        ]);

        return $this;
    }

    private function verifyDpopProof($f3, $dpop_proof, $verb, $uri, $validation_method, $log_context = []): void
    {
        $logger = $this->dice->create(Logger::class);
        if (!$dpop_proof) {
            $logger->warning('DPoP proof missing', $log_context);
            throw new \App\Exceptions\DpopException(101);
        }

        if (!$this->dpop_handler->$validation_method($dpop_proof, $verb, $uri)) {
            $logger->warning('Invalid DPoP proof', $log_context);
            throw new \App\Exceptions\DpopException(120);
        }

        $logger->debug('DPoP verification successful', $log_context);
    }

    public function addDpopMiddleware(): self
    {
        $this->setMiddleware('POST /auth/line_login', function ($f3, $params) {
            $dpop_proof = $f3->get('HEADERS.Dpop');
            $uri = $f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('URI');
            $this->verifyDpopProof($f3, $dpop_proof, $f3->get('VERB'), $uri, 'verifyRequestDpop', [
                'uri' => $uri,
                'method' => $f3->get('VERB'),
            ]);
        });

        $this->setMiddleware(['GET|POST|PUT|DELETE /auth/profile', 'GET|POST|PUT|DELETE /place/*'], function ($f3, $params) {
            $dpop_proof = $f3->get('HEADERS.Dpop');
            $uri = $f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('URI');
            $this->verifyDpopProof($f3, $dpop_proof, $f3->get('VERB'), $uri, 'validateAccessToken', [
                'uri' => $uri,
                'method' => $f3->get('VERB'),
            ]);
        });

        return $this;
    }
}
