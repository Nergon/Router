<?php

/**
 * Class Router
 *
 * @author Nergon
 */
class Router {

    /**
     * @var array Routes that will be executed before actual route handling is processed
     */
    private $beforeRoutes = array();

    /**
     * @var array The supported Request Methods
     */
    private $supportedMethods = array(
        'GET',
        'POST',
        'PUT',
        'DELETE'
    );

    /**
     * @var string The base folder. Makes it possible to execute the router in a sub-folder
     */
    private $baseUrl = '';

    /**
     * @var object|callable The function that will be executed after 404 error
     */
    private $notFoundFunction;

    /**
     * @var object|callable The function that will be executed after 405 error
     */
    private $notAllowedFunction;

    /**
     * Router constructor.
     * @param string $baseUrl The base url for the router. This is your sub-folder
     */
    public function __construct($baseUrl = '') {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Add the routes by using a simple function (e.g. $router->get($pattern, $fn)
     * @param string $name The request method (e.g get)
     * @param array $arguments The arguments of the function. Must be an array with a pattern and a function
     */
    public function __call($name, $arguments) {

        $name = strtolower($name);

        list($pattern, $fn) = $arguments;

        if(!in_array(strtoupper($name), $this->supportedMethods)) {
            $this->trigger405();
            return;
        }

        $this->{$name}[] = array("pattern" => $this->trimRoute($pattern), "function" => $fn);
    }

    /**
     * Assign multiple request methods to the same function
     * @param string $methods The request methods of the function. Split by |
     * @param string $pattern The RegEx pattern of the url
     * @param object|callable $fn The function which should be executed
     */
    public function match($methods, $pattern, $fn) {
        foreach (explode('|',$methods) as $method) {

            $method = strtolower($method);

            if(!in_array(strtoupper($method), $this->supportedMethods)) {
                $this->trigger405();
                return;
            }

            $this->{$method}[] = array("pattern" => $this->trimRoute($pattern), "function" => $fn);
        }
    }

    /**
     * Add a function which should be processed before route handling
     * @param string $methods The request methods
     * @param string $pattern The RegEx pattern of the route
     * @param object|callable $fn The function which should be executed
     */
    public function before($methods, $pattern, $fn) {
        foreach (explode('|',$methods) as $method) {
            $method = strtolower($method);

            if(!in_array(strtoupper($method), $this->supportedMethods)) {
                $this->trigger405();
                return;
            }

            $this->beforeRoutes[] = array("method" => $method,"pattern" => $this->trimRoute($pattern), "function" => $fn);
        }
    }

    /**
     * Execute the router
     */
    public function run() {
        $requestMethod = strtolower($this->getRequestMethod());
        $requestUri = $this->getRequestUri();

        if(!in_array(strtoupper($requestMethod), $this->supportedMethods)) {
            $this->trigger405();
            return;
        }

        //Before Routes
        foreach ($this->beforeRoutes as $route) {
            $method = $route["method"];
            $pattern = $route["pattern"];
            if($requestMethod == $method) {

                if (preg_match_all('#^'.$pattern.'$#', $requestUri, $matches, PREG_OFFSET_CAPTURE)) {

                    $matches = array_slice($matches, 1);

                    $params = array_map(function ($match, $index) use ($matches) {
                        if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                            if ($matches[$index + 1][0][1] > -1) {
                                return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                            }
                        }
                        return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                    }, $matches, array_keys($matches));
                    $this->callFunction($route["function"], $params);
                }
            }
        }

        $countedMatches = 0;
        foreach ($this->{$requestMethod} as $route) {
            if (preg_match_all('#^'.$route['pattern'].'$#', $requestUri, $matches, PREG_OFFSET_CAPTURE)) {
                $matches = array_slice($matches, 1);

                $params = array_map(function ($match, $index) use ($matches) {
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                        if ($matches[$index + 1][0][1] > -1) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        }
                    }
                    return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                $this->callFunction($route["function"], $params);
                $countedMatches++;
            }
        }
        if($countedMatches == 0) {
            $this->trigger404();
        }
    }

    /**
     * Call a function
     * @param object|callable $fn The function to be called
     * @param array $args The arguments for the function
     */
    private function callFunction($fn, $args = array()) {
        if(is_callable($fn)) {
            call_user_func_array($fn, $args);
            return;
        }

        $this->trigger404();
    }

    /**
     * Trigger 404 error
     */
    private function trigger404() {
        http_response_code(404);
        $this->callFunction($this->notFoundFunction);
    }

    /**
     * Trigger 405 error
     */
    private function trigger405() {
        http_response_code(405);
        $this->callFunction($this->notAllowedFunction);
    }

    /**
     * Set the function to be executed after 404 error
     * @param object|callable $fn The function to be executed
     */
    public function set404($fn) {
        $this->notFoundFunction = $fn;
    }

    /**
     * Set the function to be executed after 405 error
     * @param object|callable $fn The function to be executed
     */
    public function set405($fn) {
        $this->notAllowedFunction = $fn;
    }

    /**
     * Trim the route to add the subfolder
     * @param string $route The pattern that should be trimmed
     * @return string The trimmed pattern
     */
    private function trimRoute($route) {
        $route = '/'.trim($route, '/');
        return ($this->baseUrl == '') ? $route : '/'.trim($this->baseUrl.'/'.trim($route, '/'), '/');
    }

    /**
     * Get the current relative URI
     *
     * @return string
     */
    private function getRequestUri() {
        $uri = $_SERVER['REQUEST_URI'];
        if($this->baseUrl == '') {
            $uri = str_replace($this->baseUrl, '', $uri);
        }

        if(strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        // Remove last / and enforce / on start
        return '/'.trim($uri, '/');
    }

    /**
     * Get the current request method
     *
     * @return string
     */
    private function getRequestMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

}