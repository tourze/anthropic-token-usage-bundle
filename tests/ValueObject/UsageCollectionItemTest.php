<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionItem;

/**
 * @internal
 */
#[CoversClass(UsageCollectionItem::class)]
final class UsageCollectionItemTest extends TestCase
{
    public function testConstructor(): void
    {
        $usageData = new AnthropicUsageData(
            inputTokens: 100,
            cacheCreationInputTokens: 0,
            cacheReadInputTokens: 0,
            outputTokens: 50
        );

        $item = new UsageCollectionItem(
            usageData: $usageData,
            accessKey: null,
            user: null,
            metadata: ['test' => 'value']
        );

        $this->assertSame($usageData, $item->usageData);
        $this->assertNull($item->accessKey);
        $this->assertNull($item->user);
        $this->assertSame(['test' => 'value'], $item->metadata);
    }
}
