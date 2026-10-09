<?php

namespace KernelBridge\LicensingClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class FeatureMiddleware extends LicenseMiddleware
{
    public function handle(Request $request, Closure $next, mixed ...$arguments): Response
    {
        $feature = (string) ($arguments[0] ?? '');
        abort_if($feature === '', 500, 'A KernelBridge feature key is required.');
        if ($response = $this->requireFeature($request, $feature)) {
            return $response;
        }

        return $next($request);
    }
}
