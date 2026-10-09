<?php

namespace KernelBridge\LicensingClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use KernelBridge\LicensingClient\Services\LicenseCacheService;
use Symfony\Component\HttpFoundation\Response;

class LicenseMiddleware
{
    public function __construct(protected readonly LicenseCacheService $licenses) {}

    protected function activeFailureResponse(Request $request): ?Response
    {
        if ($this->shouldPassThrough($request) || $this->licenses->hasUsableLicense()) {
            return null;
        }

        $message = $this->licenses->activationRequirementMessage();
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(402, $message);
        }

        $routeName = $this->activationRouteName();
        if ($request->routeIs($routeName)) {
            abort(402, $message);
        }

        return redirect()->guest(route($routeName))->withErrors(['license_key' => $message]);
    }

    protected function requireActive(Request $request): ?Response
    {
        return $this->activeFailureResponse($request);
    }

    protected function requireFeature(Request $request, string $feature): ?Response
    {
        if ($response = $this->requireActive($request)) {
            return $response;
        }

        abort_unless($this->licenses->hasFeature($feature), 403, "The '$feature' feature is not included in this plan.");

        return null;
    }

    protected function requireLimit(Request $request, string $feature, int $required): ?Response
    {
        if ($response = $this->requireFeature($request, $feature)) {
            return $response;
        }

        $limit = $this->licenses->limit($feature);
        if ($limit === null || $limit === '' || $limit === 'unlimited') {
            return null;
        }
        abort_unless(is_numeric($limit) && (float) $limit >= $required, 403, "The '$feature' plan limit is insufficient.");

        return null;
    }

    public function handle(Request $request, Closure $next, mixed ...$arguments): Response
    {
        if ($response = $this->requireActive($request)) {
            return $response;
        }

        $response = $next($request);

        if ($notice = $this->licenses->renewalNotice()) {
            $response->headers->set('X-KernelBridge-Renewal-Notice-Level', $notice['level']);
            $response->headers->set('X-KernelBridge-Renewal-Notice-Message', $notice['message']);
            $response->headers->set('X-KernelBridge-Renewal-Expires-At', $notice['expires_at']);
        }

        return $response;
    }

    protected function shouldPassThrough(Request $request): bool
    {
        // Activation must be reachable before the client has a usable license.
        // The API route still authenticates its KernelBridge bearer credential.
        if ($request->is('api/v1/licenses/activate')) {
            return true;
        }

        $routeName = $this->activationRouteName();
        if ($request->routeIs($routeName)) {
            return true;
        }

        $prefix = trim((string) config('kernelbridge-licensing.routes.prefix', 'license'), '/');
        $except = array_filter((array) config('kernelbridge-licensing.protection.except', []));
        if ($prefix !== '') {
            $except[] = $prefix;
            $except[] = $prefix.'/*';
        }

        foreach ($except as $pattern) {
            if ($request->is((string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function activationRouteName(): string
    {
        $configured = (string) config('kernelbridge-licensing.redirects.activation_route');
        if ($configured !== '') {
            return $configured;
        }

        if (! (bool) config('kernelbridge-licensing.routes.enabled', true)) {
            return 'setup.license';
        }

        return (string) (config('kernelbridge-licensing.routes.name', 'kernelbridge.license.').'show');
    }
}
