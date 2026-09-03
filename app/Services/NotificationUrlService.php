<?php

namespace App\Services;

class NotificationUrlService
{
    public function safeUrl(mixed $url, string $fallback): string
    {
        if (! is_string($url) || $url === '') {
            return $fallback;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost();
        $appPort = parse_url(config('app.url'), PHP_URL_PORT);

        if (! in_array($scheme, ['http', 'https'], true) || $host !== $appHost || $port !== $appPort) {
            return $fallback;
        }

        return $url;
    }
}
