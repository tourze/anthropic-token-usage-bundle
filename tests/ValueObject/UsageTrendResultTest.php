<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendDataPoint;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendResult;

/**
 * UsageTrendResult 值对象单元测试
 * 测试重点：数据封装、统计计算、图表数据转换
 * @internal
 */
#[CoversClass(UsageTrendResult::class)]
class UsageTrendResultTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');
        $dataPoints = $this->createSampleDataPoints();
        $summary = ['total' => 1000, 'average' => 50];

        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: $startDate,
            endDate: $endDate,
            periodType: 'day',
            summary: $summary
        );

        $this->assertSame($dataPoints, $result->dataPoints);
        $this->assertSame($startDate, $result->startDate);
        $this->assertSame($endDate, $result->endDate);
        $this->assertSame('day', $result->periodType);
        $this->assertSame($summary, $result->summary);
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: $startDate,
            endDate: $endDate,
            periodType: 'hour'
        );

        $this->assertSame([], $result->dataPoints);
        $this->assertSame([], $result->summary);
        $this->assertSame('hour', $result->periodType);
    }

    public function testPropertiesAreReadonly(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-31'),
            periodType: 'day'
        );

        // 使用反射验证readonly属性
        $reflection = new \ReflectionClass($result);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    public function testGetDataPointCountReturnsCorrectCount(): void
    {
        $dataPoints = $this->createSampleDataPoints();
        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        $this->assertSame(2, $result->getDataPointCount());
    }

    public function testGetDataPointCountWithEmptyArray(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'day'
        );

        $this->assertSame(0, $result->getDataPointCount());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testHasDataReturnsCorrectValue(bool $hasData): void
    {
        $dataPoints = $hasData ? $this->createSampleDataPoints() : [];

        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        $this->assertSame($hasData, $result->hasData());
    }

    public function testGetTotalTokensCalculatesCorrectly(): void
    {
        $dataPoints = $this->createSampleDataPoints();
        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        // 第一个数据点: 100+50+25+75 = 250
        // 第二个数据点: 200+100+50+150 = 500
        // 总计: 750
        $this->assertSame(750, $result->getTotalTokens());
    }

    public function testGetTotalTokensWithEmptyDataPoints(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'day'
        );

        $this->assertSame(0, $result->getTotalTokens());
    }

    public function testGetTotalRequestsCalculatesCorrectly(): void
    {
        $dataPoints = $this->createSampleDataPoints();
        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        // 第一个数据点: 10请求
        // 第二个数据点: 20请求
        // 总计: 30请求
        $this->assertSame(30, $result->getTotalRequests());
    }

    public function testGetTotalRequestsWithEmptyDataPoints(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'day'
        );

        $this->assertSame(0, $result->getTotalRequests());
    }

    public function testGetAverageDailyTokensCalculatesCorrectly(): void
    {
        $dataPoints = $this->createSampleDataPoints();
        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        // 总tokens: 750, 数据点数量: 2
        // 平均: 750 / 2 = 375.0
        $this->assertSame(375.0, $result->getAverageDailyTokens());
    }

    public function testGetAverageDailyTokensWithEmptyDataPoints(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'day'
        );

        $this->assertSame(0.0, $result->getAverageDailyTokens());
    }

    public function testGetPeakDataPointReturnsHighestUsage(): void
    {
        $dataPoints = $this->createSampleDataPoints();
        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        $peak = $result->getPeakDataPoint();

        // 第二个数据点的tokens更多 (500 > 250)
        $this->assertNotNull($peak);
        $this->assertSame(500, $peak->getTotalTokens());
        $this->assertSame(20, $peak->totalRequests);
    }

    public function testGetPeakDataPointWithEmptyDataPoints(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'day'
        );

        $this->assertNull($result->getPeakDataPoint());
    }

    public function testGetPeakDataPointWithSingleDataPoint(): void
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

        $result = new UsageTrendResult(
            dataPoints: [$dataPoint],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'day'
        );

        $peak = $result->getPeakDataPoint();
        $this->assertSame($dataPoint, $peak);
    }

    public function testToChartDataReturnsCorrectFormat(): void
    {
        $dataPoints = $this->createSampleDataPoints();
        $result = new UsageTrendResult(
            dataPoints: $dataPoints,
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-02'),
            periodType: 'day'
        );

        $chartData = $result->toChartData();

        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertArrayHasKey('summary', $chartData);

        // 验证labels
        $this->assertIsArray($chartData['labels']);
        $this->assertCount(2, $chartData['labels']);
        $this->assertSame('2023-01-01 00:00', $chartData['labels'][0]);
        $this->assertSame('2023-01-02 00:00', $chartData['labels'][1]);

        // 验证datasets
        $this->assertIsArray($chartData['datasets']);
        $this->assertCount(2, $chartData['datasets']);

        // 验证Token数据集
        $tokenDataset = $chartData['datasets'][0];
        $this->assertIsArray($tokenDataset);
        $this->assertSame('Total Tokens', $tokenDataset['label']);
        $this->assertSame([250, 500], $tokenDataset['data']);
        $this->assertSame('line', $tokenDataset['type']);

        // 验证请求数据集
        $requestDataset = $chartData['datasets'][1];
        $this->assertIsArray($requestDataset);
        $this->assertSame('Total Requests', $requestDataset['label']);
        $this->assertSame([10, 20], $requestDataset['data']);
        $this->assertSame('bar', $requestDataset['type']);
        $this->assertSame('requests', $requestDataset['yAxisID']);

        // 验证summary
        $summary = $chartData['summary'];
        $this->assertIsArray($summary);
        $this->assertSame(750, $summary['total_tokens']);
        $this->assertSame(30, $summary['total_requests']);
        $this->assertSame(375.0, $summary['average_daily_tokens']);
        $this->assertSame('day', $summary['period_type']);
        $this->assertSame(2, $summary['data_points']);
        $this->assertIsArray($summary['peak_usage']);
    }

    public function testToChartDataWithEmptyDataPoints(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-01'),
            periodType: 'hour'
        );

        $chartData = $result->toChartData();

        $this->assertSame([], $chartData['labels']);
        $datasets = $chartData['datasets'];
        $this->assertIsArray($datasets);
        $dataset0 = $datasets[0];
        $this->assertIsArray($dataset0);
        $this->assertSame([], $dataset0['data']); // tokens
        $dataset1 = $datasets[1];
        $this->assertIsArray($dataset1);
        $this->assertSame([], $dataset1['data']); // requests
        $summary = $chartData['summary'];
        $this->assertIsArray($summary);
        $this->assertSame(0, $summary['total_tokens']);
        $this->assertSame(0, $summary['total_requests']);
        $this->assertSame(0.0, $summary['average_daily_tokens']);
        $this->assertNull($summary['peak_usage']);
    }

    public function testValueObjectImmutability(): void
    {
        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-31'),
            periodType: 'day'
        );

        // 验证值对象是不可变的 - readonly属性不能被修改
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // 通过反射尝试修改readonly属性
        $reflection = new \ReflectionClass($result);
        $property = $reflection->getProperty('periodType');
        $property->setAccessible(true);
        $property->setValue($result, 'hour');
    }

    public function testDateTimeObjectsAreRetained(): void
    {
        $startDate = new \DateTimeImmutable('2023-06-15 14:30:45');
        $endDate = new \DateTime('2023-06-15 15:30:45');

        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: $startDate,
            endDate: $endDate,
            periodType: 'hour'
        );

        $this->assertSame($startDate, $result->startDate);
        $this->assertSame($endDate, $result->endDate);
        $this->assertInstanceOf(\DateTimeInterface::class, $result->startDate);
        $this->assertInstanceOf(\DateTimeInterface::class, $result->endDate);
    }

    public function testSummaryMetadataIsRetained(): void
    {
        $customSummary = [
            'custom_metric' => 123,
            'description' => 'test summary',
            'nested' => ['key' => 'value'],
        ];

        $result = new UsageTrendResult(
            dataPoints: [],
            startDate: new \DateTimeImmutable('2023-01-01'),
            endDate: new \DateTimeImmutable('2023-01-31'),
            periodType: 'day',
            summary: $customSummary
        );

        $this->assertSame($customSummary, $result->summary);
        $this->assertSame(123, $result->summary['custom_metric']);
        $this->assertSame('test summary', $result->summary['description']);
        $this->assertSame(['key' => 'value'], $result->summary['nested']);
    }

    /**
     * @return array<UsageTrendDataPoint>
     */
    private function createSampleDataPoints(): array
    {
        return [
            new UsageTrendDataPoint(
                new \DateTimeImmutable('2023-01-01 00:00:00'),
                new \DateTimeImmutable('2023-01-01 23:59:59'),
                100,  // totalInputTokens
                50,   // totalCacheCreationInputTokens
                25,   // totalCacheReadInputTokens
                75,   // totalOutputTokens
                10    // totalRequests
            ),
            new UsageTrendDataPoint(
                new \DateTimeImmutable('2023-01-02 00:00:00'),
                new \DateTimeImmutable('2023-01-02 23:59:59'),
                200,  // totalInputTokens
                100,  // totalCacheCreationInputTokens
                50,   // totalCacheReadInputTokens
                150,  // totalOutputTokens
                20    // totalRequests
            ),
        ];
    }
}
