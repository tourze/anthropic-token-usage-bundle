<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumersResult;

/**
 * @internal
 */
#[CoversClass(TopConsumersResult::class)]
final class TopConsumersResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $result = new TopConsumersResult(
            items: [],
            dimensionType: 'access_key',
            startDate: $startDate,
            endDate: $endDate,
            totalCount: 0,
            limit: 10
        );

        $this->assertSame([], $result->items);
        $this->assertSame('access_key', $result->dimensionType);
        $this->assertSame($startDate, $result->startDate);
        $this->assertSame($endDate, $result->endDate);
        $this->assertSame(0, $result->totalCount);
        $this->assertSame(10, $result->limit);
    }
}
