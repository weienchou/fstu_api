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
    private $logger;

    public function __construct()
    {
        $this->f3 = \Base::instance();
        $this->dice = new \Dice\Dice();
    }

    /**
     * Setup Monolog logger
     *
     * @param string $log_path Path to store log files
     * @param string $channel_name Logger channel name
     * @param string $log_level Minimum log level to record
     * @return self
     */
    public function setupLogger(string $log_path, string $channel_name = 'app', string $log_level = 'debug'): self
    {
        // Create logger instance
        $this->logger = new Logger($channel_name);

        // Define log format
        $output_format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output_format);

        // Add rotating file handler (keeps logs for 7 days)
        $rotating_handler = new RotatingFileHandler(
            $log_path . '/' . $channel_name . '.log',
            7,
            $this->getLogLevel($log_level)
        );
        $rotating_handler->setFormatter($formatter);
        $this->logger->pushHandler($rotating_handler);

        // Register logger in container for dependency injection
        $this->container_rules[Logger::class] = [
            'shared' => true,
            'instanceOf' => Logger::class,
            'substitutions' => [
                Logger::class => $this->logger,
            ],
        ];
        $this->dice = $this->dice->addRules($this->container_rules);

        // Add logger to F3 hive for global access
        $this->f3->set('LOGGER', $this->logger);

        return $this;
    }

    /**
     * Convert string log level to Monolog constant
     *
     * @param string $level Log level string
     * @return int Monolog log level constant
     */
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

        return $levels[$level] ?? Logger::DEBUG;
    }

    /**
     * Get logger instance
     *
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
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

        $this->f3->set('CONTAINER', function ($class) {
            return $this->dice->create($class);
        });

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
            $error = $f3->get('EXCEPTION');

            if ($error instanceof \App\Exceptions\BaseException) {
                $response = $error->getErrorResponse();
                $http_code = $error->getHttpCode();

                // Log exception with contextual data
                if ($this->logger) {
                    $this->logger->error('Application exception: ' . $error->getMessage(), [
                        'code' => $response['code'],
                        'http_code' => $http_code,
                        'details' => $response['details'] ?? [],
                    ]);
                }
            } else {
                $error = $f3->get('ERROR');
                $response = [
                    'status' => 'error',
                    'code' => $error['code'],
                    'message' => $error['text'],
                ];
                $http_code = $error['code'];

                // Log system error
                if ($this->logger) {
                    $this->logger->error('System error: ' . $error['text'], [
                        'code' => $error['code'],
                        'file' => $error['file'],
                        'line' => $error['line'],
                        'trace' => $error['trace'],
                    ]);
                }
            }

            response()->error($response['code'], $response['message'], $response['details'] ?? [], $http_code)->send();
        });

        return $this;
    }

    public function run(): void
    {
        require_once '../vendor/autoload.php';

        if ($this->logger) {
            $this->logger->info('Application starting');
        }

        \Middleware::instance()->run();

        $allowed_origins = [
            'https://local.fstu.com:5173',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed_origins) /*&& $this->f3->hive['VERB'] == 'OPTIONS'*/) {
            $this->f3->set('CORS', [
                'origin' => '*',
                'headers' => 'Access-Control-Allow-Origin, X-Requested-With,X-Requested-From, X-Requested-Token, Content-Type, Content-Range, Content-Disposition, Origin, Accept, Authorization, Dpop',
                'ttl' => '86400',
                'expose' => true,
                'credentials' => false,
            ]);
        }

        $this->setupDB();

        \Falsum\Run::handler();

        $this->f3->run();

        if ($this->logger) {
            $this->logger->info('Application finished');
        }
    }

    public function setupDB()
    {
        $db = new \App\Libraries\DB([
            'type' => 'mysql',
            'host' => env('DB_HOST'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'prefix' => env('DB_PREFIX'),
        ]);

        if ($this->logger) {
            $this->logger->info('Database connection established', [
                'host' => env('DB_HOST'),
                'database' => env('DB_DATABASE'),
            ]);
        }

        \App\Models\BaseModel::setConnection($db);
    }

    public function setupDpop(string $private_key_path, string $public_key_path): self
    {
        $this->dpop_handler = new DpopHandler($private_key_path, $public_key_path);

        if ($this->logger) {
            $this->logger->info('DPoP handler initialized', [
                'private_key_path' => $private_key_path,
                'public_key_path' => $public_key_path,
            ]);
        }

        return $this;
    }

    public function addDpopMiddleware(): self
    {
        $this->setMiddleware('POST /auth/line_login', function ($f3, $params) {
            $dpop_proof = $f3->get('HEADERS.Dpop');
            if (!$dpop_proof) {
                if ($this->logger) {
                    $this->logger->warning('DPoP proof missing for line login request');
                }
                throw new \App\Exceptions\DpopException(101);
            }

            if (!$this->dpop_handler->verifyRequestDpop($dpop_proof, $f3->get('VERB'), $f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('URI'))) {
                if ($this->logger) {
                    $this->logger->warning('Invalid DPoP proof for line login request');
                }
                throw new \App\Exceptions\DpopException(120);
            }

            if ($this->logger) {
                $this->logger->info('DPoP verification successful for line login');
            }
        });

        $this->setMiddleware(['GET|POST|PUT|DELETE /auth/profile', 'GET|POST|PUT|DELETE /place/*'], function ($f3, $params) {
            $dpop_proof = $f3->get('HEADERS.Dpop');
            if (!$dpop_proof) {
                if ($this->logger) {
                    $this->logger->warning('DPoP proof missing for protected endpoint', [
                        'uri' => $f3->get('URI'),
                        'method' => $f3->get('VERB'),
                    ]);
                }
                throw new \App\Exceptions\DpopException(101);
            }

            if (!$this->dpop_handler->validateAccessToken($dpop_proof, $f3->get('VERB'), $f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('URI'))) {
                if ($this->logger) {
                    $this->logger->warning('Invalid access token in DPoP proof', [
                        'uri' => $f3->get('URI'),
                        'method' => $f3->get('VERB'),
                    ]);
                }
                throw new \App\Exceptions\DpopException(120);
            }

            if ($this->logger) {
                $this->logger->debug('DPoP access token validation successful', [
                    'uri' => $f3->get('URI'),
                    'method' => $f3->get('VERB'),
                ]);
            }
        });

        return $this;
    }
}
