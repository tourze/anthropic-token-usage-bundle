<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AdminOverviewFilter;

/**
 * AdminOverviewFilter 值对象单元测试
 * @internal
 */
#[CoversClass(AdminOverviewFilter::class)]
final class AdminOverviewFilterTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $filter = new AdminOverviewFilter();

        $this->assertNull($filter->startDate);
        $this->assertNull($filter->endDate);
        $this->assertSame('day', $filter->aggregationPeriod);
        $this->assertNull($filter->models);
        $this->assertNull($filter->features);
        $this->assertFalse($filter->includeInactiveKeys);
        $this->assertTrue($filter->includeTrendData);
        $this->assertTrue($filter->includeHealthMetrics);
    }

    public function testConstructorWithCustomValues(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $models = ['claude-3-opus', 'claude-3-sonnet'];
        $features = ['chat', 'completion'];

        $filter = new AdminOverviewFilter(
            startDate: $startDate,
            endDate: $endDate,
            aggregationPeriod: 'hour',
            models: $models,
            features: $features,
            includeInactiveKeys: true,
            includeTrendData: false,
            includeHealthMetrics: false
        );

        $this->assertSame($startDate, $filter->startDate);
        $this->assertSame($endDate, $filter->endDate);
        $this->assertSame('hour', $filter->aggregationPeriod);
        $this->assertSame($models, $filter->models);
        $this->assertSame($features, $filter->features);
        $this->assertTrue($filter->includeInactiveKeys);
        $this->assertFalse($filter->includeTrendData);
        $this->assertFalse($filter->includeHealthMetrics);
    }

    public function testGetEffectiveDateRange(): void
    {
        $filter = new AdminOverviewFilter();
        $dateRange = $filter->getEffectiveDateRange();

        $this->assertIsArray($dateRange);
        $this->assertArrayHasKey('start', $dateRange);
        $this->assertArrayHasKey('end', $dateRange);
        $this->assertInstanceOf(\DateTimeInterface::class, $dateRange['start']);
        $this->assertInstanceOf(\DateTimeInterface::class, $dateRange['end']);
    }

    public function testHasDateRange(): void
    {
        $filterWithoutDates = new AdminOverviewFilter();
        $this->assertFalse($filterWithoutDates->hasDateRange());

        $filterWithDates = new AdminOverviewFilter(
            startDate: new \DateTimeImmutable('2024-01-01')
        );
        $this->assertTrue($filterWithDates->hasDateRange());
    }

    public function testHasModelFilter(): void
    {
        $filterWithoutModels = new AdminOverviewFilter();
        $this->assertFalse($filterWithoutModels->hasModelFilter());

        $filterWithModels = new AdminOverviewFilter(models: ['claude-3-opus']);
        $this->assertTrue($filterWithModels->hasModelFilter());
    }

    public function testHasFeatureFilter(): void
    {
        $filterWithoutFeatures = new AdminOverviewFilter();
        $this->assertFalse($filterWithoutFeatures->hasFeatureFilter());

        $filterWithFeatures = new AdminOverviewFilter(features: ['chat']);
        $this->assertTrue($filterWithFeatures->hasFeatureFilter());
    }

    public function testToArray(): void
    {
        $filter = new AdminOverviewFilter(
            aggregationPeriod: 'month',
            models: ['claude-3-opus'],
            includeInactiveKeys: true
        );

        $array = $filter->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('start_date', $array);
        $this->assertArrayHasKey('end_date', $array);
        $this->assertArrayHasKey('aggregation_period', $array);
        $this->assertSame('month', $array['aggregation_period']);
        $this->assertSame(['claude-3-opus'], $array['models']);
        $this->assertTrue($array['include_inactive_keys']);
    }
}
