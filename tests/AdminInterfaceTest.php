<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * Admin界面集成测试
 * 测试重点：容器服务配置、内核启动、基础设施集成
 * @internal
 */
#[CoversClass(AbstractIntegrationTestCase::class)]
#[RunTestsInSeparateProcesses]
class AdminInterfaceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // No additional setup needed
    }

    /**
     * 测试容器基本功能
     */
    public function testContainerBasics(): void
    {
        $container = self::getContainer();
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    /**
     * 测试内核可以正确启动
     */
    public function testKernelBootsCorrectly(): void
    {
        $kernel = static::bootKernel();
        $this->assertInstanceOf(KernelInterface::class, $kernel);
        $this->assertSame('test', $kernel->getEnvironment());
    }

    /**
     * 测试容器可以访问所有需要的服务
     */
    public function testContainerServices(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // 测试基本服务是否存在
        $this->assertTrue($container->has('logger'));
        $this->assertTrue($container->has('router.default'));

        // 验证容器是一个有效的服务容器
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }
}
