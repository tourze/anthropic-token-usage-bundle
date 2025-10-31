<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsPeriod;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsResult;

/**
 * UsageStatisticsResult 值对象单元测试
 * 测试重点：数据封装、统计计算、数组转换功能
 * @internal
 */
#[CoversClass(UsageStatisticsResult::class)]
class UsageStatisticsResultTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');
        $periods = $this->createSamplePeriods();
        $metadata = ['source' => 'test'];

        $result = new UsageStatisticsResult(
            totalInputTokens: 1000,
            totalCacheCreationInputTokens: 500,
            totalCacheReadInputTokens: 250,
            totalOutputTokens: 750,
            totalRequests: 100,
            periods: $periods,
            startDate: $startDate,
            endDate: $endDate,
            metadata: $metadata
        );

        $this->assertSame(1000, $result->totalInputTokens);
        $this->assertSame(500, $result->totalCacheCreationInputTokens);
        $this->assertSame(250, $result->totalCacheReadInputTokens);
        $this->assertSame(750, $result->totalOutputTokens);
        $this->assertSame(100, $result->totalRequests);
        $this->assertSame($periods, $result->periods);
        $this->assertSame($startDate, $result->startDate);
        $this->assertSame($endDate, $result->endDate);
        $this->assertSame($metadata, $result->metadata);
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $result = new UsageStatisticsResult(
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 50,
            totalCacheReadInputTokens: 25,
            totalOutputTokens: 75,
            totalRequests: 10,
            periods: []
        );

        $this->assertNull($result->startDate);
        $this->assertNull($result->endDate);
        $this->assertSame([], $result->metadata);
        $this->assertSame([], $result->periods);
    }

    public function testPropertiesAreReadonly(): void
    {
        $result = new UsageStatisticsResult(
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 50,
            totalCacheReadInputTokens: 25,
            totalOutputTokens: 75,
            totalRequests: 10,
            periods: []
        );

        // 使用反射验证readonly属性
        $reflection = new \ReflectionClass($result);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    #[TestWith([1000, 500, 250, 750, 2500])]
    #[TestWith([0, 0, 0, 0, 0])]
    #[TestWith([100, 50, 25, 75, 250])]
    #[TestWith([1, 1, 1, 1, 4])]
    public function testGetTotalTokensCalculatesCorrectly(
        int $inputTokens,
        int $cacheCreationTokens,
        int $cacheReadTokens,
        int $outputTokens,
        int $expectedTotal,
    ): void {
        $result = new UsageStatisticsResult(
            totalInputTokens: $inputTokens,
            totalCacheCreationInputTokens: $cacheCreationTokens,
            totalCacheReadInputTokens: $cacheReadTokens,
            totalOutputTokens: $outputTokens,
            totalRequests: 10,
            periods: []
        );

        $this->assertSame($expectedTotal, $result->getTotalTokens());
    }

    #[TestWith([1000, 10, 100.0])]
    #[TestWith([250, 5, 50.0])]
    #[TestWith([0, 10, 0.0])]
    #[TestWith([1000, 0, 0.0])]
    #[TestWith([1, 3, 0.3333333333333333])]
    public function testGetAverageTokensPerRequestCalculatesCorrectly(
        int $totalTokens,
        int $totalRequests,
        float $expectedAverage,
    ): void {
        // 计算各个token类型的分配，使总数等于totalTokens
        $inputTokens = (int) ($totalTokens * 0.4);
        $outputTokens = (int) ($totalTokens * 0.4);
        $cacheCreation = (int) ($totalTokens * 0.1);
        $cacheRead = $totalTokens - $inputTokens - $outputTokens - $cacheCreation;

        $result = new UsageStatisticsResult(
            totalInputTokens: $inputTokens,
            totalCacheCreationInputTokens: $cacheCreation,
            totalCacheReadInputTokens: $cacheRead,
            totalOutputTokens: $outputTokens,
            totalRequests: $totalRequests,
            periods: []
        );

        $this->assertEqualsWithDelta($expectedAverage, $result->getAverageTokensPerRequest(), 0.0001);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01 00:00:00');
        $endDate = new \DateTimeImmutable('2023-01-31 23:59:59');
        $periods = $this->createSamplePeriods();

        $result = new UsageStatisticsResult(
            totalInputTokens: 1000,
            totalCacheCreationInputTokens: 500,
            totalCacheReadInputTokens: 250,
            totalOutputTokens: 750,
            totalRequests: 100,
            periods: $periods,
            startDate: $startDate,
            endDate: $endDate,
            metadata: ['test' => 'value']
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('total_input_tokens', $array);
        $this->assertArrayHasKey('total_cache_creation_input_tokens', $array);
        $this->assertArrayHasKey('total_cache_read_input_tokens', $array);
        $this->assertArrayHasKey('total_output_tokens', $array);
        $this->assertArrayHasKey('total_tokens', $array);
        $this->assertArrayHasKey('total_requests', $array);
        $this->assertArrayHasKey('average_tokens_per_request', $array);
        $this->assertArrayHasKey('periods', $array);
        $this->assertArrayHasKey('start_date', $array);
        $this->assertArrayHasKey('end_date', $array);

        $this->assertSame(1000, $array['total_input_tokens']);
        $this->assertSame(500, $array['total_cache_creation_input_tokens']);
        $this->assertSame(250, $array['total_cache_read_input_tokens']);
        $this->assertSame(750, $array['total_output_tokens']);
        $this->assertSame(2500, $array['total_tokens']);
        $this->assertSame(100, $array['total_requests']);
        $this->assertSame(25.0, $array['average_tokens_per_request']);
        $this->assertSame('2023-01-01 00:00:00', $array['start_date']);
        $this->assertSame('2023-01-31 23:59:59', $array['end_date']);
        $this->assertIsArray($array['periods']);
        $this->assertCount(2, $array['periods']);
    }

    public function testToArrayWithNullDates(): void
    {
        $result = new UsageStatisticsResult(
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 50,
            totalCacheReadInputTokens: 25,
            totalOutputTokens: 75,
            totalRequests: 10,
            periods: []
        );

        $array = $result->toArray();

        $this->assertNull($array['start_date']);
        $this->assertNull($array['end_date']);
    }

    public function testValueObjectImmutability(): void
    {
        $result = new UsageStatisticsResult(
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 50,
            totalCacheReadInputTokens: 25,
            totalOutputTokens: 75,
            totalRequests: 10,
            periods: []
        );

        // 验证值对象是不可变的 - readonly属性不能被修改
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // 通过反射尝试修改readonly属性
        $reflection = new \ReflectionClass($result);
        $property = $reflection->getProperty('totalInputTokens');
        $property->setAccessible(true);
        $property->setValue($result, 200);
    }

    public function testZeroValuesAreValid(): void
    {
        $result = new UsageStatisticsResult(
            totalInputTokens: 0,
            totalCacheCreationInputTokens: 0,
            totalCacheReadInputTokens: 0,
            totalOutputTokens: 0,
            totalRequests: 0,
            periods: []
        );

        $this->assertSame(0, $result->totalInputTokens);
        $this->assertSame(0, $result->totalCacheCreationInputTokens);
        $this->assertSame(0, $result->totalCacheReadInputTokens);
        $this->assertSame(0, $result->totalOutputTokens);
        $this->assertSame(0, $result->totalRequests);
        $this->assertSame(0, $result->getTotalTokens());
        $this->assertSame(0.0, $result->getAverageTokensPerRequest());
    }

    public function testLargeValuesAreHandledCorrectly(): void
    {
        $largeValue = 999999;
        $result = new UsageStatisticsResult(
            totalInputTokens: $largeValue,
            totalCacheCreationInputTokens: $largeValue,
            totalCacheReadInputTokens: $largeValue,
            totalOutputTokens: $largeValue,
            totalRequests: $largeValue,
            periods: []
        );

        $this->assertSame($largeValue, $result->totalInputTokens);
        $this->assertSame($largeValue, $result->totalCacheCreationInputTokens);
        $this->assertSame($largeValue, $result->totalCacheReadInputTokens);
        $this->assertSame($largeValue, $result->totalOutputTokens);
        $this->assertSame($largeValue, $result->totalRequests);
        $this->assertSame($largeValue * 4, $result->getTotalTokens());
        $this->assertSame(4.0, $result->getAverageTokensPerRequest());
    }

    public function testPeriodsArrayMapping(): void
    {
        $periods = $this->createSamplePeriods();
        $result = new UsageStatisticsResult(
            totalInputTokens: 300,
            totalCacheCreationInputTokens: 150,
            totalCacheReadInputTokens: 75,
            totalOutputTokens: 225,
            totalRequests: 30,
            periods: $periods
        );

        $array = $result->toArray();
        $periodsArray = $array['periods'];

        $this->assertIsArray($periodsArray);
        $this->assertCount(2, $periodsArray);

        // 验证每个period都被正确转换为数组
        foreach ($periodsArray as $periodArray) {
            $this->assertIsArray($periodArray);
            $this->assertArrayHasKey('period_start', $periodArray);
            $this->assertArrayHasKey('period_end', $periodArray);
            $this->assertArrayHasKey('input_tokens', $periodArray);
            $this->assertArrayHasKey('total_tokens', $periodArray);
        }
    }

    /**
     * @return array<UsageStatisticsPeriod>
     */
    private function createSamplePeriods(): array
    {
        return [
            new UsageStatisticsPeriod(
                new \DateTimeImmutable('2023-01-01 00:00:00'),
                new \DateTimeImmutable('2023-01-01 23:59:59'),
                100,
                50,
                25,
                75,
                10
            ),
            new UsageStatisticsPeriod(
                new \DateTimeImmutable('2023-01-02 00:00:00'),
                new \DateTimeImmutable('2023-01-02 23:59:59'),
                200,
                100,
                50,
                150,
                20
            ),
        ];
    }
}
