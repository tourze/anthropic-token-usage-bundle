<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataConsistencyMetric;

/**
 * @internal
 */
#[CoversClass(DataConsistencyMetric::class)]
final class DataConsistencyMetricTest extends TestCase
{
    public function testConstructor(): void
    {
        $metric = new DataConsistencyMetric(
            inconsistencyCount: 1,
            consistencyPercentage: 90.0,
            hasDiscrepancies: true
        );

        $this->assertSame(1, $metric->inconsistencyCount);
        $this->assertSame(90.0, $metric->consistencyPercentage);
        $this->assertTrue($metric->hasDiscrepancies);
    }
}
