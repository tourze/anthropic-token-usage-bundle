<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * 使用量聚合数据值对象
 *
 * 用于表示从数据库聚合查询中获得的结构化数据，消除 mixed 类型
 */
final readonly class UsageAggregationData
{
    public function __construct(
        public string $dimensionId,  // accessKeyId 或 userId
        public int $totalInputTokens,
        public int $totalCacheCreationInputTokens,
        public int $totalCacheReadInputTokens,
        public int $totalOutputTokens,
        public int $totalRequests,
    ) {
    }

    /**
     * 从数据库查询结果数组创建实例
     *
     * @param array<string, mixed> $result
     */
    public static function fromQueryResult(array $result): self
    {
        $dimensionId = $result['accessKeyId'] ?? $result['userId'] ?? '';
        assert(is_string($dimensionId) || is_numeric($dimensionId));

        return new self(
            dimensionId: (string) $dimensionId,
            totalInputTokens: is_numeric($result['totalInputTokens'] ?? 0)
                ? (int) ($result['totalInputTokens'] ?? 0) : 0,
            totalCacheCreationInputTokens: is_numeric($result['totalCacheCreationInputTokens'] ?? 0)
                ? (int) ($result['totalCacheCreationInputTokens'] ?? 0) : 0,
            totalCacheReadInputTokens: is_numeric($result['totalCacheReadInputTokens'] ?? 0)
                ? (int) ($result['totalCacheReadInputTokens'] ?? 0) : 0,
            totalOutputTokens: is_numeric($result['totalOutputTokens'] ?? 0)
                ? (int) ($result['totalOutputTokens'] ?? 0) : 0,
            totalRequests: is_numeric($result['totalRequests'] ?? 0)
                ? (int) ($result['totalRequests'] ?? 0) : 0,
        );
    }

    /**
     * 计算总令牌数
     */
    public function getTotalTokens(): int
    {
        return $this->totalInputTokens
            + $this->totalCacheCreationInputTokens
            + $this->totalCacheReadInputTokens
            + $this->totalOutputTokens;
    }
}
