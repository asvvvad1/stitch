<?php declare(strict_types=1);

// include the Composer autoloader
require 'vendor/autoload.php';

// New Stitch instance.
// The only parameter to be passed is the data directory
// Directory structure is configurable using the .env file
// But here's the default one I use since it's common with shared hosting providers

/*
/www
__/index.php
__/assets
/data
__/.env
__/vendor
__/templates
  __/views
	__/home.phtml
	--/error.phtml
  __/layouts
	__/layout.phtml

*/

$stitch = new Stitch('data_directory');


/* Routes */

// Adding routes is done with the addRoute method
// where you specify the method, uri, and the callback.

// This example
$stitch->addRoute('GET', '/hello', function(array $vars, Medoo\Medoo $db) : array {
	return ['body' => 'Hello, World!', 'headers' => ['content-type' => 'text/plain']];
});


// You can use get(), post(), put(), head(), patch(), delete() too.
// The callback can be anything that is_callable()
$stitch->get('/home', 'Controller::home');

class Controller
{
	static public function home(array $vars, Medoo\Medoo $db)
	{
		return ['body' => ['vars' => $vars, 'db' => $db]];
	}
}



/* RESPNOSE TYPES */
// The examples bellow demontrate the possible responses along with other things

/* JSON response */ 
// if the body is an array it is automatically converted to JSON
// and Stitch send the right content-type header for it
$stitch->get('/api/version', function () : array {
	return ['body' => ['success' => true, 'version' => 'v0.1']];
});

/* Views with Plates */
// You can access Plates and use it directly just how you would it's meant to be without abstractions
// eg: This will add data to all templates
$stitch->plates->addData([
	'title' => 'Stitch',
	'description' => 'A micro framework that wraps FastRoute, Plates, and Medoo.'
]);

// Views uses are regular Plates templates that inherit layouts
// Layouts and Views folders are under the TEMPLATES folder set in the .env file
// When using $v->layout() in your views you can use any value as long as it's under TEMPLATES
// Having separate directories for each is just my prefference and you can set it however you like!

// This route returns a view name and its data.
$stitch->get('/', function () : array {
	return ['view' => 'home', 'data' => ['title' => 'Home', 'header' => 'Stitch']];
});

// Different content types
// You can easily set custom headers and override ones set by the server
// This may be used to output content Custom headers & dother than HTML (images, file downloads, pdf documents, etc.)

$stitch->addRoute('GET', '/image', function () : array {
    return ['body' => file_get_contents('/path/to/image.png'), 'headers' => ['content-type' => 'image/png']];
});

/* No proccessing (HTML) */
// When you return a string it is printed out as HTML.
$stitch->get('/bye', function() : string {
	return '<b>Bye</b>, World.';
});

/* OTHER/POST requests */

// You can use all methods supported by FastRoute
// POST request support JSON data so to test you can:
// curl -i -X POST -d '{"message":"help"}' http://localhost/echo/name
$stitch->post('/echo[/{name}]', function (array $vars) : array {
	return [
		'body' => [
			'success' => isset($_POST['message']),
			'message' => isset($_POST['message']) ? $_POST['message'] : 'Message not specified',
			'name' => $vars['name'] ?? 'Name not set',
		],
		'status_code' => isset($_POST['message']) ? 200 : 400
	];
});

// HTTP Redirects / custom status code
// Redirects are made easy! simply set redirect to the uri you want
// There is no default redirect HTTP code sent by Stitch so set your own
// depending on the situation by setting status_code to the appropriate code
// status_code isn't limited to any method or body type and can be used in all calls to addRoute
$stitch->get('/old', function () : array {
	return ['redirect' => '/new', 'status_code' => 301];
});

// Plain HTML response
// You seen before that if body is an array it is converted to JSON
// When it isn't though, it is simply sent as regular HTML
// So you can use your own templating or no templates at all
$stitch->get('/new', function () : array {
	return ['body' => '<b>It is what it is</b>'];
});

/* URI variables and database queries */

// Two variabels are passed to the handler method:
// $vars for URI variables and $db for the medoo database
// if you didn't set a DB_DATABASE_TYPE in your .env, omit the $db
// You can also omit both as you seen in previous examples
$stitch->delete('/user/{id:\d+}', function (array $vars, Medoo\Medoo &$db) : array {
	if ($db->has('users', ['id' => $vars['id']])) {
		$db->delete('users', ['id' => $vars['id']]);
	}
	return ["redirect" => "/logout"];
});

$stitch->run();
