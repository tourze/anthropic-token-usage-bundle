<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Service;

use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\Service\AdminMenu;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private LinkGeneratorInterface $linkGenerator;

    public function testInvokeCreatesTokenMenu(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);
        $adminMenu($rootMenu);

        $tokenMenu = $rootMenu->getChild('Token使用管理');
        self::assertNotNull($tokenMenu);

        $accessKeyUsageMenu = $tokenMenu->getChild('AccessKey使用记录');
        self::assertNotNull($accessKeyUsageMenu);
        self::assertSame('/admin/access-key-usage', $accessKeyUsageMenu->getUri());
        self::assertSame('fas fa-key', $accessKeyUsageMenu->getAttribute('icon'));
        self::assertSame('查看AccessKey维度的Token使用记录', $accessKeyUsageMenu->getAttribute('description'));

        $userUsageMenu = $tokenMenu->getChild('用户使用记录');
        self::assertNotNull($userUsageMenu);
        self::assertSame('/admin/user-usage', $userUsageMenu->getUri());
        self::assertSame('fas fa-user', $userUsageMenu->getAttribute('icon'));
        self::assertSame('查看用户维度的Token使用记录', $userUsageMenu->getAttribute('description'));

        $statisticsMenu = $tokenMenu->getChild('统计数据');
        self::assertNotNull($statisticsMenu);
        self::assertSame('/admin/usage-statistics', $statisticsMenu->getUri());
        self::assertSame('fas fa-chart-bar', $statisticsMenu->getAttribute('icon'));
        self::assertSame('查看预聚合的Token使用统计数据', $statisticsMenu->getAttribute('description'));
    }

    public function testInvokeUsesExistingTokenMenu(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);
        $existingTokenMenu = $rootMenu->addChild('Token使用管理');

        $adminMenu($rootMenu);

        $tokenMenu = $rootMenu->getChild('Token使用管理');
        self::assertSame($existingTokenMenu, $tokenMenu);

        $accessKeyUsageMenu = $tokenMenu->getChild('AccessKey使用记录');
        self::assertNotNull($accessKeyUsageMenu);
        self::assertSame('/admin/access-key-usage', $accessKeyUsageMenu->getUri());

        $userUsageMenu = $tokenMenu->getChild('用户使用记录');
        self::assertNotNull($userUsageMenu);
        self::assertSame('/admin/user-usage', $userUsageMenu->getUri());

        $statisticsMenu = $tokenMenu->getChild('统计数据');
        self::assertNotNull($statisticsMenu);
        self::assertSame('/admin/usage-statistics', $statisticsMenu->getUri());
    }

    public function testServiceIsCallable(): void
    {
        $service = self::getService(AdminMenu::class);

        // Verify the service implements __invoke method
        $reflection = new \ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('__invoke'));
        $this->assertTrue($reflection->getMethod('__invoke')->isPublic());

        // Verify the service is readonly
        $this->assertTrue($reflection->isReadOnly());

        // Verify constructor injection
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertCount(1, $constructor->getParameters());

        $linkGeneratorParam = $constructor->getParameters()[0];
        self::assertSame('linkGenerator', $linkGeneratorParam->getName());
        $type = $linkGeneratorParam->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(LinkGeneratorInterface::class, $type->getName());
    }

    public function testInvokeHandlesNullTokenMenu(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);

        // Simulate case where getChild returns null initially
        $initialTokenMenu = $rootMenu->getChild('Token使用管理');
        self::assertNull($initialTokenMenu);

        $adminMenu($rootMenu);

        $tokenMenu = $rootMenu->getChild('Token使用管理');
        self::assertNotNull($tokenMenu);

        // Verify child menus are added correctly
        self::assertNotNull($tokenMenu->getChild('AccessKey使用记录'));
        self::assertNotNull($tokenMenu->getChild('用户使用记录'));
        self::assertNotNull($tokenMenu->getChild('统计数据'));
    }

    public function testMenuItemAttributes(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        $factory = new MenuFactory();
        $rootMenu = new MenuItem('root', $factory);
        $adminMenu($rootMenu);

        $tokenMenu = $rootMenu->getChild('Token使用管理');
        self::assertNotNull($tokenMenu);

        // Test AccessKey usage menu attributes
        $accessKeyUsageMenu = $tokenMenu->getChild('AccessKey使用记录');
        self::assertNotNull($accessKeyUsageMenu);

        $attributes = $accessKeyUsageMenu->getAttributes();
        self::assertArrayHasKey('icon', $attributes);
        self::assertSame('fas fa-key', $attributes['icon']);
        self::assertArrayHasKey('description', $attributes);
        self::assertSame('查看AccessKey维度的Token使用记录', $attributes['description']);

        // Test user usage menu attributes
        $userUsageMenu = $tokenMenu->getChild('用户使用记录');
        self::assertNotNull($userUsageMenu);

        $attributes = $userUsageMenu->getAttributes();
        self::assertArrayHasKey('icon', $attributes);
        self::assertSame('fas fa-user', $attributes['icon']);
        self::assertArrayHasKey('description', $attributes);
        self::assertSame('查看用户维度的Token使用记录', $attributes['description']);

        // Test statistics menu attributes
        $statisticsMenu = $tokenMenu->getChild('统计数据');
        self::assertNotNull($statisticsMenu);

        $attributes = $statisticsMenu->getAttributes();
        self::assertArrayHasKey('icon', $attributes);
        self::assertSame('fas fa-chart-bar', $attributes['icon']);
        self::assertArrayHasKey('description', $attributes);
        self::assertSame('查看预聚合的Token使用统计数据', $attributes['description']);
    }

    protected function onSetUp(): void
    {
        $this->linkGenerator = new class () implements LinkGeneratorInterface {
            public function getCurdListPage(string $entityClass): string
            {
                return match ($entityClass) {
                    AccessKeyUsage::class => '/admin/access-key-usage',
                    UserUsage::class => '/admin/user-usage',
                    UsageStatistics::class => '/admin/usage-statistics',
                    default => '/admin/unknown',
                };
            }

            public function extractEntityFqcn(string $url): ?string
            {
                return null; // Not used in tests
            }

            public function setDashboard(string $dashboardControllerFqcn): void
            {
                // Not used in tests
            }
        };
        self::getContainer()->set(LinkGeneratorInterface::class, $this->linkGenerator);
    }
}
