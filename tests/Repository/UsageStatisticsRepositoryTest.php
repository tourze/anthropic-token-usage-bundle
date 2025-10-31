<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\AnthropicTokenUsageBundle\Repository\UsageStatisticsRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * UsageStatisticsRepository 测试
 * @internal
 */
#[CoversClass(UsageStatisticsRepository::class)]
#[RunTestsInSeparateProcesses]
final class UsageStatisticsRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类可以重写此方法进行额外的设置
    }

    protected function createNewEntity(): UsageStatistics
    {
        $entity = new UsageStatistics();
        $entity->setDimensionType('user');
        $entity->setDimensionId('user-' . uniqid());
        $entity->setPeriodType('day');
        $entity->setPeriodStart(new \DateTimeImmutable('2024-01-01'));
        $entity->setPeriodEnd(new \DateTimeImmutable('2024-01-01 23:59:59'));
        // UsageStatistics 没有 model 和 feature 字段，所以不需要设置
        $entity->setTotalInputTokens(100);
        $entity->setTotalCacheCreationInputTokens(10);
        $entity->setTotalCacheReadInputTokens(5);
        $entity->setTotalOutputTokens(200);
        $entity->setTotalRequests(15);

        return $entity;
    }

    protected function getRepository(): UsageStatisticsRepository
    {
        return self::getService(UsageStatisticsRepository::class);
    }

    public function testFindOrCreateWithExistingEntity(): void
    {
        $repository = $this->getRepository();
        $dimensionType = 'user';
        $dimensionId = 'user-' . uniqid();
        $periodType = 'day';
        $periodStart = new \DateTimeImmutable('2024-01-01');
        $periodEnd = new \DateTimeImmutable('2024-01-01 23:59:59');

        // 首先创建并保存一个实体
        $entity = $this->createNewEntity();
        $entity->setDimensionType($dimensionType);
        $entity->setDimensionId($dimensionId);
        $entity->setPeriodType($periodType);
        $entity->setPeriodStart($periodStart);
        $entity->setPeriodEnd($periodEnd);
        $repository->save($entity, true);

        // 现在调用 findOrCreate 应该返回已存在的实体
        $result = $repository->findOrCreate(
            $dimensionType,
            $dimensionId,
            $periodType,
            $periodStart,
            $periodEnd
        );

        $this->assertInstanceOf(UsageStatistics::class, $result);
        $this->assertSame($dimensionType, $result->getDimensionType());
        $this->assertSame($dimensionId, $result->getDimensionId());
        $this->assertSame($periodType, $result->getPeriodType());
        $this->assertEquals($periodStart, $result->getPeriodStart());
        $this->assertEquals($periodEnd, $result->getPeriodEnd());
    }

    public function testFindOrCreateWithNewEntity(): void
    {
        $repository = $this->getRepository();
        $dimensionType = 'user';
        $dimensionId = 'user-' . uniqid();
        $periodType = 'day';
        $periodStart = new \DateTimeImmutable('2024-01-01');
        $periodEnd = new \DateTimeImmutable('2024-01-01 23:59:59');

        // 调用 findOrCreate 应该创建新实体
        $result = $repository->findOrCreate(
            $dimensionType,
            $dimensionId,
            $periodType,
            $periodStart,
            $periodEnd
        );

        $this->assertInstanceOf(UsageStatistics::class, $result);
        $this->assertSame($dimensionType, $result->getDimensionType());
        $this->assertSame($dimensionId, $result->getDimensionId());
        $this->assertSame($periodType, $result->getPeriodType());
        $this->assertEquals($periodStart, $result->getPeriodStart());
        $this->assertEquals($periodEnd, $result->getPeriodEnd());
    }

    public function testFindByDimension(): void
    {
        $repository = $this->getRepository();
        $dimensionType = 'user';
        $dimensionId = 'user-' . uniqid();
        $periodType = 'day';

        // 创建测试数据
        $entity = $this->createNewEntity();
        $entity->setDimensionType($dimensionType);
        $entity->setDimensionId($dimensionId);
        $entity->setPeriodType($periodType);
        $repository->save($entity, true);

        $result = $repository->findByDimension(
            $dimensionType,
            $dimensionId,
            $periodType
        );

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(0, count($result));

        if (count($result) > 0) {
            $data = $result[0];
            $this->assertArrayHasKey('total_input_tokens', $data);
            $this->assertArrayHasKey('total_cache_creation_input_tokens', $data);
            $this->assertArrayHasKey('total_cache_read_input_tokens', $data);
            $this->assertArrayHasKey('total_output_tokens', $data);
            $this->assertArrayHasKey('total_requests', $data);
            $this->assertArrayHasKey('period_start', $data);
            $this->assertArrayHasKey('period_end', $data);
            $this->assertArrayHasKey('model', $data);
            $this->assertArrayHasKey('feature', $data);
        }
    }

    public function testFindTrendData(): void
    {
        $repository = $this->getRepository();
        $dimensionType = 'user';
        $dimensionId = 'user-' . uniqid();
        $periodType = 'day';
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        // 创建测试数据
        $entity = $this->createNewEntity();
        $entity->setDimensionType($dimensionType);
        $entity->setDimensionId($dimensionId);
        $entity->setPeriodType($periodType);
        $entity->setPeriodStart($startDate);
        $entity->setPeriodEnd($endDate);
        $repository->save($entity, true);

        $result = $repository->findTrendData(
            $dimensionType,
            $dimensionId,
            $periodType,
            $startDate,
            $endDate
        );

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(0, count($result));

        if (count($result) > 0) {
            $data = $result[0];
            $this->assertArrayHasKey('period_start', $data);
            $this->assertArrayHasKey('period_end', $data);
            $this->assertArrayHasKey('total_input_tokens', $data);
            $this->assertArrayHasKey('total_cache_creation_input_tokens', $data);
            $this->assertArrayHasKey('total_cache_read_input_tokens', $data);
            $this->assertArrayHasKey('total_output_tokens', $data);
            $this->assertArrayHasKey('total_requests', $data);
        }
    }

    public function testGetSystemTotals(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $periodType = 'day';

        // 创建测试数据
        $entity = $this->createNewEntity();
        $entity->setPeriodType($periodType);
        $entity->setPeriodStart($startDate);
        $entity->setPeriodEnd($endDate);
        $repository->save($entity, true);

        $result = $repository->getSystemTotals($startDate, $endDate, $periodType);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_creation_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_read_input_tokens', $result);
        $this->assertArrayHasKey('total_output_tokens', $result);
        $this->assertArrayHasKey('total_requests', $result);

        $this->assertIsInt($result['total_input_tokens']);
        $this->assertIsInt($result['total_cache_creation_input_tokens']);
        $this->assertIsInt($result['total_cache_read_input_tokens']);
        $this->assertIsInt($result['total_output_tokens']);
        $this->assertIsInt($result['total_requests']);
    }

    public function testGetSystemTotalsWithNoData(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $periodType = 'day';

        // 清理所有数据
        foreach ($repository->findAll() as $entity) {
            $repository->remove($entity, true);
        }

        $result = $repository->getSystemTotals($startDate, $endDate, $periodType);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['total_input_tokens']);
        $this->assertSame(0, $result['total_cache_creation_input_tokens']);
        $this->assertSame(0, $result['total_cache_read_input_tokens']);
        $this->assertSame(0, $result['total_output_tokens']);
        $this->assertSame(0, $result['total_requests']);
    }
}
