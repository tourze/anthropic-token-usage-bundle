<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

/**
 * UsageCollectionMessage测试
 * @internal
 */
#[CoversClass(UsageCollectionMessage::class)]
final class UsageCollectionMessageTest extends TestCase
{
    private AnthropicUsageData $usageData;

    protected function setUp(): void
    {
        $this->usageData = new AnthropicUsageData(
            inputTokens: 100,
            cacheCreationInputTokens: 10,
            cacheReadInputTokens: 5,
            outputTokens: 200
        );
    }

    public function testConstructor(): void
    {
        $metadata = ['request_id' => 'req-123', 'feature' => 'chat'];
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: 'ak-123',
            userId: 'user-456',
            metadata: $metadata
        );

        $this->assertSame($this->usageData, $message->usageData);
        $this->assertSame('ak-123', $message->accessKeyId);
        $this->assertSame('user-456', $message->userId);
        $this->assertSame($metadata, $message->metadata);
    }

    public function testConstructorWithDefaults(): void
    {
        $message = new UsageCollectionMessage($this->usageData);

        $this->assertSame($this->usageData, $message->usageData);
        $this->assertNull($message->accessKeyId);
        $this->assertNull($message->userId);
        $this->assertSame([], $message->metadata);
    }

    public function testGetMessageId(): void
    {
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: 'ak-123',
            userId: 'user-456'
        );

        $messageId = $message->getMessageId();

        $this->assertIsString($messageId);
        $this->assertSame(32, strlen($messageId)); // MD5哈希长度

        // 相同参数应该产生相同的ID
        $message2 = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: 'ak-123',
            userId: 'user-456'
        );
        $this->assertSame($messageId, $message2->getMessageId());
    }

    public function testGetMessageIdWithMetadata(): void
    {
        $message1 = new UsageCollectionMessage(
            usageData: $this->usageData,
            metadata: ['request_id' => 'req-123']
        );

        $message2 = new UsageCollectionMessage(
            usageData: $this->usageData,
            metadata: ['request_id' => 'req-456']
        );

        // 不同的request_id应该产生不同的消息ID
        $this->assertNotSame($message1->getMessageId(), $message2->getMessageId());
    }

    public function testGetMessageType(): void
    {
        $message = new UsageCollectionMessage($this->usageData);

        $this->assertSame('usage_collection', $message->getMessageType());
    }

    #[DataProvider('hasAccessKeyProvider')]
    public function testHasAccessKey(bool $expected, ?string $accessKeyId): void
    {
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: $accessKeyId
        );

        $this->assertSame($expected, $message->hasAccessKey());
    }

    /**
     * @return iterable<string, array{bool, ?string}>
     */
    public static function hasAccessKeyProvider(): iterable
    {
        yield 'with access key' => [true, 'ak-123'];
        yield 'without access key' => [false, null];
        yield 'with empty string access key' => [true, '']; // 空字符串仍然被认为是有accessKey
    }

    #[DataProvider('hasUserProvider')]
    public function testHasUser(bool $expected, ?string $userId): void
    {
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            userId: $userId
        );

        $this->assertSame($expected, $message->hasUser());
    }

    /**
     * @return iterable<string, array{bool, ?string}>
     */
    public static function hasUserProvider(): iterable
    {
        yield 'with user' => [true, 'user-123'];
        yield 'without user' => [false, null];
        yield 'with empty string user' => [true, '']; // 空字符串仍然被认为是有user
    }

    #[DataProvider('priorityProvider')]
    public function testGetPriority(int $expected, ?string $accessKeyId, ?string $userId): void
    {
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: $accessKeyId,
            userId: $userId
        );

        $this->assertSame($expected, $message->getPriority());
    }

    /**
     * @return iterable<string, array{int, ?string, ?string}>
     */
    public static function priorityProvider(): iterable
    {
        yield 'with user and access key' => [10, 'ak-123', 'user-456'];
        yield 'with user only' => [10, null, 'user-456'];
        yield 'with access key only' => [10, 'ak-123', null];
        yield 'without user or access key' => [5, null, null];
    }

    public function testToArray(): void
    {
        $metadata = ['request_id' => 'req-123', 'feature' => 'chat'];
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: 'ak-123',
            userId: 'user-456',
            metadata: $metadata
        );

        $array = $message->toArray();

        $this->assertIsArray($array);
        $this->assertSame($message->getMessageId(), $array['message_id']);
        $this->assertSame('usage_collection', $array['message_type']);
        $this->assertSame('ak-123', $array['access_key_id']);
        $this->assertSame('user-456', $array['user_id']);
        $this->assertSame($metadata, $array['metadata']);
        $this->assertSame(10, $array['priority']);

        // 验证usage_data结构
        $this->assertArrayHasKey('usage_data', $array);
        $usageDataArray = $array['usage_data'];
        $this->assertIsArray($usageDataArray);
        $this->assertSame(100, $usageDataArray['input_tokens']);
        $this->assertSame(200, $usageDataArray['output_tokens']);
        $this->assertSame(10, $usageDataArray['cache_creation_input_tokens']);
        $this->assertSame(5, $usageDataArray['cache_read_input_tokens']);
        $this->assertSame(315, $usageDataArray['total_tokens']); // 100+200+10+5
    }

    public function testToArrayWithNullValues(): void
    {
        $message = new UsageCollectionMessage($this->usageData);

        $array = $message->toArray();

        $this->assertNull($array['access_key_id']);
        $this->assertNull($array['user_id']);
        $this->assertSame([], $array['metadata']);
        $this->assertSame(5, $array['priority']); // 默认优先级
    }

    public function testMessageIsReadOnly(): void
    {
        $message = new UsageCollectionMessage($this->usageData);

        // readonly类的反射检查
        $reflection = new \ReflectionClass($message);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testMessageIdConsistency(): void
    {
        $message = new UsageCollectionMessage(
            usageData: $this->usageData,
            accessKeyId: 'ak-123'
        );

        // 多次调用应该返回相同的ID
        $id1 = $message->getMessageId();
        $id2 = $message->getMessageId();

        $this->assertSame($id1, $id2);
    }

    public function testDifferentUsageDataGeneratesDifferentIds(): void
    {
        $usageData2 = new AnthropicUsageData(
            inputTokens: 50,
            cacheCreationInputTokens: 0,
            cacheReadInputTokens: 0,
            outputTokens: 100
        );

        $message1 = new UsageCollectionMessage($this->usageData);
        $message2 = new UsageCollectionMessage($usageData2);

        $this->assertNotSame($message1->getMessageId(), $message2->getMessageId());
    }
}
