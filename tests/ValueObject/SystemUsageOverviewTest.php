<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\SystemUsageOverview;

/**
 * @internal
 */
#[CoversClass(SystemUsageOverview::class)]
final class SystemUsageOverviewTest extends TestCase
{
    public function testConstructor(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        $overview = new SystemUsageOverview(
            totalInputTokens: 1000,
            totalCacheCreationInputTokens: 200,
            totalCacheReadInputTokens: 300,
            totalOutputTokens: 500,
            totalRequests: 100,
            activeAccessKeysCount: 10,
            activeUsersCount: 5,
            startDate: $startDate,
            endDate: $endDate
        );

        $this->assertSame(1000, $overview->totalInputTokens);
        $this->assertSame(200, $overview->totalCacheCreationInputTokens);
        $this->assertSame(300, $overview->totalCacheReadInputTokens);
        $this->assertSame(500, $overview->totalOutputTokens);
        $this->assertSame(100, $overview->totalRequests);
        $this->assertSame(10, $overview->activeAccessKeysCount);
        $this->assertSame(5, $overview->activeUsersCount);
        $this->assertSame($startDate, $overview->startDate);
        $this->assertSame($endDate, $overview->endDate);
    }
}
