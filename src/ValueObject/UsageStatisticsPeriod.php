<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Usage统计的时间周期数据
 */
final readonly class UsageStatisticsPeriod
{
    public function __construct(
        #[Assert\NotNull]
        public \DateTimeInterface $periodStart,
        #[Assert\NotNull]
        public \DateTimeInterface $periodEnd,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $inputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $cacheCreationInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $cacheReadInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $outputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $requests,
    ) {
    }

    /**
     * 计算总Token数量
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->cacheCreationInputTokens
             + $this->cacheReadInputTokens + $this->outputTokens;
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
            'input_tokens' => $this->inputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'requests' => $this->requests,
        ];
    }
}
