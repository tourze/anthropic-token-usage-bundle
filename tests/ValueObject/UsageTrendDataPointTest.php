<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendDataPoint;

/**
 * UsageTrendDataPoint 值对象单元测试
 * 测试重点：数据封装、统计计算、缓存命中率计算
 * @internal
 */
#[CoversClass(UsageTrendDataPoint::class)]
class UsageTrendDataPointTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $periodStart = new \DateTimeImmutable('2023-01-01 00:00:00');
        $periodEnd = new \DateTimeImmutable('2023-01-01 23:59:59');
        $totalInputTokens = 100;
        $totalCacheCreationInputTokens = 50;
        $totalCacheReadInputTokens = 25;
        $totalOutputTokens = 75;
        $totalRequests = 10;

        $dataPoint = new UsageTrendDataPoint(
            $periodStart,
            $periodEnd,
            $totalInputTokens,
            $totalCacheCreationInputTokens,
            $totalCacheReadInputTokens,
            $totalOutputTokens,
            $totalRequests
        );

        $this->assertSame($periodStart, $dataPoint->periodStart);
        $this->assertSame($periodEnd, $dataPoint->periodEnd);
        $this->assertSame($totalInputTokens, $dataPoint->totalInputTokens);
        $this->assertSame($totalCacheCreationInputTokens, $dataPoint->totalCacheCreationInputTokens);
        $this->assertSame($totalCacheReadInputTokens, $dataPoint->totalCacheReadInputTokens);
        $this->assertSame($totalOutputTokens, $dataPoint->totalOutputTokens);
        $this->assertSame($totalRequests, $dataPoint->totalRequests);
    }

    public function testPropertiesAreReadonly(): void
    {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            100,
            50,
            25,
            75,
            10
        );

        // 使用反射验证readonly属性
        $reflection = new \ReflectionClass($dataPoint);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    #[TestWith([100, 50, 25, 75, 250])]
    #[TestWith([0, 0, 0, 0, 0])]
    #[TestWith([1000, 500, 250, 750, 2500])]
    #[TestWith([1, 1, 1, 1, 4])]
    public function testGetTotalTokensCalculatesCorrectly(
        int $inputTokens,
        int $cacheCreationTokens,
        int $cacheReadTokens,
        int $outputTokens,
        int $expectedTotal,
    ): void {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $inputTokens,
            $cacheCreationTokens,
            $cacheReadTokens,
            $outputTokens,
            10
        );

        $this->assertSame($expectedTotal, $dataPoint->getTotalTokens());
    }

    #[TestWith([100, 50, 25, 175])]
    #[TestWith([0, 0, 0, 0])]
    #[TestWith([200, 100, 50, 350])]
    #[TestWith([10, 5, 3, 18])]
    public function testGetTotalInputTokensCalculatesCorrectly(
        int $inputTokens,
        int $cacheCreationTokens,
        int $cacheReadTokens,
        int $expectedTotal,
    ): void {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $inputTokens,
            $cacheCreationTokens,
            $cacheReadTokens,
            100, // outputTokens - 不影响输入token总数计算
            5
        );

        $this->assertSame($expectedTotal, $dataPoint->getTotalInputTokens());
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

        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $inputTokens,
            $cacheCreation,
            $cacheRead,
            $outputTokens,
            $totalRequests
        );

        $this->assertEqualsWithDelta($expectedAverage, $dataPoint->getAverageTokensPerRequest(), 0.0001);
    }

    #[TestWith([100, 50, 25, 0.14285714285714285])] // 25 / (100 + 50 + 25) = 25/175
    #[TestWith([0, 0, 0, 0.0])] // 0/0 = 0
    #[TestWith([100, 0, 0, 0.0])] // 0/100 = 0
    #[TestWith([0, 0, 100, 1.0])] // 100/100 = 1
    #[TestWith([200, 100, 50, 0.14285714285714285])] // 50 / (200 + 100 + 50) = 50/350
    public function testGetCacheHitRateCalculatesCorrectly(
        int $inputTokens,
        int $cacheCreationTokens,
        int $cacheReadTokens,
        float $expectedRate,
    ): void {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $inputTokens,
            $cacheCreationTokens,
            $cacheReadTokens,
            100, // outputTokens - 不影响缓存命中率计算
            5
        );

        $this->assertEqualsWithDelta($expectedRate, $dataPoint->getCacheHitRate(), 0.0001);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $periodStart = new \DateTimeImmutable('2023-01-01 10:00:00');
        $periodEnd = new \DateTimeImmutable('2023-01-01 11:00:00');

        $dataPoint = new UsageTrendDataPoint(
            $periodStart,
            $periodEnd,
            100,
            50,
            25,
            75,
            5
        );

        $result = $dataPoint->toArray();

        $expectedKeys = [
            'period_start',
            'period_end',
            'total_input_tokens',
            'total_cache_creation_input_tokens',
            'total_cache_read_input_tokens',
            'total_output_tokens',
            'total_tokens',
            'total_requests',
            'average_tokens_per_request',
            'cache_hit_rate',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $this->assertSame('2023-01-01 10:00:00', $result['period_start']);
        $this->assertSame('2023-01-01 11:00:00', $result['period_end']);
        $this->assertSame(100, $result['total_input_tokens']);
        $this->assertSame(50, $result['total_cache_creation_input_tokens']);
        $this->assertSame(25, $result['total_cache_read_input_tokens']);
        $this->assertSame(75, $result['total_output_tokens']);
        $this->assertSame(250, $result['total_tokens']);
        $this->assertSame(5, $result['total_requests']);
        $this->assertSame(50.0, $result['average_tokens_per_request']);
        $this->assertEqualsWithDelta(0.14285714285714285, $result['cache_hit_rate'], 0.0001);
    }

    public function testToArrayWithDifferentDateFormats(): void
    {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTime('2023-12-25 23:59:59'),
            new \DateTime('2023-12-26 00:00:00'),
            200,
            100,
            50,
            150,
            20
        );

        $result = $dataPoint->toArray();

        $this->assertSame('2023-12-25 23:59:59', $result['period_start']);
        $this->assertSame('2023-12-26 00:00:00', $result['period_end']);
        $this->assertSame(500, $result['total_tokens']);
    }

    public function testValueObjectImmutability(): void
    {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            100,
            50,
            25,
            75,
            10
        );

        // 验证值对象是不可变的 - readonly属性不能被修改
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // 通过反射尝试修改readonly属性
        $reflection = new \ReflectionClass($dataPoint);
        $property = $reflection->getProperty('totalInputTokens');
        $property->setAccessible(true);
        $property->setValue($dataPoint, 200);
    }

    public function testZeroValuesAreValid(): void
    {
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            0,
            0,
            0,
            0,
            0
        );

        $this->assertSame(0, $dataPoint->totalInputTokens);
        $this->assertSame(0, $dataPoint->totalCacheCreationInputTokens);
        $this->assertSame(0, $dataPoint->totalCacheReadInputTokens);
        $this->assertSame(0, $dataPoint->totalOutputTokens);
        $this->assertSame(0, $dataPoint->totalRequests);
        $this->assertSame(0, $dataPoint->getTotalTokens());
        $this->assertSame(0, $dataPoint->getTotalInputTokens());
        $this->assertSame(0.0, $dataPoint->getAverageTokensPerRequest());
        $this->assertSame(0.0, $dataPoint->getCacheHitRate());
    }

    public function testLargeValuesAreHandledCorrectly(): void
    {
        $largeValue = 999999;
        $dataPoint = new UsageTrendDataPoint(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $largeValue,
            $largeValue,
            $largeValue,
            $largeValue,
            $largeValue
        );

        $this->assertSame($largeValue, $dataPoint->totalInputTokens);
        $this->assertSame($largeValue, $dataPoint->totalCacheCreationInputTokens);
        $this->assertSame($largeValue, $dataPoint->totalCacheReadInputTokens);
        $this->assertSame($largeValue, $dataPoint->totalOutputTokens);
        $this->assertSame($largeValue, $dataPoint->totalRequests);
        $this->assertSame($largeValue * 4, $dataPoint->getTotalTokens());
        $this->assertSame($largeValue * 3, $dataPoint->getTotalInputTokens());
        $this->assertSame(4.0, $dataPoint->getAverageTokensPerRequest());
        $this->assertEqualsWithDelta(0.3333333333333333, $dataPoint->getCacheHitRate(), 0.0001);
    }

    public function testDateTimeObjectsAreRetained(): void
    {
        $startDate = new \DateTimeImmutable('2023-06-15 14:30:45');
        $endDate = new \DateTime('2023-06-15 15:30:45');

        $dataPoint = new UsageTrendDataPoint(
            $startDate,
            $endDate,
            100,
            50,
            25,
            75,
            5
        );

        $this->assertSame($startDate, $dataPoint->periodStart);
        $this->assertSame($endDate, $dataPoint->periodEnd);
        $this->assertInstanceOf(\DateTimeInterface::class, $dataPoint->periodStart);
        $this->assertInstanceOf(\DateTimeInterface::class, $dataPoint->periodEnd);
    }
}
