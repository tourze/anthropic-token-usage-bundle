<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageQueryServiceInterface;
use Tourze\AnthropicTokenUsageBundle\Service\UsageAdminService;
use Tourze\AnthropicTokenUsageBundle\ValueObject\PaginatedUsageDetailResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * UsageAdminService 测试
 * @internal
 */
#[CoversClass(UsageAdminService::class)]
#[RunTestsInSeparateProcesses]
final class UsageAdminServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 创建 UsageQueryService 的测试替身
        $usageQueryService = new class () implements UsageQueryServiceInterface {
            public function getAccessKeyUsageStatistics(
                AccessKey $accessKey,
                UsageQueryFilter $filter,
            ): UsageStatisticsResult {
                return new UsageStatisticsResult(0, 0, 0, 0, 0, [], null, null);
            }

            public function getUserUsageStatistics(
                UserInterface $user,
                UsageQueryFilter $filter,
            ): UsageStatisticsResult {
                return new UsageStatisticsResult(0, 0, 0, 0, 0, [], null, null);
            }

            public function getUsageDetails(UsageDetailQuery $query): PaginatedUsageDetailResult
            {
                return new PaginatedUsageDetailResult([], 0, $query->page, $query->limit);
            }

            public function getUsageTrends(UsageTrendQuery $query): UsageTrendResult
            {
                $now = new \DateTimeImmutable();

                return new UsageTrendResult([], $now, $now, 'daily');
            }

            /** @return array<array{accessKey: AccessKey, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: ?\DateTimeInterface, lastUsageTime: ?\DateTimeInterface}> */
            public function getTopAccessKeys(
                \DateTimeInterface $startDate,
                \DateTimeInterface $endDate,
                int $limit = 10,
            ): array {
                return [];
            }

            /** @return array<array{user: UserInterface, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: ?\DateTimeInterface, lastUsageTime: ?\DateTimeInterface}> */
            public function getTopUsers(
                \DateTimeInterface $startDate,
                \DateTimeInterface $endDate,
                int $limit = 10,
            ): array {
                return [];
            }
        };

        // 将测试替身注入到容器中
        self::getContainer()->set(UsageQueryServiceInterface::class, $usageQueryService);
    }

    public function testServiceIsInstantiable(): void
    {
        $service = self::getService(UsageAdminService::class);
        $this->assertInstanceOf(UsageAdminService::class, $service);
    }

    public function testExportUsageData(): void
    {
        $service = self::getService(UsageAdminService::class);
        // 基本的方法调用测试，确保方法存在且可调用
        $reflection = new \ReflectionClass($service);
        $this->assertTrue($reflection->hasMethod('exportUsageData'));
    }
}
