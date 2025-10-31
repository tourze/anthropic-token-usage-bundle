<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataQualityMetric;

/**
 * @internal
 */
#[CoversClass(DataQualityMetric::class)]
final class DataQualityMetricTest extends TestCase
{
    public function testConstructor(): void
    {
        $metric = new DataQualityMetric(
            name: 'accuracy',
            description: 'Data accuracy measurement',
            value: 0.95,
            threshold: 0.9,
            operator: 'greater_than',
            passing: true,
            severity: 'medium'
        );

        $this->assertSame('accuracy', $metric->name);
        $this->assertSame('Data accuracy measurement', $metric->description);
        $this->assertSame(0.95, $metric->value);
        $this->assertSame(0.9, $metric->threshold);
        $this->assertSame('greater_than', $metric->operator);
        $this->assertTrue($metric->passing);
        $this->assertSame('medium', $metric->severity);
    }
}
