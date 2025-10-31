<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;

/**
 * @internal
 */
#[CoversClass(UsageDetailQuery::class)]
final class UsageDetailQueryTest extends TestCase
{
    public function testConstructor(): void
    {
        $query = new UsageDetailQuery(
            accessKey: null,
            user: null,
            startDate: null,
            endDate: null,
            model: null,
            feature: null,
            dimensionId: null,
            models: null,
            features: null,
            requestId: null,
            page: 1,
            limit: 50,
            orderBy: 'occurTime',
            orderDirection: 'DESC'
        );

        $this->assertSame(1, $query->page);
        $this->assertSame(50, $query->limit);
        $this->assertSame('occurTime', $query->orderBy);
        $this->assertSame('DESC', $query->orderDirection);
    }
}
