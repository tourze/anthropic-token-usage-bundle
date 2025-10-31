<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;

/**
 * @internal
 */
#[CoversClass(UsageCollectionBatch::class)]
final class UsageCollectionBatchTest extends TestCase
{
    public function testConstructor(): void
    {
        $batch = new UsageCollectionBatch(
            items: []
        );

        $this->assertSame([], $batch->items);
    }
}
