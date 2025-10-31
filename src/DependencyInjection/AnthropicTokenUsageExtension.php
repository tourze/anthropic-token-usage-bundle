<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class AnthropicTokenUsageExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        parent::load($configs, $container);

        // 加载环境特定的配置
        $loader = new YamlFileLoader($container, new FileLocator($this->getConfigDir()));

        if ('dev' === $container->getParameter('kernel.environment')) {
            $loader->load('services_dev.yaml');
        }

        if ('test' === $container->getParameter('kernel.environment')) {
            $loader->load('services_test.yaml');
        }
    }
}
