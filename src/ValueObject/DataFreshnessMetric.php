<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 数据新鲜度指标
 */
final readonly class DataFreshnessMetric
{
    public function __construct(
        public ?\DateTimeInterface $lastDataUpdate,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $lagMinutes,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 0, max: 100)]
        public int $freshnessScore,
        #[Assert\Type(type: 'bool')]
        public bool $isWithinSla,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'last_data_update' => $this->lastDataUpdate?->format('Y-m-d H:i:s'),
            'lag_minutes' => $this->lagMinutes,
            'freshness_score' => $this->freshnessScore,
            'is_within_sla' => $this->isWithinSla,
        ];
    }
}
