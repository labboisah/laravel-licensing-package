<?php

namespace KernelBridge\LicensingClient\Services;

use KernelBridge\LicensingClient\Exceptions\KernelBridgeApiException;
use Symfony\Component\Process\Process;

class DeviceIdentity
{
    private ?string $fingerprint = null;

    public function fingerprint(): string
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }
        $identifier = null;
        if (PHP_OS_FAMILY === 'Windows') {
            $output = $this->run(['reg', 'query', 'HKLM\SOFTWARE\Microsoft\Cryptography', '/v', 'MachineGuid', '/reg:64']);
            if (preg_match('/MachineGuid\s+REG_SZ\s+([a-f0-9-]+)/i', $output, $matches)) {
                $identifier = strtolower($matches[1]);
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            foreach (['/etc/machine-id', '/var/lib/dbus/machine-id'] as $path) {
                if (is_readable($path) && ($value = trim((string) file_get_contents($path))) !== '') {
                    $identifier = $value;
                    break;
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = $this->run(['ioreg', '-rd1', '-c', 'IOPlatformExpertDevice']);
            if (preg_match('/"IOPlatformUUID"\s*=\s*"([^"]+)"/', $output, $matches)) {
                $identifier = strtolower($matches[1]);
            }
        }
        if (! $identifier) {
            throw new KernelBridgeApiException('A stable machine identity could not be read. Ask your administrator to enable access to the operating system machine identifier.', 'device_identity_unavailable');
        }

        return $this->fingerprint = hash('sha256', 'kernelbridge-device-v1|'.PHP_OS_FAMILY.'|'.$identifier);
    }

    public function information(): array
    {
        return [
            'hostname' => substr((string) gethostname(), 0, 255),
            'os' => substr(PHP_OS_FAMILY.' '.php_uname('r'), 0, 255),
            'architecture' => substr(php_uname('m'), 0, 255),
            'client_version' => 'laravel-device-v1',
        ];
    }

    private function run(array $command): string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
