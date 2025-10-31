<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumerItem;

/**
 * @internal
 */
#[CoversClass(TopConsumerItem::class)]
final class TopConsumerItemTest extends TestCase
{
    public function testConstructor(): void
    {
        $item = new TopConsumerItem(
            dimensionId: 'key123',
            displayName: 'Test Key',
            totalInputTokens: 1000,
            totalCacheCreationInputTokens: 200,
            totalCacheReadInputTokens: 300,
            totalOutputTokens: 500,
            totalRequests: 100
        );

        $this->assertSame('key123', $item->dimensionId);
        $this->assertSame('Test Key', $item->displayName);
        $this->assertSame(1000, $item->totalInputTokens);
        $this->assertSame(200, $item->totalCacheCreationInputTokens);
        $this->assertSame(300, $item->totalCacheReadInputTokens);
        $this->assertSame(500, $item->totalOutputTokens);
        $this->assertSame(100, $item->totalRequests);
    }

    public function testGetTotalTokens(): void
    {
        $item = new TopConsumerItem(
            dimensionId: 'key123',
            displayName: 'Test Key',
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 50,
            totalCacheReadInputTokens: 25,
            totalOutputTokens: 75,
            totalRequests: 10
        );

        $this->assertSame(250, $item->getTotalTokens());
    }

    public function testGetAverageTokensPerRequest(): void
    {
        $item = new TopConsumerItem(
            dimensionId: 'key123',
            displayName: 'Test Key',
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 0,
            totalCacheReadInputTokens: 0,
            totalOutputTokens: 100,
            totalRequests: 10
        );

        $this->assertSame(20.0, $item->getAverageTokensPerRequest());
    }

    public function testToArray(): void
    {
        $item = new TopConsumerItem(
            dimensionId: 'key123',
            displayName: 'Test Key',
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 0,
            totalCacheReadInputTokens: 0,
            totalOutputTokens: 100,
            totalRequests: 10
        );

        $array = $item->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('dimension_id', $array);
        $this->assertArrayHasKey('total_tokens', $array);
    }
}
