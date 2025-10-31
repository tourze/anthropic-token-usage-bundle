<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataCompletenessMetric;

/**
 * @internal
 */
#[CoversClass(DataCompletenessMetric::class)]
final class DataCompletenessMetricTest extends TestCase
{
    public function testConstructor(): void
    {
        $metric = new DataCompletenessMetric(
            completenessPercentage: 95.0,
            missingDataPoints: 50,
            totalExpectedDataPoints: 1000,
            meetsThreshold: true
        );

        $this->assertSame(95.0, $metric->completenessPercentage);
        $this->assertSame(50, $metric->missingDataPoints);
        $this->assertSame(1000, $metric->totalExpectedDataPoints);
        $this->assertTrue($metric->meetsThreshold);
    }
}
