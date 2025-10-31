<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\AnthropicTokenUsageBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * AnthropicTokenUsageBundle 集成测试
 * 验证Bundle的基本注册和依赖关系
 * @internal
 * @phpstan-ignore symplify.forbiddenExtendOfNonAbstractClass
 */
#[CoversClass(AnthropicTokenUsageBundle::class)]
#[RunTestsInSeparateProcesses]
final class AnthropicTokenUsageBundleTest extends AbstractBundleTestCase
{
    public function testBundleIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);

        $this->assertTrue($reflection->isInstantiable());
    }

    public function testBundleImplementsBundleDependencyInterface(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);

        $this->assertTrue(
            $reflection->implementsInterface('Tourze\BundleDependency\BundleDependencyInterface'),
            'Bundle应该实现BundleDependencyInterface接口'
        );
    }

    public function testGetBundleDependencies(): void
    {
        $dependencies = AnthropicTokenUsageBundle::getBundleDependencies();

        $this->assertIsArray($dependencies);
        $this->assertNotEmpty($dependencies, 'Bundle应该声明依赖关系');

        // 验证核心依赖存在
        $expectedDependencies = [
            'Doctrine\Bundle\DoctrineBundle\DoctrineBundle',
            'Tourze\HttpForwardBundle\HttpForwardBundle',
            'Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle',
        ];

        foreach ($expectedDependencies as $dependency) {
            $this->assertArrayHasKey(
                $dependency,
                $dependencies,
                "Bundle应该依赖 {$dependency}"
            );

            $this->assertEquals(
                ['all' => true],
                $dependencies[$dependency],
                "依赖 {$dependency} 应该在所有环境下启用"
            );
        }
    }

    public function testBundleExtendsSymfonyBundle(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);

        $this->assertTrue(
            $reflection->isSubclassOf('Symfony\Component\HttpKernel\Bundle\Bundle'),
            'Bundle应该继承Symfony Bundle基类'
        );
    }

    public function testBundleHasCorrectNamespace(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);

        $this->assertEquals(
            'Tourze\AnthropicTokenUsageBundle',
            $reflection->getNamespaceName()
        );
    }

    public function testBundleClassIsNotFinal(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);

        // Bundle类通常不应该是final的，以便可以被扩展
        $this->assertFalse(
            $reflection->isFinal(),
            'Bundle类不应该是final的，以便可以被扩展'
        );
    }

    public function testBundleHasStrictTypesDeclaration(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Bundle文件路径应该可以获取');

        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Bundle源码应该可以读取');
        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source, 'Bundle应该声明严格类型');
    }

    public function testBundleUsesCorrectImports(): void
    {
        $reflection = new \ReflectionClass(AnthropicTokenUsageBundle::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Bundle文件路径应该可以获取');

        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Bundle源码应该可以读取');

        // 验证关键import存在
        $expectedImports = [
            'use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;',
            'use Symfony\Component\HttpKernel\Bundle\Bundle;',
            'use Tourze\BundleDependency\BundleDependencyInterface;',
            'use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;',
            'use Tourze\HttpForwardBundle\HttpForwardBundle;',
        ];

        foreach ($expectedImports as $import) {
            $this->assertStringContainsString(
                $import,
                $source,
                "Bundle应该包含import: {$import}"
            );
        }
    }

    public function testBundleDependenciesAreValidClasses(): void
    {
        $dependencies = AnthropicTokenUsageBundle::getBundleDependencies();

        foreach (array_keys($dependencies) as $dependencyClass) {
            // 验证依赖类可以实例化或反射
            $reflection = new \ReflectionClass($dependencyClass);
            $this->assertTrue(
                $reflection->isInstantiable() || $reflection->isAbstract(),
                "依赖的Bundle类 {$dependencyClass} 应该是可实例化或抽象类"
            );
        }
    }
}
