<?php

return [
    'deployment' => [
        'mode' => env('KERNELBRIDGE_DEPLOYMENT_MODE', env('APP_MODE', 'client')),
        'allow_local_fallback' => (bool) env('KERNELBRIDGE_ALLOW_LOCAL_FALLBACK', false),
        'strict_profile' => (bool) env('KERNELBRIDGE_STRICT_DEPLOYMENT_PROFILE', true),
    ],
    'api_url' => env('KERNELBRIDGE_API_URL', 'https://kernelbridge.com/api/v1'),
    'product_code' => env('KERNELBRIDGE_PRODUCT_CODE'),
    'api_token' => env('KERNELBRIDGE_API_TOKEN'),
    'timeout_seconds' => (int) env('KERNELBRIDGE_API_TIMEOUT', 10),
    'connect_timeout_seconds' => (int) env('KERNELBRIDGE_API_CONNECT_TIMEOUT', 3),
    'verification_interval_minutes' => (int) env('KERNELBRIDGE_VERIFICATION_INTERVAL', 15),
    'verification_queue' => env('REDIS_NOTIFICATION_QUEUE', env('REDIS_QUEUE', 'default')),
    'offline_grace_hours' => (int) env('KERNELBRIDGE_OFFLINE_GRACE_HOURS', 72),
    'signature_key' => env('KERNELBRIDGE_CACHE_SIGNING_KEY', env('APP_KEY')),
    'protection' => [
        'web' => (bool) env('KERNELBRIDGE_PROTECT_WEB_ROUTES', false),
        'except' => array_values(array_filter(array_map('trim', explode(',', (string) env('KERNELBRIDGE_PROTECT_EXCEPT', ''))))),
    ],
    'renewal_notice' => [
        'enabled' => (bool) env('KERNELBRIDGE_RENEWAL_NOTICE_ENABLED', true),
        'threshold_months' => [3, 2, 1],
    ],
    'routes' => [
        'enabled' => (bool) env('KERNELBRIDGE_LICENSE_ROUTES_ENABLED', true),
        'prefix' => env('KERNELBRIDGE_LICENSE_ROUTE_PREFIX', 'license'),
        'name' => env('KERNELBRIDGE_LICENSE_ROUTE_NAME', 'kernelbridge.license.'),
        'middleware' => ['web'],
        'reprovision_middleware' => ['web'],
    ],

    'ui' => [
        'view' => env('KERNELBRIDGE_LICENSE_VIEW', 'kernelbridge-licensing::activation'),
        'logo_path' => env('KERNELBRIDGE_LICENSE_LOGO_PATH', 'vendor/kernelbridge-licensing/kernelbridge-logo.png'),
        'logo_alt' => env('KERNELBRIDGE_LICENSE_LOGO_ALT'),
        'css_path' => env('KERNELBRIDGE_LICENSE_CSS_PATH', 'vendor/kernelbridge-licensing/activation.css'),
        'show_logo' => (bool) env('KERNELBRIDGE_LICENSE_SHOW_LOGO', true),
        'show_reprovision_button' => (bool) env('KERNELBRIDGE_LICENSE_SHOW_REPROVISION_BUTTON', false),
        'brand_name' => env('KERNELBRIDGE_LICENSE_BRAND_NAME'),
        'brand_subtitle' => env('KERNELBRIDGE_LICENSE_BRAND_SUBTITLE'),
        'heading' => env('KERNELBRIDGE_LICENSE_HEADING'),
        'description' => env('KERNELBRIDGE_LICENSE_DESCRIPTION'),
    ],

    'redirects' => [
        'activation_route' => env('KERNELBRIDGE_LICENSE_ACTIVATION_ROUTE'),
        'after_activation' => env('KERNELBRIDGE_LICENSE_REDIRECT_AFTER_ACTIVATION', function () {
            $product = strtoupper((string) env('KERNELBRIDGE_PRODUCT_CODE'));

            return in_array($product, ['EDUNEXA', 'MEDIFLOW'], true) ? '/configuration' : '/';
        }),
    ],
];
