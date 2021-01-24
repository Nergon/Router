# Router
This is a simple Routing Engine for PHP

## Usage
### Static route
```php
include "Router.php";
$router = new Router();

$router->get("/get", function() {
  echo "This route is accessible by GET";
});


$router->post("/post", function() {
  echo "This route is accessible by POST";
});

$router->match("GET|POST","/getandpost", function() {
  echo "This route is accessible by GET and POST";
});

$router->run();
```

### Variable Routes
```php
include "Router.php";
$router = new Router();

$router->get("/admin/(*.)", function($page) {
  echo "Admin Panel. You are visiting page: ".$page;
});

$router->run();
```

### Error Handling

```php
include "Router.php";
$router = new Router();

$router->set404(function() {
  echo "Error 404 - Not found";
});

$router->set405(function() {
  echo "Error 405 - Not found";
});

$router->run();
```

### Middleware

```php
include "Router.php";
$router = new Router();

$router->before("admin/(*.)", function($page) {
  if(!isset($_SESSION['token']) {
    exit("Please log in to access the admin panel");
  }
});

$router->run();
```

