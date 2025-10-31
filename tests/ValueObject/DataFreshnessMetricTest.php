<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataFreshnessMetric;

/**
 * @internal
 */
#[CoversClass(DataFreshnessMetric::class)]
final class DataFreshnessMetricTest extends TestCase
{
    public function testConstructor(): void
    {
        $lastUpdate = new \DateTimeImmutable('2024-01-01 12:00:00');

        $metric = new DataFreshnessMetric(
            lastDataUpdate: $lastUpdate,
            lagMinutes: 30,
            freshnessScore: 85,
            isWithinSla: true
        );

        $this->assertSame($lastUpdate, $metric->lastDataUpdate);
        $this->assertSame(30, $metric->lagMinutes);
        $this->assertSame(85, $metric->freshnessScore);
        $this->assertTrue($metric->isWithinSla);
    }
}
