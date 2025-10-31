<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 系统Usage概览数据
 *
 * 提供系统整体usage的概览统计信息
 */
final readonly class SystemUsageOverview
{
    /**
     * @param array<UsageTrendDataPoint> $trendData
     * @param array<string, mixed> $healthMetrics
     * @param array<string, mixed> $metadata
     */
    public function __construct(
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
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $activeAccessKeysCount,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $activeUsersCount,
        public \DateTimeInterface $startDate,
        public \DateTimeInterface $endDate,
        #[Assert\All(constraints: [
            new Assert\Type(type: UsageTrendDataPoint::class),
        ])]
        public array $trendData = [],
        public array $healthMetrics = [],
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
     * 计算平均每天请求数量
     */
    public function getAverageRequestsPerDay(): float
    {
        $daysDiff = $this->startDate->diff($this->endDate)->days;
        $days = $daysDiff > 0 ? $daysDiff : 1;

        return $this->totalRequests / $days;
    }

    /**
     * 计算平均每天Token数量
     */
    public function getAverageTokensPerDay(): float
    {
        $daysDiff = $this->startDate->diff($this->endDate)->days;
        $days = $daysDiff > 0 ? $daysDiff : 1;

        return $this->getTotalTokens() / $days;
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
     * 获取缓存命中率
     */
    public function getCacheHitRatio(): float
    {
        $totalCacheTokens = $this->totalCacheCreationInputTokens + $this->totalCacheReadInputTokens;

        return $totalCacheTokens > 0 ? $this->totalCacheReadInputTokens / $totalCacheTokens : 0.0;
    }

    /**
     * 检查是否有趋势数据
     */
    public function hasTrendData(): bool
    {
        return count($this->trendData) > 0;
    }

    /**
     * 检查是否有健康度指标
     */
    public function hasHealthMetrics(): bool
    {
        return count($this->healthMetrics) > 0;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_input_tokens' => $this->totalInputTokens,
            'total_cache_creation_input_tokens' => $this->totalCacheCreationInputTokens,
            'total_cache_read_input_tokens' => $this->totalCacheReadInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'total_requests' => $this->totalRequests,
            'active_access_keys_count' => $this->activeAccessKeysCount,
            'active_users_count' => $this->activeUsersCount,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate->format('Y-m-d H:i:s'),
            'calculated_metrics' => [
                'average_tokens_per_request' => $this->getAverageTokensPerRequest(),
                'average_requests_per_day' => $this->getAverageRequestsPerDay(),
                'average_tokens_per_day' => $this->getAverageTokensPerDay(),
                'cache_usage_ratio' => $this->getCacheUsageRatio(),
                'cache_hit_ratio' => $this->getCacheHitRatio(),
            ],
            'trend_data' => array_map(fn ($point) => $point->toArray(), $this->trendData),
            'health_metrics' => $this->healthMetrics,
            'metadata' => $this->metadata,
        ];
    }
}
