<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * UsageStatistics 实体测试
 * @internal
 */
#[CoversClass(UsageStatistics::class)]
final class UsageStatisticsTest extends AbstractEntityTestCase
{
    protected function createEntity(): UsageStatistics
    {
        return new UsageStatistics();
    }

    public static function propertiesProvider(): iterable
    {
        return [
            ['dimensionType', UsageStatistics::DIMENSION_ACCESS_KEY],
            ['dimensionId', 'test_dimension_id'],
            ['periodType', UsageStatistics::PERIOD_DAY],
            ['periodStart', new \DateTimeImmutable('2024-01-01 00:00:00')],
            ['periodEnd', new \DateTimeImmutable('2024-01-01 23:59:59')],
            ['totalRequests', 100],
            ['totalInputTokens', 1000],
            ['totalCacheCreationInputTokens', 100],
            ['totalCacheReadInputTokens', 50],
            ['totalOutputTokens', 2000],
            ['lastUpdateTime', new \DateTimeImmutable()],
        ];
    }

    public function testEntityConstants(): void
    {
        $this->assertEquals('access_key', UsageStatistics::DIMENSION_ACCESS_KEY);
        $this->assertEquals('user', UsageStatistics::DIMENSION_USER);
        $this->assertEquals('hour', UsageStatistics::PERIOD_HOUR);
        $this->assertEquals('day', UsageStatistics::PERIOD_DAY);
        $this->assertEquals('month', UsageStatistics::PERIOD_MONTH);
    }

    public function testEntityInstanceCreation(): void
    {
        $entity = new UsageStatistics();
        $this->assertInstanceOf(UsageStatistics::class, $entity);
    }

    public function testDimensionTypeSettersAndGetters(): void
    {
        $entity = new UsageStatistics();
        $entity->setDimensionType(UsageStatistics::DIMENSION_ACCESS_KEY);
        $this->assertEquals(UsageStatistics::DIMENSION_ACCESS_KEY, $entity->getDimensionType());
    }

    public function testDimensionIdSettersAndGetters(): void
    {
        $entity = new UsageStatistics();
        $entity->setDimensionId('test_dimension_id');
        $this->assertEquals('test_dimension_id', $entity->getDimensionId());
    }

    public function testPeriodTypeSettersAndGetters(): void
    {
        $entity = new UsageStatistics();
        $entity->setPeriodType(UsageStatistics::PERIOD_DAY);
        $this->assertEquals(UsageStatistics::PERIOD_DAY, $entity->getPeriodType());
    }

    public function testPeriodStartSettersAndGetters(): void
    {
        $entity = new UsageStatistics();
        $date = new \DateTimeImmutable('2024-01-01 00:00:00');
        $entity->setPeriodStart($date);
        $this->assertEquals($date, $entity->getPeriodStart());
    }

    public function testPeriodEndSettersAndGetters(): void
    {
        $entity = new UsageStatistics();
        $date = new \DateTimeImmutable('2024-01-01 23:59:59');
        $entity->setPeriodEnd($date);
        $this->assertEquals($date, $entity->getPeriodEnd());
    }

    public function testStatisticsFieldsSettersAndGetters(): void
    {
        $entity = new UsageStatistics();

        $entity->setTotalRequests(100);
        $this->assertEquals(100, $entity->getTotalRequests());

        $entity->setTotalInputTokens(1000);
        $this->assertEquals(1000, $entity->getTotalInputTokens());

        $entity->setTotalCacheCreationInputTokens(100);
        $this->assertEquals(100, $entity->getTotalCacheCreationInputTokens());

        $entity->setTotalCacheReadInputTokens(50);
        $this->assertEquals(50, $entity->getTotalCacheReadInputTokens());

        $entity->setTotalOutputTokens(2000);
        $this->assertEquals(2000, $entity->getTotalOutputTokens());
    }

    public function testLastUpdateTimeSetterAndGetter(): void
    {
        $entity = new UsageStatistics();
        $time = new \DateTimeImmutable();
        $entity->setLastUpdateTime($time);
        $this->assertEquals($time, $entity->getLastUpdateTime());
    }

    public function testToString(): void
    {
        $entity = new UsageStatistics();
        $entity->setDimensionType(UsageStatistics::DIMENSION_ACCESS_KEY);
        $entity->setDimensionId('test_key');
        $entity->setPeriodType(UsageStatistics::PERIOD_DAY);

        $toString = (string) $entity;
        $this->assertStringContainsString('access_key', $toString);
        $this->assertStringContainsString('test_key', $toString);
        $this->assertStringContainsString('day', $toString);
    }

    public function testConstructorSetsDefaultValues(): void
    {
        $entity = new UsageStatistics();
        $this->assertEquals(0, $entity->getTotalRequests());
        $this->assertEquals(0, $entity->getTotalInputTokens());
        $this->assertEquals(0, $entity->getTotalCacheCreationInputTokens());
        $this->assertEquals(0, $entity->getTotalCacheReadInputTokens());
        $this->assertEquals(0, $entity->getTotalOutputTokens());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getLastUpdateTime());
    }
}
