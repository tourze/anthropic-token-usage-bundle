<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Service\UsageQueryService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * UsageQueryService 测试
 * @internal
 */
#[CoversClass(UsageQueryService::class)]
#[RunTestsInSeparateProcesses]
class UsageQueryServiceTest extends AbstractIntegrationTestCase
{
    private UsageQueryService $service;

    protected function onSetUp(): void
    {
        $this->service = self::getService(UsageQueryService::class);
    }

    public function testServiceIsInstantiable(): void
    {
        $this->assertInstanceOf(UsageQueryService::class, $this->service);
    }
}
