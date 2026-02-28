<?php

namespace Mrldavies\WpRestFluent\Middleware;

class RateLimitMiddleware
{
    public int $requests;
    public int $seconds;

    public function __construct(int $requests = 60, int $minutes = 1)
    {
        $this->requests = $requests;
        $this->seconds = $minutes * 60;
    }

    public function handle($request, $next)
    {
        $key = $this->resolveKey();

        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, $this->seconds);
            return $next($request);
        }

        if ($count < $this->requests) {
            set_transient($key, $count + 1, $this->seconds);
            return $next($request);
        }

        return new \WP_REST_Response(
            ['message' => 'Too many requests'],
            429,
            ['Retry-After' => (string) $this->seconds]
        );
    }

    private function resolveKey(): string
    {
        if (is_user_logged_in()) {
            return 'rate_limit_user_' . get_current_user_id();
        }

        $ip = $this->resolveIp();

        $routeId = $_SERVER['REQUEST_URI'] ?? '';
        return 'rate_limit_' . md5($ip . '|' . $routeId);
    }

    private function resolveIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return uniqid('anon_', true);
    }
}
