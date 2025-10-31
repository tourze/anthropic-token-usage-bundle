<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 数据完整性指标
 */
final readonly class DataCompletenessMetric
{
    public function __construct(
        #[Assert\Type(type: 'float')]
        #[Assert\Range(min: 0.0, max: 100.0)]
        public float $completenessPercentage,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $missingDataPoints,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalExpectedDataPoints,
        #[Assert\Type(type: 'bool')]
        public bool $meetsThreshold,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'completeness_percentage' => $this->completenessPercentage,
            'missing_data_points' => $this->missingDataPoints,
            'total_expected_data_points' => $this->totalExpectedDataPoints,
            'meets_threshold' => $this->meetsThreshold,
        ];
    }
}
