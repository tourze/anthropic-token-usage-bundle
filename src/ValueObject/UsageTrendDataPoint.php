<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * Usage趋势数据点
 */
final readonly class UsageTrendDataPoint
{
    public function __construct(
        public \DateTimeInterface $periodStart,
        public \DateTimeInterface $periodEnd,
        public int $totalInputTokens,
        public int $totalCacheCreationInputTokens,
        public int $totalCacheReadInputTokens,
        public int $totalOutputTokens,
        public int $totalRequests,
    ) {
    }

    /**
     * 获取总token数量
     */
    public function getTotalTokens(): int
    {
        return $this->totalInputTokens + $this->totalCacheCreationInputTokens +
               $this->totalCacheReadInputTokens + $this->totalOutputTokens;
    }

    /**
     * 获取输入token总数（包含缓存）
     */
    public function getTotalInputTokens(): int
    {
        return $this->totalInputTokens + $this->totalCacheCreationInputTokens + $this->totalCacheReadInputTokens;
    }

    /**
     * 计算平均每次请求的token使用量
     */
    public function getAverageTokensPerRequest(): float
    {
        return $this->totalRequests > 0 ? $this->getTotalTokens() / $this->totalRequests : 0.0;
    }

    /**
     * 获取缓存命中率
     */
    public function getCacheHitRate(): float
    {
        $totalInput = $this->getTotalInputTokens();

        return $totalInput > 0 ? $this->totalCacheReadInputTokens / $totalInput : 0.0;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period_start' => $this->periodStart->format('Y-m-d H:i:s'),
            'period_end' => $this->periodEnd->format('Y-m-d H:i:s'),
            'total_input_tokens' => $this->totalInputTokens,
            'total_cache_creation_input_tokens' => $this->totalCacheCreationInputTokens,
            'total_cache_read_input_tokens' => $this->totalCacheReadInputTokens,
            'total_output_tokens' => $this->totalOutputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'total_requests' => $this->totalRequests,
            'average_tokens_per_request' => $this->getAverageTokensPerRequest(),
            'cache_hit_rate' => $this->getCacheHitRate(),
        ];
    }
}
