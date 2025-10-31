<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Message;

use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

/**
 * Usage收集异步消息
 *
 * 用于通过Symfony Messenger异步处理token usage数据
 */
final readonly class UsageCollectionMessage
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public AnthropicUsageData $usageData,
        public ?string $accessKeyId = null,
        public ?string $userId = null,
        public array $metadata = [],
    ) {
    }

    /**
     * 获取消息的唯一标识
     */
    public function getMessageId(): string
    {
        return md5(serialize([
            $this->accessKeyId,
            $this->userId,
            $this->usageData,
            $this->metadata['request_id'] ?? null,
        ]));
    }

    /**
     * 获取消息类型标识（用于路由和监控）
     */
    public function getMessageType(): string
    {
        return 'usage_collection';
    }

    /**
     * 检查是否有访问键关联
     */
    public function hasAccessKey(): bool
    {
        return null !== $this->accessKeyId;
    }

    /**
     * 检查是否有用户关联
     */
    public function hasUser(): bool
    {
        return null !== $this->userId;
    }

    /**
     * 获取消息优先级（用于队列排序）
     */
    public function getPriority(): int
    {
        // 有用户或访问键的消息优先级更高
        if ($this->hasUser() || $this->hasAccessKey()) {
            return 10;
        }

        return 5; // 默认优先级
    }

    /**
     * 转换为数组格式（用于序列化和调试）
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->getMessageId(),
            'message_type' => $this->getMessageType(),
            'usage_data' => [
                'input_tokens' => $this->usageData->inputTokens,
                'cache_creation_input_tokens' => $this->usageData->cacheCreationInputTokens,
                'cache_read_input_tokens' => $this->usageData->cacheReadInputTokens,
                'output_tokens' => $this->usageData->outputTokens,
                'total_tokens' => $this->usageData->getTotalTokens(),
            ],
            'access_key_id' => $this->accessKeyId,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
            'priority' => $this->getPriority(),
        ];
    }
}
