<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return $this->resolveRuntimeDir('cache/'.$this->environment);
    }

    public function getLogDir(): string
    {
        return $this->resolveRuntimeDir('log');
    }

    private function resolveRuntimeDir(string $suffix): string
    {
        $projectVar = $this->getProjectDir().'/var';
        if (is_dir($projectVar) && is_writable($projectVar)) {
            return $projectVar.'/'.$suffix;
        }

        return sys_get_temp_dir().'/federleicht-kursverwaltung/'.$suffix;
    }
}
