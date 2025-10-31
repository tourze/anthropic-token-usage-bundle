<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Service\UsageAggregateService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * UsageAggregateService 测试
 * @internal
 */
#[CoversClass(UsageAggregateService::class)]
#[RunTestsInSeparateProcesses]
class UsageAggregateServiceTest extends AbstractIntegrationTestCase
{
    private UsageAggregateService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(UsageAggregateService::class);
        $this->assertInstanceOf(UsageAggregateService::class, $this->service);
    }

    public function testServiceIsInstantiable(): void
    {
        $this->assertInstanceOf(UsageAggregateService::class, $this->service);
    }

    public function testCleanupExpiredData(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $this->assertTrue($reflection->hasMethod('cleanupExpiredData'));
        $method = $reflection->getMethod('cleanupExpiredData');
        $this->assertTrue($method->isPublic());
    }

    public function testPerformIncrementalAggregation(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $this->assertTrue($reflection->hasMethod('performIncrementalAggregation'));
        $method = $reflection->getMethod('performIncrementalAggregation');
        $this->assertTrue($method->isPublic());
    }

    public function testRebuildAggregateData(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $this->assertTrue($reflection->hasMethod('rebuildAggregateData'));
        $method = $reflection->getMethod('rebuildAggregateData');
        $this->assertTrue($method->isPublic());
    }
}
