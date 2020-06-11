<?php

use Medoo\Medoo;
use Dotenv\Dotenv;
use League\Plates\Engine;
use FastRoute;

/**
 * Stitch: A micro framework that wraps FastRoute, Plates, and Medoo.
 */
class Stitch
{

	/** @var Medoo\Medoo $database Medoo Database instance */
	public $database;
	/** @var Dotenv\Dotenv $env Dotenv instance */
	public $dotenv;
	/** @var League\Plates\Engine $plates Plates templates */
	public $plates;

	/** @var array $routesArray FastRoute routes as array before processing */
	protected $routesArray = array();
	/** @var callable $routes FastRoute routes function */
	protected $routes;

	/**
	 * Create a new Stitch instance
	 *
	 * @param string $dataDir directory that contains the .env file
	 **/
	public function __construct(string $dataDir)
	{
		$_ENV['DATA'] = $dataDir;

		// Load the env variables
		$this->dotenv = Dotenv::createImmutable($dataDir);
		$this->dotenv->load();        

		// Initialize database if set
		if (isset($_ENV['DB_DATABASE_TYPE'])) {
			foreach($_ENV as $name => $value) {
				if (strpos($name, 'DB_') !== 0 or empty($value))
					continue;
				$name = strtolower(explode('DB_', $name, 2)[1]);
				$config[$name] = $value;
			}
			$this->database = new Medoo($config);
		}
		
		// Create new Plates instance
		$this->plates = Engine::create($_ENV['TEMPLATES']);
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
			foreach ($this->routesArray as $data) {
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
	public function addRoute(string $method, string $route, $handler)
	{
		$this->routesArray[] = ['method' => $method, 'route' => $route, 'handler' => $handler];
	}

	/**
	 * GET request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	public function get(string $route, $handler) { $this->addRoute('GET', $route, $handler); }
	/**
	 * POST request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	public function post(string $route, $handler) { $this->addRoute('POST', $route, $handler); }
	/**
	 * PUT request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	public function put(string $route, $handler) { $this->addRoute('PUT', $route, $handler); }
	/**
	 * DELETE request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	public function delete(string $route, $handler) { $this->addRoute('DELETE', $route, $handler); }
	/**
	 * HEAD request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	public function head(string $route, $handler) { $this->addRoute('HEAD', $route, $handler); }
	/**
	 * PATCH request method
	 *
	 * @param string $route Route's URI
	 * @param mixed $handler the handler for the route
	 **/
	public function patch(string $route, $handler) { $this->addRoute('PATCH', $route, $handler); }


	/**
	 * This will run the dispatcher with the added routes using addRoutes()
	 **/
	public function run()
	{
		$this->addRoutes();

		$dispatcher = FastRoute\simpleDispatcher($this->routes, [
			'cacheFile' => $_ENV['CACHE_FILE'], /* required */
			'cacheDisabled' =>  $_ENV['CACHE_DISABLED']
		]);

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

				echo $this->plates->render($_ENV['VIEWS'] . '/error', [
					'code' => '404',
					'error' => 'Not Found',
					'description' => 'This page doesn\'t exist.',
				]);
				break;
			case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
				$allowedMethods = $routeInfo[1];
				// ... 405 Method Not Allowed
				http_response_code(405);
				echo $this->plates->render($_ENV['VIEWS'] . '/error', [
					'code' => '405',
					'error' => 'Method Not Allowed',
					'description' => 'This HTTP method is not allowed.',
				]);
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
				if (is_callable($handler))
					$result = call_user_func($handler, $vars, $this->database);
				elseif (is_string($handler)) {
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
					$output = $this->plates->render($_ENV['VIEWS'] . '/' . $result['view'], $result['data'] ?? []);
				} elseif (isset($result['body'])) {
					if (is_array($result['body'])) {
						header('content-type: application/json');
						$output = json_encode($result['body']);
					} else
						$output = $result['body'];
				}

				if ($output != null and $httpMethod != 'HEAD') {
					echo $output;
				}

				break;
		}
	}
}
