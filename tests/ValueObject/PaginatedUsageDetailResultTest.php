<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\PaginatedUsageDetailResult;

/**
 * @internal
 */
#[CoversClass(PaginatedUsageDetailResult::class)]
final class PaginatedUsageDetailResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new PaginatedUsageDetailResult(
            items: [],
            totalCount: 0,
            page: 1,
            limit: 50
        );

        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->totalCount);
        $this->assertSame(1, $result->page);
        $this->assertSame(50, $result->limit);
    }
}
