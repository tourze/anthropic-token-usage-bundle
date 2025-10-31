<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendQuery;

/**
 * UsageTrendQuery 值对象单元测试
 * 测试重点：查询参数封装、静态工厂方法、时间计算
 * @internal
 */
#[CoversClass(UsageTrendQuery::class)]
class UsageTrendQueryTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');
        $models = ['gpt-4', 'gpt-3.5-turbo'];
        $features = ['chat', 'completion'];

        $query = new UsageTrendQuery(
            startDate: $startDate,
            endDate: $endDate,
            dimensionType: 'user',
            dimensionId: 'user123',
            periodType: 'hour',
            models: $models,
            features: $features,
            limit: 50
        );

        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertSame('user', $query->dimensionType);
        $this->assertSame('user123', $query->dimensionId);
        $this->assertSame('hour', $query->periodType);
        $this->assertSame($models, $query->models);
        $this->assertSame($features, $query->features);
        $this->assertSame(50, $query->limit);
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $query = new UsageTrendQuery($startDate, $endDate);

        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertNull($query->dimensionType);
        $this->assertNull($query->dimensionId);
        $this->assertSame('day', $query->periodType);
        $this->assertNull($query->models);
        $this->assertNull($query->features);
        $this->assertSame(100, $query->limit);
    }

    public function testPropertiesAreReadonly(): void
    {
        $query = new UsageTrendQuery(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-31')
        );

        // 使用反射验证readonly属性
        $reflection = new \ReflectionClass($query);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    public function testForAccessKeyFactoryMethod(): void
    {
        $accessKeyId = 'ak_test123';
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $query = UsageTrendQuery::forAccessKey($accessKeyId, $startDate, $endDate, 'hour');

        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertSame('access_key', $query->dimensionType);
        $this->assertSame($accessKeyId, $query->dimensionId);
        $this->assertSame('hour', $query->periodType);
        $this->assertSame(100, $query->limit); // 默认值
        $this->assertNull($query->models);
        $this->assertNull($query->features);
    }

    public function testForAccessKeyWithDefaultPeriodType(): void
    {
        $accessKeyId = 'ak_test123';
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $query = UsageTrendQuery::forAccessKey($accessKeyId, $startDate, $endDate);

        $this->assertSame('access_key', $query->dimensionType);
        $this->assertSame($accessKeyId, $query->dimensionId);
        $this->assertSame('day', $query->periodType); // 默认值
    }

    public function testForUserFactoryMethod(): void
    {
        $userId = 'user_test123';
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $query = UsageTrendQuery::forUser($userId, $startDate, $endDate, 'month');

        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertSame('user', $query->dimensionType);
        $this->assertSame($userId, $query->dimensionId);
        $this->assertSame('month', $query->periodType);
        $this->assertSame(100, $query->limit); // 默认值
        $this->assertNull($query->models);
        $this->assertNull($query->features);
    }

    public function testForUserWithDefaultPeriodType(): void
    {
        $userId = 'user_test123';
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $query = UsageTrendQuery::forUser($userId, $startDate, $endDate);

        $this->assertSame('user', $query->dimensionType);
        $this->assertSame($userId, $query->dimensionId);
        $this->assertSame('day', $query->periodType); // 默认值
    }

    #[TestWith(['2023-01-01', '2023-01-01', 1])] // 同一天
    #[TestWith(['2023-01-01', '2023-01-02', 2])] // 相差1天
    #[TestWith(['2023-01-01', '2023-01-07', 7])] // 相差6天
    #[TestWith(['2023-01-01', '2023-01-31', 31])] // 相差30天
    #[TestWith(['2023-01-01', '2023-02-01', 32])] // 跨月
    public function testGetDaySpanCalculatesCorrectly(string $start, string $end, int $expectedDays): void
    {
        $query = new UsageTrendQuery(
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end)
        );

        $this->assertSame($expectedDays, $query->getDaySpan());
    }

    #[TestWith(['user', 'user123', true])]
    #[TestWith(['access_key', 'ak123', true])]
    #[TestWith([null, 'user123', false])]
    #[TestWith(['user', null, false])]
    #[TestWith([null, null, false])]
    public function testIsSingleDimensionChecksCorrectly(?string $dimensionType, ?string $dimensionId, bool $expected): void
    {
        $query = new UsageTrendQuery(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-31'),
            dimensionType: $dimensionType,
            dimensionId: $dimensionId
        );

        $this->assertSame($expected, $query->isSingleDimension());
    }

    #[TestWith([0, 'hour'])] // 0天差值 + 1 = 1天span -> hour
    #[TestWith([6, 'hour'])] // 6天差值 + 1 = 7天span -> hour (boundary)
    #[TestWith([7, 'day'])] // 7天差值 + 1 = 8天span -> day
    #[TestWith([30, 'day'])] // 30天差值 + 1 = 31天span -> day
    #[TestWith([89, 'day'])] // 89天差值 + 1 = 90天span -> day (boundary)
    #[TestWith([90, 'month'])] // 90天差值 + 1 = 91天span -> month
    #[TestWith([365, 'month'])] // 365天差值 + 1 = 366天span -> month
    public function testGetOptimalPeriodTypeCalculatesCorrectly(int $dayDiff, string $expectedPeriod): void
    {
        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = $startDate->modify("+{$dayDiff} days");

        $query = new UsageTrendQuery($startDate, $endDate);

        $this->assertSame($expectedPeriod, $query->getOptimalPeriodType());
    }

    public function testValueObjectImmutability(): void
    {
        $query = new UsageTrendQuery(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-31')
        );

        // 验证值对象是不可变的 - readonly属性不能被修改
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // 通过反射尝试修改readonly属性
        $reflection = new \ReflectionClass($query);
        $property = $reflection->getProperty('periodType');
        $property->setAccessible(true);
        $property->setValue($query, 'month');
    }

    public function testArrayPropertiesCanBeEmpty(): void
    {
        $query = new UsageTrendQuery(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-31'),
            models: [],
            features: []
        );

        $this->assertSame([], $query->models);
        $this->assertSame([], $query->features);
    }

    public function testArrayPropertiesCanContainMultipleValues(): void
    {
        $models = ['gpt-4', 'gpt-3.5-turbo', 'text-davinci-003'];
        $features = ['chat', 'completion', 'embedding', 'fine-tuning'];

        $query = new UsageTrendQuery(
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2023-01-31'),
            models: $models,
            features: $features
        );

        $this->assertSame($models, $query->models);
        $this->assertSame($features, $query->features);
        $this->assertCount(3, $query->models);
        $this->assertCount(4, $query->features);
    }

    public function testDateTimeObjectsAreRetained(): void
    {
        $startDate = new \DateTimeImmutable('2023-06-15 14:30:45');
        $endDate = new \DateTime('2023-06-15 15:30:45');

        $query = new UsageTrendQuery($startDate, $endDate);

        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertInstanceOf(\DateTimeInterface::class, $query->startDate);
        $this->assertInstanceOf(\DateTimeInterface::class, $query->endDate);
    }

    public function testLimitValueRanges(): void
    {
        // 测试不同的limit值
        $queries = [
            new UsageTrendQuery(new \DateTimeImmutable('2023-01-01'), new \DateTimeImmutable('2023-01-02'), limit: 1),
            new UsageTrendQuery(new \DateTimeImmutable('2023-01-01'), new \DateTimeImmutable('2023-01-02'), limit: 1000),
            new UsageTrendQuery(new \DateTimeImmutable('2023-01-01'), new \DateTimeImmutable('2023-01-02'), limit: 50),
        ];

        $this->assertSame(1, $queries[0]->limit);
        $this->assertSame(1000, $queries[1]->limit);
        $this->assertSame(50, $queries[2]->limit);
    }

    public function testDifferentPeriodTypes(): void
    {
        $validPeriodTypes = ['hour', 'day', 'month', 'week', 'year'];

        foreach ($validPeriodTypes as $periodType) {
            $query = new UsageTrendQuery(
                new \DateTimeImmutable('2023-01-01'),
                new \DateTimeImmutable('2023-01-31'),
                periodType: $periodType
            );

            $this->assertSame($periodType, $query->periodType);
        }
    }
}
