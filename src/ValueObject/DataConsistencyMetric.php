<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 数据一致性指标
 */
final readonly class DataConsistencyMetric
{
    public function __construct(
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $inconsistencyCount,
        #[Assert\Type(type: 'float')]
        #[Assert\Range(min: 0.0, max: 100.0)]
        public float $consistencyPercentage,
        #[Assert\Type(type: 'bool')]
        public bool $hasDiscrepancies,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'inconsistency_count' => $this->inconsistencyCount,
            'consistency_percentage' => $this->consistencyPercentage,
            'has_discrepancies' => $this->hasDiscrepancies,
        ];
    }
}
