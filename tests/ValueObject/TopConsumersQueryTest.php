<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumersQuery;

/**
 * @internal
 */
#[CoversClass(TopConsumersQuery::class)]
final class TopConsumersQueryTest extends TestCase
{
    public function testConstructor(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $query = new TopConsumersQuery(
            dimensionType: 'access_key',
            startDate: $startDate,
            endDate: $endDate,
            limit: 10,
            includeTrendData: true
        );

        $this->assertSame('access_key', $query->dimensionType);
        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertSame(10, $query->limit);
        $this->assertTrue($query->includeTrendData);
    }
}
