<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Usage查询过滤器
 */
final readonly class UsageQueryFilter
{
    /**
     * @param array<string>|null $models
     * @param array<string>|null $features
     */
    public function __construct(
        public ?\DateTimeInterface $startDate = null,
        public ?\DateTimeInterface $endDate = null,

        /** @var array<string>|null */
        #[Assert\All(constraints: [new Assert\Type(type: 'string')])]
        #[Assert\All(constraints: [new Assert\Length(max: 50)])]
        public ?array $models = null,

        /** @var array<string>|null */
        #[Assert\All(constraints: [new Assert\Type(type: 'string')])]
        #[Assert\All(constraints: [new Assert\Length(max: 50)])]
        public ?array $features = null,
        #[Assert\Choice(choices: ['hour', 'day', 'month'])]
        public string $aggregationPeriod = 'day',
    ) {
    }

    /**
     * 检查是否有日期范围过滤
     */
    public function hasDateRange(): bool
    {
        return null !== $this->startDate || null !== $this->endDate;
    }

    /**
     * 检查是否有模型过滤
     */
    public function hasModelFilter(): bool
    {
        return null !== $this->models && count($this->models) > 0;
    }

    /**
     * 检查是否有功能过滤
     */
    public function hasFeatureFilter(): bool
    {
        return null !== $this->features && count($this->features) > 0;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate?->format('Y-m-d H:i:s'),
            'models' => $this->models,
            'features' => $this->features,
            'aggregation_period' => $this->aggregationPeriod,
        ];
    }
}
