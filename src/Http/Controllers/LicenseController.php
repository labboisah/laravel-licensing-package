<?php

namespace KernelBridge\LicensingClient\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeApiException;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeUnavailableException;
use KernelBridge\LicensingClient\Services\DeploymentProfile as DeploymentProfileService;
use KernelBridge\LicensingClient\Services\LicenseActivationService;
use KernelBridge\LicensingClient\Services\LicenseCacheService;
use KernelBridge\LicensingClient\Services\LicenseVerificationService;

final class LicenseController extends Controller
{
    public function show(LicenseCacheService $cache): View
    {
        return view((string) config('kernelbridge-licensing.ui.view', 'kernelbridge-licensing::activation'), [
            'state' => $cache->state(),
            'hasUsableLicense' => $cache->hasUsableLicense(),
            'activationReason' => $cache->activationRequirementReason(),
            'activationMessage' => $cache->activationRequirementMessage(),
            'licenseStatus' => $cache->status(),
            'productCode' => config('kernelbridge-licensing.product_code'),
        ]);
    }

    public function activate(Request $request, LicenseActivationService $activation, LicenseCacheService $cache): RedirectResponse
    {
        $isEdunexa = strtoupper((string) config('kernelbridge-licensing.product_code')) === 'EDUNEXA';
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
            'device_name' => [$isEdunexa ? 'required' : 'nullable', 'string', 'max:255'],
            'platform_url' => [$isEdunexa ? 'required' : 'nullable', 'url', 'max:2048'],
        ]);
        $state = $cache->state();
        $wasPreviouslyActivated = $state->license_identifier !== null || $state->activation_identifier !== null;

        try {
            $activation->activate($data['license_key'], $data['device_name'] ?? config('app.name'), $data['platform_url'] ?? null);
        } catch (KernelBridgeApiException|KernelBridgeUnavailableException $exception) {
            return back()->withInput($request->except('license_key'))->withErrors([
                'license_key' => $this->friendlyError($exception),
            ]);
        }

        $redirect = (string) config('kernelbridge-licensing.redirects.after_activation', '/');
        $response = ! $wasPreviouslyActivated && $redirect !== '/'
            ? redirect($redirect)
            : redirect()->intended('/');

        return $response->with('kernelbridge_license_status', 'License activated successfully.');
    }

    public function verify(LicenseVerificationService $verification): RedirectResponse
    {
        try {
            $verification->verify(true);
        } catch (KernelBridgeApiException|KernelBridgeUnavailableException $exception) {
            return back()->withErrors([
                'license_key' => $this->friendlyError($exception),
            ]);
        }

        return redirect()->route($this->routeName('show'))->with('kernelbridge_license_status', 'License verified successfully.');
    }

    public function deactivate(LicenseVerificationService $verification): RedirectResponse
    {
        try {
            $verification->deactivate();
        } catch (KernelBridgeApiException|KernelBridgeUnavailableException $exception) {
            return back()->withErrors([
                'license_key' => $this->friendlyError($exception),
            ]);
        }

        return redirect()->route($this->routeName('show'))->with('kernelbridge_license_status', 'License deactivated successfully.');
    }

    public function reprovision(DeploymentProfileService $profile): RedirectResponse
    {
        $profile->persist();

        return redirect()->route($this->routeName('show'))->with('kernelbridge_license_status', 'Deployment profile re-provisioned successfully.');
    }

    private function friendlyError(KernelBridgeApiException|KernelBridgeUnavailableException $exception): string
    {
        if ($exception instanceof KernelBridgeApiException && $exception->errorCode === 'activation_limit_reached') {
            return 'This license has reached its device activation limit. Deactivate another installation in KernelBridge before trying again.';
        }

        return $exception->getMessage();
    }

    private function routeName(string $name): string
    {
        return (string) config('kernelbridge-licensing.routes.name', 'kernelbridge.license.').$name;
    }
}
