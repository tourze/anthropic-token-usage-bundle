<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Top消费者项目数据
 */
final readonly class TopConsumerItem
{
    /**
     * @param array<UsageTrendDataPoint> $trendData
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $dimensionId,
        public string $displayName,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalCacheCreationInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalCacheReadInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalOutputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalRequests,
        public ?\DateTimeInterface $firstUsageTime = null,
        public ?\DateTimeInterface $lastUsageTime = null,
        #[Assert\All(constraints: [
            new Assert\Type(type: UsageTrendDataPoint::class),
        ])]
        public array $trendData = [],
        public array $metadata = [],
    ) {
    }

    /**
     * 计算总Token数量
     */
    public function getTotalTokens(): int
    {
        return $this->totalInputTokens + $this->totalCacheCreationInputTokens
             + $this->totalCacheReadInputTokens + $this->totalOutputTokens;
    }

    /**
     * 计算平均每请求Token数量
     */
    public function getAverageTokensPerRequest(): float
    {
        return $this->totalRequests > 0 ? $this->getTotalTokens() / $this->totalRequests : 0.0;
    }

    /**
     * 检查是否有趋势数据
     */
    public function hasTrendData(): bool
    {
        return count($this->trendData) > 0;
    }

    /**
     * 获取缓存使用率
     */
    public function getCacheUsageRatio(): float
    {
        $totalCacheTokens = $this->totalCacheCreationInputTokens + $this->totalCacheReadInputTokens;
        $totalInputTokens = $this->totalInputTokens + $this->totalCacheCreationInputTokens + $this->totalCacheReadInputTokens;

        return $totalInputTokens > 0 ? $totalCacheTokens / $totalInputTokens : 0.0;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dimension_id' => $this->dimensionId,
            'display_name' => $this->displayName,
            'total_input_tokens' => $this->totalInputTokens,
            'total_cache_creation_input_tokens' => $this->totalCacheCreationInputTokens,
            'total_cache_read_input_tokens' => $this->totalCacheReadInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'total_requests' => $this->totalRequests,
            'first_usage_time' => $this->firstUsageTime?->format('Y-m-d H:i:s'),
            'last_usage_time' => $this->lastUsageTime?->format('Y-m-d H:i:s'),
            'calculated_metrics' => [
                'average_tokens_per_request' => $this->getAverageTokensPerRequest(),
                'cache_usage_ratio' => $this->getCacheUsageRatio(),
            ],
            'trend_data' => array_map(fn ($point) => $point->toArray(), $this->trendData),
            'metadata' => $this->metadata,
        ];
    }
}
