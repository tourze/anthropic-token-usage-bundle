<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\AccessKeyBundle\AccessKeyBundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;
use Tourze\HttpForwardBundle\HttpForwardBundle;

class AnthropicTokenUsageBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            HttpForwardBundle::class => ['all' => true],
            AccessKeyBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
        ];
    }
}
