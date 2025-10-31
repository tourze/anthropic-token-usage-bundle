<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageExportQuery;

/**
 * @internal
 */
#[CoversClass(UsageExportQuery::class)]
final class UsageExportQueryTest extends TestCase
{
    public function testConstructor(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $query = new UsageExportQuery(
            startDate: $startDate,
            endDate: $endDate,
            format: 'csv'
        );

        $this->assertSame($startDate, $query->startDate);
        $this->assertSame($endDate, $query->endDate);
        $this->assertSame('csv', $query->format);
    }
}
