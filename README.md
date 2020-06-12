# Stitch: a truely _micro_ framework that wraps FastRoute, Plates, and Medoo
I wrote this to make the process of building up a website in PHP easier. I tried many frameworks and micro-frameworks but they all seemed too much.

I chose FastRoute as it is small and _fast_. Plates as it is easy to use and does the job pretty well. And Medoo, because writing SQL queries was never my thing.
Except for fast route I used these a lot and they're easy to use and to learn.


## Features:

- Almost no learning curve! Stitch syntax is very minimal as it uses components like FastRoute, Plates and Medoo which are familiar to many and have great documentation and support.
- Wraps powerful, tested, and easy libraries providing all that's needed without bloat while letting you extend however you like
- No forced directory structure! You can set up all the location in an easy to use and read .env file.
- Sending a response is easy! send **HTML**, **JSON**, **redirects**, any other content using **`content-type`** header, in a single line!
- Medoo Database config is set in the .env for easy configuration. For example `DB_DATABASE_TYPE` is Medoo's `database_type` option. Changing the database type or credentials is as simple as easy editing the `.env` file, and no could should need to be changed!
- Disabling database is as easy as not setting a `DB_DATABASE_TYPE`.
- `GET` requests support `HEAD` calls and don't output the body automatically. 
- Support for `JSON` data in `POST` requests.

## Preview:

```php
$stitch = new Stitch('data_directory');


// Views

// You can access both $plates & $database (Medoo)
// Add data to all templates
$stitch->plates->addData([
	'title' => 'Stitch'
]);

$stitch->addRoute('GET', '/hello', function(array $vars, Medoo\Medoo $db) : array {
	return ['view' => 'hello'];
})

// JSON
$stitch->get('/', function() {
    return ['body' => ['json' => 'response']];
});

// When you pass a string as handler it'll be printed out as HTMl
$stitch->get('/handle', 'This route has been <em>handled</em>');

// HTTP redirect
$stitch->get('/old', function () : array {
	return ['redirect' => '/new', 'status_code' => 301];
});

// Output any type of content using headers
$stitch->get('/image', function () : array {
    return ['body' => file_get_contents('/path/to/image.png'), 'headers' => ['content-type' => 'image/png']];
});

// Any callable is supported
$stitch->post('/post', 'Controller::post');

$stitch->run();
```

> #### You can see the full possible operations in [examples.php](examples.php) which is fully documented with comments

## Getting started:

While Stitch require no specific structure to allow its use on shared hosting platforms, etc. It's recommended to use the template and make changes depending on your needs.
It can be used as reference and documentation for Stitch. For now get the `.env` from there.

To do that:

`git clone https://github.com/asvvvad/stitch-template/`

`cd stitch-template/data/`

`composer update`

See the template's [README](https://github.com/asvvvad/stitch-template/blob/master/README.md) for more information.

### URL rewrite:

#### ngnix:
```
location / {
    if (!-e $request_filename){
        rewrite ^.*$ /index.php last;
    }
    try_files $uri $uri/ /index.php?$args;
}
```

#### Apache .htaccess:
```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond $1 !\.(html)
RewriteRule ^(.*)$ index.php?/$1 [L,QSA]
```