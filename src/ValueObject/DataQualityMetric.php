<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 数据质量指标项
 */
final readonly class DataQualityMetric
{
    public function __construct(
        public string $name,
        public string $description,
        #[Assert\Type(type: 'float')]
        public float $value,
        #[Assert\Type(type: 'float')]
        public float $threshold,
        #[Assert\Choice(choices: ['greater_than', 'less_than', 'equal_to'])]
        public string $operator,
        #[Assert\Type(type: 'bool')]
        public bool $passing,
        public string $severity = 'medium',
    ) {
    }

    public function isPassing(): bool
    {
        return $this->passing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'value' => $this->value,
            'threshold' => $this->threshold,
            'operator' => $this->operator,
            'passing' => $this->passing,
            'severity' => $this->severity,
        ];
    }
}
