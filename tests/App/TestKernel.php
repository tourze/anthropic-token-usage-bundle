<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Interface\AccessKeyFinderInterface;
use Tourze\AnthropicTokenUsageBundle\AnthropicTokenUsageBundle;

/**
 * 测试用的内核类
 */
class TestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new AnthropicTokenUsageBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test_secret',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'validation' => ['enable_attributes' => true],
            'session' => [
                'handler_id' => null,
                'storage_factory_id' => 'session.storage.factory.mock_file',
                'cookie_secure' => 'auto',
                'cookie_samesite' => 'lax',
            ],
        ]);

        $container->extension('security', [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => 'auto',
            ],
            'providers' => [
                'users_in_memory' => [
                    'memory' => [
                        'users' => [
                            'admin@test.com' => [
                                'password' => 'password',
                                'roles' => ['ROLE_ADMIN'],
                            ],
                            'user@test.com' => [
                                'password' => 'password',
                                'roles' => ['ROLE_USER'],
                            ],
                        ],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'users_in_memory',
                    'http_basic' => true,
                ],
            ],
            'access_control' => [
                ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'url' => 'sqlite:///:memory:',
            ],
            'orm' => [
                'auto_mapping' => true,
                'mappings' => [
                    'AnthropicTokenUsageTestApp' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__,
                        'prefix' => __NAMESPACE__,
                        'alias' => 'TestApp',
                    ],
                ],
            ],
        ]);

        $container->extension('anthropic_token_usage', [
            'enabled' => true,
        ]);

        // 注册测试用的 AccessKeyFinder 实现
        $container->services()
            ->set(TestAccessKeyFinder::class)
            ->alias(AccessKeyFinderInterface::class, TestAccessKeyFinder::class)
            ->public()
        ;

        // 配置 resolve_target_entities 将 UserInterface 映射到测试用的 SimpleUser
        // 使用更高优先级覆盖全局配置
        $container->services()
            ->set('test.resolve_target_entity', ResolveTargetEntityListener::class)
            ->call('addResolveTargetEntity', [
                UserInterface::class,
                SimpleUser::class,
                [],
            ])
            ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata', 'priority' => 1000])
            ->tag('doctrine.event_listener', ['event' => 'onClassMetadataNotFound', 'priority' => 1000])
        ;

        // 移除或禁用全局的resolve_target_entity监听器（如果存在）
        $container->services()
            ->remove('doctrine.orm.listeners.resolve_target_entity')
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // 简化的路由配置
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }
}
