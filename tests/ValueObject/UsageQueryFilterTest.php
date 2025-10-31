<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;

/**
 * @internal
 */
#[CoversClass(UsageQueryFilter::class)]
final class UsageQueryFilterTest extends TestCase
{
    public function testConstructor(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $filter = new UsageQueryFilter(
            startDate: $startDate,
            endDate: $endDate,
            models: ['claude-3-opus'],
            features: ['chat']
        );

        $this->assertSame($startDate, $filter->startDate);
        $this->assertSame($endDate, $filter->endDate);
        $this->assertSame(['claude-3-opus'], $filter->models);
        $this->assertSame(['chat'], $filter->features);
    }
}
