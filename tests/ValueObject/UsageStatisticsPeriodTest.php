<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsPeriod;

/**
 * UsageStatisticsPeriod 值对象单元测试
 * 测试重点：数据封装、不变性、统计计算功能
 * @internal
 */
#[CoversClass(UsageStatisticsPeriod::class)]
class UsageStatisticsPeriodTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $periodStart = new \DateTimeImmutable('2023-01-01 00:00:00');
        $periodEnd = new \DateTimeImmutable('2023-01-01 23:59:59');
        $inputTokens = 100;
        $cacheCreationTokens = 50;
        $cacheReadTokens = 25;
        $outputTokens = 75;
        $requests = 10;

        $period = new UsageStatisticsPeriod(
            $periodStart,
            $periodEnd,
            $inputTokens,
            $cacheCreationTokens,
            $cacheReadTokens,
            $outputTokens,
            $requests
        );

        $this->assertSame($periodStart, $period->periodStart);
        $this->assertSame($periodEnd, $period->periodEnd);
        $this->assertSame($inputTokens, $period->inputTokens);
        $this->assertSame($cacheCreationTokens, $period->cacheCreationInputTokens);
        $this->assertSame($cacheReadTokens, $period->cacheReadInputTokens);
        $this->assertSame($outputTokens, $period->outputTokens);
        $this->assertSame($requests, $period->requests);
    }

    public function testPropertiesAreReadonly(): void
    {
        $period = new UsageStatisticsPeriod(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            100,
            50,
            25,
            75,
            10
        );

        // 使用反射验证readonly属性
        $reflection = new \ReflectionClass($period);
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
        $period = new UsageStatisticsPeriod(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $inputTokens,
            $cacheCreationTokens,
            $cacheReadTokens,
            $outputTokens,
            10
        );

        $this->assertSame($expectedTotal, $period->getTotalTokens());
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $periodStart = new \DateTimeImmutable('2023-01-01 10:00:00');
        $periodEnd = new \DateTimeImmutable('2023-01-01 11:00:00');

        $period = new UsageStatisticsPeriod(
            $periodStart,
            $periodEnd,
            100,
            50,
            25,
            75,
            5
        );

        $result = $period->toArray();

        $expectedArray = [
            'period_start' => '2023-01-01 10:00:00',
            'period_end' => '2023-01-01 11:00:00',
            'input_tokens' => 100,
            'cache_creation_input_tokens' => 50,
            'cache_read_input_tokens' => 25,
            'output_tokens' => 75,
            'total_tokens' => 250,
            'requests' => 5,
        ];

        $this->assertSame($expectedArray, $result);
    }

    public function testToArrayWithDifferentDateFormats(): void
    {
        $period = new UsageStatisticsPeriod(
            new \DateTime('2023-12-25 23:59:59'),
            new \DateTime('2023-12-26 00:00:00'),
            200,
            100,
            50,
            150,
            20
        );

        $result = $period->toArray();

        $this->assertSame('2023-12-25 23:59:59', $result['period_start']);
        $this->assertSame('2023-12-26 00:00:00', $result['period_end']);
        $this->assertSame(500, $result['total_tokens']);
    }

    public function testValueObjectImmutability(): void
    {
        $period = new UsageStatisticsPeriod(
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
        $reflection = new \ReflectionClass($period);
        $property = $reflection->getProperty('inputTokens');
        $property->setAccessible(true);
        $property->setValue($period, 200);
    }

    public function testZeroValuesAreValid(): void
    {
        $period = new UsageStatisticsPeriod(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            0,
            0,
            0,
            0,
            0
        );

        $this->assertSame(0, $period->inputTokens);
        $this->assertSame(0, $period->cacheCreationInputTokens);
        $this->assertSame(0, $period->cacheReadInputTokens);
        $this->assertSame(0, $period->outputTokens);
        $this->assertSame(0, $period->requests);
        $this->assertSame(0, $period->getTotalTokens());
    }

    public function testLargeValuesAreHandledCorrectly(): void
    {
        $largeValue = 999999;
        $period = new UsageStatisticsPeriod(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-02'),
            $largeValue,
            $largeValue,
            $largeValue,
            $largeValue,
            $largeValue
        );

        $this->assertSame($largeValue, $period->inputTokens);
        $this->assertSame($largeValue, $period->cacheCreationInputTokens);
        $this->assertSame($largeValue, $period->cacheReadInputTokens);
        $this->assertSame($largeValue, $period->outputTokens);
        $this->assertSame($largeValue, $period->requests);
        $this->assertSame($largeValue * 4, $period->getTotalTokens());
    }

    public function testDateTimeObjectsAreRetained(): void
    {
        $startDate = new \DateTimeImmutable('2023-06-15 14:30:45');
        $endDate = new \DateTime('2023-06-15 15:30:45');

        $period = new UsageStatisticsPeriod(
            $startDate,
            $endDate,
            100,
            50,
            25,
            75,
            5
        );

        $this->assertSame($startDate, $period->periodStart);
        $this->assertSame($endDate, $period->periodEnd);
        $this->assertInstanceOf(\DateTimeInterface::class, $period->periodStart);
        $this->assertInstanceOf(\DateTimeInterface::class, $period->periodEnd);
    }
}
