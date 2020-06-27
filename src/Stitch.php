<?php

use Medoo\Medoo;
use Dotenv\Dotenv;
use League\Plates\Engine as Plates;
use \Delight\Auth\Auth as Auth;

/**
 * Stitch: A micro framework that wraps FastRoute, Plates, and Medoo.
 */
class Stitch
{

	/** @var Medoo\Medoo $database Medoo Database instance */
	static public Medoo $database;
	/** @var Dotenv\Dotenv $env Dotenv instance */
	static public Dotenv $dotenv;
	/** @var League\Plates\Engine $templates Plates templates */
	static public Plates $templates;
	/** @var \Delight\Auth\Auth $auth Delight's PHP-Auth */
	static public Auth $auth;

	/** @var array $translations array where the translation files will be loaded into */
	static protected array $translations;
	/** @var array $routesArray FastRoute routes as array before processing */
	static protected  array $routesArray = array();
	/** @var callable $routes FastRoute routes function */
	protected $routes;

	static protected $errorHandler;

	/**
	 * Create a new Stitch instance
	 *
	 * @param string $dataDir directory that contains the .env file
	 **/
	public function __construct(string $dataDir)
	{
		$_ENV['DATA'] = $dataDir;

		// Load the env variables
		static::$dotenv = Dotenv::createImmutable($dataDir);
		static::$dotenv->load();        

		// Initialize database if set
		if (isset($_ENV['DB_DATABASE_TYPE'])) {
			foreach($_ENV as $name => $value) {
				if (strpos($name, 'DB_') !== 0 or empty($value))
					continue;
				$name = strtolower(explode('DB_', $name, 2)[1]);
				$config[$name] = $value;
			}
			static::$database = new Medoo($config);
			if (isset($_ENV['USE_AUTHENTICATION'])) {
				// Setup the database for authentication automatically
				if (isset($_ENV['VENDOR'])) {
					switch (strtolower($_ENV['DB_DATABASE_TYPE'])) {
						case 'mysql':
						case 'mariadb':
							$type = 'MySQL';
						break;
						case 'postgresql':
							$type = 'PostgreSQL';
						break;
						case 'sqlite':
						case 'sqlite3':
							$type = 'SQLite';
						break;	
					}
					if (isset($type)) {
						static::$database->query(file_get_contents($_ENV['VENDOR'] . "/delight-im/auth/Database/$type.sql"));
					}
				}

				static::$auth = new Auth(static::$database->pdo);
			}
		}
		
		// Create new Plates instance if set
		if (isset($_ENV['TEMPLATES'])) {
			static::$templates = Plates::create($_ENV['TEMPLATES']);
		}
		// Autoload controllers automatically
		if (isset($_ENV['CONTROLLERS'])) {
			spl_autoload_register(function ($class) {
				include_once $_ENV['CONTROLLERS'] . $class . '.php';
			});
		}
		// Automatically include routes' definitions
		if (isset($_ENV['ROUTES'])) {
			foreach (glob($_ENV['ROUTES'] . '*.php') as $file) {
				include_once $file;
			}
		}
	}

	/**
	 * Set the handler for the 404 and 405 errors
	 *
	 * @param $handler Can be a function or a string
	 **/
	static public function setErrorHandler($handler)
	{
		static::$errorHandler = $handler;
	}

	/**
	 * Adds the routes set up with addRoute() to FastRoute dispatcher.
	 *
	 * @param string $method HTTP method for the route
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	protected function addRoutes()
	{
		$this->routes = function (FastRoute\RouteCollector $r) {
			foreach (static::$routesArray as $data) {
				$r->addRoute($data['method'], $data['route'], $data['handler']);
			}
		};
	}

	/**
	 * Add a route with custom method
	 *
	 * @param string $method HTTP method for the route
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function addRoute(string $method, string $route, $handler)
	{
		static::$routesArray[] = ['method' => $method, 'route' => $route, 'handler' => $handler];
	}

	/**
	 * GET request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function get(string $route, $handler) { static::addRoute('GET', $route, $handler); }
	/**
	 * POST request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function post(string $route, $handler) { static::addRoute('POST', $route, $handler); }
	/**
	 * PUT request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function put(string $route, $handler) { static::addRoute('PUT', $route, $handler); }
	/**
	 * DELETE request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function delete(string $route, $handler) { static::addRoute('DELETE', $route, $handler); }
	/**
	 * HEAD request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function head(string $route, $handler) { static::addRoute('HEAD', $route, $handler); }
	/**
	 * PATCH request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	static public function patch(string $route, $handler) { static::addRoute('PATCH', $route, $handler); }


	/**
	 * Proccess a handler
	 *
	 * @param mixed $handler Description
	 * @throws Exception Handler must return an array or be a string.
	 **/
	public function handle($handler, array $vars, string $httpMethod)
	{
		if (is_callable($handler)) {
			$result = call_user_func($handler, $vars);
			// If the handler returned a string not array throw an exception
			if (!array($result)) {
				throw new Exception('Handler must return an array or be a string.', 1);
			}
		} elseif (is_string($handler)) {
			// If the handler is not a callback but a string, print it out as regular HTML
			die($handler);
		}

		if (isset($result['headers'])) {
			foreach ($result['headers'] as $name => $value) {
				header("$name: $value");
			}
		}

		if (isset($result['redirect'])) {
			header('Location: ' . $result['redirect']);
		}

		if (isset($result['status_code'])) {
			http_response_code($result['status_code']);
		}


		if (isset($result['view'])) {
			$output = static::$templates->render($_ENV['VIEWS'] . '/' . $result['view'], $result['data'] ?? []);
		} elseif (isset($result['body'])) {
			if (is_array($result['body'])) {
				header('content-type: application/json');
				$output = json_encode($result['body']);
			} elseif (is_string($result['body'])) {
				$output = $result['body'];
			}
		}

		if (!empty($output) and $httpMethod != 'HEAD') {
			echo $output;
		}
	}

	/**
	 * This will run the dispatcher with the added routes using addRoutes()
	 * @throws Exception Handler must return an array or be a string.
	 **/
	public function run()
	{
		$this->addRoutes();
		if (isset($_ENV['CACHE_FILE'])) {
			$dispatcher = FastRoute\cachedDispatcher($this->routes, [
				'cacheFile' => $_ENV['CACHE_FILE'], /* required */
				'cacheDisabled' =>  $_ENV['CACHE_DISABLED']
			]);
		} else {
			$dispatcher = FastRoute\simpleDispatcher($this->routes);
		}

		// Fetch method and URI from somewhere
		$httpMethod = $_SERVER['REQUEST_METHOD'];
		$uri = $_SERVER['REQUEST_URI'];

		// Strip query string (?foo=bar) and decode URI
		if (false !== $pos = strpos($uri, '?')) {
			$uri = substr($uri, 0, $pos);
		}

		$uri = rawurldecode($uri);
		$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
		switch ($routeInfo[0]) {
			case FastRoute\Dispatcher::NOT_FOUND:
				// ... 404 Not Found
				http_response_code(404);
				$this->handle(static::$errorHandler, ['code' => 404, 'full' => 'Not Found'], $httpMethod);
				break;
			case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
				$allowedMethods = $routeInfo[1];
				// ... 405 Method Not Allowed
				http_response_code(405);
				$this->handle(static::$errorHandler, ['code' => 405, 'full' => 'Method Not Allowed'], $httpMethod);
				break;
			case FastRoute\Dispatcher::FOUND:
				$handler = $routeInfo[1];
				$vars = $routeInfo[2];

				// Handle JSON data
				if (count($_POST) === 1) {
					$j = array_key_first($_POST);
					if (empty($_POST[$j]) and (strpos($j, '{') === 0 or strpos($j, '[') === 0)) {
						$_POST = json_decode($j, true);
					}
				}

				// Proccess the handler
				$this->handle($handler, $vars, $httpMethod);

				break;
		}
	}
}
