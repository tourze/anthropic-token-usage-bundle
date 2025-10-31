<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Usage统计结果
 */
final readonly class UsageStatisticsResult
{
    /**
     * @param array<UsageStatisticsPeriod> $periods
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

        /** @var array<UsageStatisticsPeriod> */
        #[Assert\All(constraints: [
            new Assert\Type(type: UsageStatisticsPeriod::class),
        ])]
        public array $periods,
        public ?\DateTimeInterface $startDate = null,
        public ?\DateTimeInterface $endDate = null,

        /** @var array<string, mixed> */
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
            'average_tokens_per_request' => $this->getAverageTokensPerRequest(),
            'periods' => array_map(fn ($period) => $period->toArray(), $this->periods),
            'start_date' => $this->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate?->format('Y-m-d H:i:s'),
        ];
    }
}
