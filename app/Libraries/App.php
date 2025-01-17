<?php
namespace App\Libraries;

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
            } else {
                $error = $f3->get('ERROR');
                $response = [
                    'status' => 'error',
                    'code' => $error['code'],
                    'message' => $error['text'],
                ];
                $http_code = $error['code'];
            }

            response()->error($response['code'], $response['message'], $response['details'] ?? [], $http_code)->send();
        });

        return $this;
    }

    public function run(): void
    {
        require_once '../vendor/autoload.php';

        \Middleware::instance()->run();

        $allowed_origins = [
            'https://local.fstu.com:5173',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed_origins)) {
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

        \App\Models\BaseModel::setConnection($db);
    }

    public function setupDpop(string $private_key_path, string $public_key_path): self
    {
        $this->dpop_handler = new DpopHandler($private_key_path, $public_key_path);

        // $this->dice->addRule('DpopHandler', [
        //     'sahred' => true,
        //     'constructParams' => [[$private_key_path, $public_key_path]],
        //     // 'substitutions' => ['Iterator' => $this->dpop_handler],
        // ]);

        // $this->dice->addRule(DpopHandler::class, ['constructParams' => [\Dice\Dice::INSTANCE => '$NamedPDOInstance']]);

        // $this->dice->addRule(DpopHandler::class, [
        //     'shared' => true, // Make it singleton
        //     'instanceOf' => DpopHandler::class,
        //     // 'constructParams' => [$this->dpop_handler], // Pass the injected handler
        //     'substitutions' => [
        //         DpopHandler::class => $this->dpop_handler,
        //     ],
        // ]);

        return $this;
    }

    public function addDpopMiddleware(): self
    {
        $this->setMiddleware('POST /auth/line_login', function ($f3, $params) {
            $dpop_proof = $f3->get('HEADERS.Dpop');
            if (!$dpop_proof) {
                throw new \App\Exceptions\DpopException(101);
            }

            if (!$this->dpop_handler->verifyRequestDpop($dpop_proof, $f3->get('VERB'), $f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('URI'))) {
                throw new \App\Exceptions\DpopException(120);
            }
        });

        $this->setMiddleware(['GET|POST|PUT|DELETE /auth/profile', 'GET|POST|PUT|DELETE /place/*'], function ($f3, $params) {
            $dpop_proof = $f3->get('HEADERS.Dpop');
            if (!$dpop_proof) {
                throw new \App\Exceptions\DpopException(101);
            }

            if (!$this->dpop_handler->validateAccessToken($dpop_proof, $f3->get('VERB'), $f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('URI'))) {
                throw new \App\Exceptions\DpopException(120);
            }
        });

        return $this;
    }

}
