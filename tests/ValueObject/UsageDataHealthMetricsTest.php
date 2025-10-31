<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataCompletenessMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataConsistencyMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataFreshnessMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDataHealthMetrics;

/**
 * @internal
 */
#[CoversClass(UsageDataHealthMetrics::class)]
final class UsageDataHealthMetricsTest extends TestCase
{
    public function testConstructor(): void
    {
        $generateTime = new \DateTimeImmutable();
        $completeness = new DataCompletenessMetric(
            completenessPercentage: 95.0,
            missingDataPoints: 50,
            totalExpectedDataPoints: 1000,
            meetsThreshold: true
        );
        $consistency = new DataConsistencyMetric(
            inconsistencyCount: 1,
            consistencyPercentage: 90.0,
            hasDiscrepancies: true
        );
        $freshness = new DataFreshnessMetric(
            lastDataUpdate: new \DateTimeImmutable(),
            lagMinutes: 30,
            freshnessScore: 85,
            isWithinSla: true
        );

        $metrics = new UsageDataHealthMetrics(
            generateTime: $generateTime,
            overallHealthScore: 90,
            healthStatus: 'good',
            dataFreshness: $freshness,
            dataCompleteness: $completeness,
            dataConsistency: $consistency,
            qualityMetrics: [],
            consistencyChecks: [],
            metadata: []
        );

        $this->assertSame($generateTime, $metrics->generateTime);
        $this->assertSame(90, $metrics->overallHealthScore);
        $this->assertSame('good', $metrics->healthStatus);
        $this->assertSame($freshness, $metrics->dataFreshness);
        $this->assertSame($completeness, $metrics->dataCompleteness);
        $this->assertSame($consistency, $metrics->dataConsistency);
        $this->assertSame([], $metrics->qualityMetrics);
        $this->assertSame([], $metrics->consistencyChecks);
        $this->assertSame([], $metrics->metadata);
    }
}
