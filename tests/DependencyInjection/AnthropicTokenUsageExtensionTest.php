<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\AnthropicTokenUsageBundle\DependencyInjection\AnthropicTokenUsageExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * AnthropicTokenUsageExtension 测试
 * @internal
 */
#[CoversClass(AnthropicTokenUsageExtension::class)]
final class AnthropicTokenUsageExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private AnthropicTokenUsageExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AnthropicTokenUsageExtension();
    }

    public function testExtensionLoadsWithoutError(): void
    {
        $container = new ContainerBuilder();

        // 添加必要的参数
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', __DIR__ . '/../../..');

        // 测试Extension能够正常加载，不抛出异常
        $this->extension->load([], $container);

        // 验证Extension已正确初始化
        $this->assertInstanceOf(AnthropicTokenUsageExtension::class, $this->extension);
    }

    public function testExtensionAlias(): void
    {
        // 测试Extension的别名
        $this->assertEquals('anthropic_token_usage', $this->extension->getAlias());
    }

    public function testLoad(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', __DIR__ . '/../../..');

        $this->extension->load([], $container);

        // 验证 load 方法执行成功
        $this->assertTrue(true);
    }
}
