<?php

namespace Mrldavies\WpRestFluent;

class Rest
{
    protected static $routes = [];

    protected $method;
    protected $endpoint;
    protected $prefix = 'v1';
    protected $callback;
    protected $contentType = 'application/json';
    protected $headers = [];
    protected $middleware = [];
    protected $useFormatter = false;
    protected $status = 200;
    protected $permissions;
    protected $mapDataKey = 'data';
    protected $mapStatusKey = 'status';
    protected $mapHeadersKey = 'headers';

    protected static $groupAttributes = [
        'prefix' => null,
        'permissions' => null,
        'middleware' => [],
    ];

    public static function registerRoutes()
    {
        add_action('rest_api_init', function () {
            foreach (self::$routes as $route) {
                register_rest_route($route->prefix, $route->endpoint, [
                    'methods' => $route->method,
                    'permission_callback' => function ($request) use ($route) {
                        return ($route->permissions ? call_user_func($route->permissions, $request) : true);
                    },
                    'callback' => function ($request) use ($route) {
                        $middlewares = array_reverse($route->middleware);

                        $next = function ($request) use ($route) {
                            if (!is_callable($route->callback)) {
                                return new \WP_Error('missing_callback', 'Route missing callback', ['status' => 500]);
                            }

                            $result = call_user_func($route->callback, $request);

                            if ($result instanceof \WP_REST_Response || $result instanceof \WP_Error) {
                                return $result;
                            }

                            $normalized = self::normalizeHandlerResult($result, $route);

                            $body = $normalized['body'];
                            $status = $normalized['status'];
                            $headers = $normalized['headers'];

                            if (!isset($headers['Content-Type'])) {
                                $headers['Content-Type'] = $route->contentType;
                            }

                            if ($route->useFormatter) {
                                $body = [
                                    'data' => $body,
                                    'status' => $status,
                                    'success' => $status >= 200 && $status < 300,
                                ];
                            }

                            return new \WP_REST_Response($body, $status, $headers);
                        };

                        foreach ($middlewares as $middleware) {
                            $next = function ($request) use ($middleware, $next) {
                                if (is_string($middleware) && class_exists($middleware)) {
                                    $middleware = new $middleware();
                                }

                                if (is_object($middleware) && is_callable([$middleware, 'handle'])) {
                                    return $middleware->handle($request, $next);
                                }

                                return $next($request);
                            };
                        }

                        return $next($request);
                    },
                ]);
            }
        });
    }

    private static function normalizeHandlerResult($result, self $route): array
    {
        $status = (int) ($route->status ?? 200);
        $headers = is_array($route->headers) ? $route->headers : [];
        $body = $result;

        // Arrays: look for keys
        if (is_array($result)) {
            if (array_key_exists($route->mapStatusKey, $result)) {
                $status = (int) $result[$route->mapStatusKey];
            }
            if (array_key_exists($route->mapHeadersKey, $result) && is_array($result[$route->mapHeadersKey])) {
                $headers = array_merge($headers, $result[$route->mapHeadersKey]);
            }

            if (array_key_exists($route->mapDataKey, $result)) {
                $body = $result[$route->mapDataKey];
            } elseif (array_key_exists('body', $result)) {
                $body = $result['body'];
            } elseif (array_key_exists('response', $result)) {
                $body = $result['response'];
            }

            return ['body' => $body, 'status' => $status, 'headers' => $headers];
        }

        if (is_object($result)) {
            if (isset($result->{$route->mapStatusKey})) {
                $status = (int) $result->{$route->mapStatusKey};
            }
            if (isset($result->{$route->mapHeadersKey}) && is_array($result->{$route->mapHeadersKey})) {
                $headers = array_merge($headers, $result->{$route->mapHeadersKey});
            }

            if (isset($result->{$route->mapDataKey})) {
                $body = $result->{$route->mapDataKey};
            } elseif (isset($result->body)) {
                $body = $result->body;
            } elseif (isset($result->response)) {
                $body = $result->response;
            }

            return ['body' => $body, 'status' => $status, 'headers' => $headers];
        }

        return ['body' => $body, 'status' => $status, 'headers' => $headers];
    }

    public function prefix(string $prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function handler($callback, $status = 200)
    {
        $this->callback = $callback;
        $this->status = (int) $status;
        return $this;
    }

    public function contentType(string $contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function permissions($permissions)
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function status()
    {
        $this->useFormatter = true;
        return $this;
    }

    public function formatter(bool $on = true)
    {
        $this->useFormatter = $on;
        return $this;
    }

    public function map(string $dataKey = 'data', string $statusKey = 'status', string $headersKey = 'headers')
    {
        $this->mapDataKey = $dataKey;
        $this->mapStatusKey = $statusKey;
        $this->mapHeadersKey = $headersKey;
        return $this;
    }

    public static function group($attrs, $callback)
    {
        $originalGroupAttributes = self::$groupAttributes;

        foreach ($attrs as $key => $value) {
            self::$groupAttributes[$key] = $value;
        }

        call_user_func($callback);
        self::$groupAttributes = $originalGroupAttributes;
    }

    public function middleware($middleware)
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    private function method($method, $endpoint)
    {
        $pos = strrpos($method, '::');
        $method = strtoupper(substr($method, $pos + 2));

        $route = new self();
        $route->method = $method;
        $route->endpoint = str_replace('/(?:', '(?:', $this->formatRouteArgs($endpoint));

        $route->prefix = !empty(self::$groupAttributes['prefix'])
            ? self::$groupAttributes['prefix']
            : $this->prefix;

        if (!empty(self::$groupAttributes['permissions'])) {
            $route->permissions = self::$groupAttributes['permissions'];
        }

        $route->middleware = self::$groupAttributes['middleware'] ?? [];

        self::$routes[] = $route;
        return $route;
    }

    private function formatRouteArgs(string $endpoint)
    {
        $pattern = '/\/\{(\w+)(?:\:(\w+))?(\?)?\}/';

        return preg_replace_callback($pattern, function ($matches) {
            $argName = $matches[1];
            $type = $matches[2] ?? '';
            $isOptional = isset($matches[3]) && $matches[3] === '?';

            $regex = $this->buildTypeRegex($type);

            return $isOptional
                ? "(?:/(?P<$argName>$regex))?"
                : "/(?P<$argName>$regex)";
        }, $endpoint);
    }

    private function buildTypeRegex($type)
    {
        switch ($type) {
            case 'int':
                return '[0-9]+';
            case 'alpha':
                return '[a-zA-Z]+';
            default:
                return '[a-zA-Z0-9-+_]+';
        }
    }

    public static function get(string $endpoint)
    {
        $rest = new self();
        return $rest->method(__METHOD__, $endpoint);
    }

    public static function post(string $endpoint)
    {
        $rest = new self();
        return $rest->method(__METHOD__, $endpoint);
    }

    public static function put(string $endpoint)
    {
        $rest = new self();
        return $rest->method(__METHOD__, $endpoint);
    }

    public static function patch(string $endpoint)
    {
        $rest = new self();
        return $rest->method(__METHOD__, $endpoint);
    }

    public static function delete(string $endpoint)
    {
        $rest = new self();
        return $rest->method(__METHOD__, $endpoint);
    }

    public static function debugRoutes()
    {
        return array_map(function ($r) {
            return [$r->prefix, $r->method, $r->endpoint];
        }, self::$routes);
    }
}
