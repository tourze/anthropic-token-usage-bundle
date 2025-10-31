<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Top消费者查询参数
 *
 * 用于查询Top消费者（AccessKey或User维度）的过滤条件
 */
final readonly class TopConsumersQuery
{
    /**
     * @param array<string>|null $models
     * @param array<string>|null $features
     */
    public function __construct(
        #[Assert\Choice(choices: ['access_key', 'user'])]
        public string $dimensionType,
        public \DateTimeInterface $startDate,
        public \DateTimeInterface $endDate,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 1, max: 1000)]
        public int $limit = 10,
        #[Assert\Choice(choices: ['total_tokens', 'input_tokens', 'output_tokens', 'requests_count'])]
        public string $sortBy = 'total_tokens',
        #[Assert\Choice(choices: ['desc', 'asc'])]
        public string $sortDirection = 'desc',
        #[Assert\All(constraints: [
            new Assert\Type(type: 'string'),
            new Assert\Length(max: 50),
        ])]
        public ?array $models = null,
        #[Assert\All(constraints: [
            new Assert\Type(type: 'string'),
            new Assert\Length(max: 50),
        ])]
        public ?array $features = null,
        #[Assert\Type(type: 'bool')]
        public bool $includeInactive = false,
        #[Assert\Type(type: 'bool')]
        public bool $includeTrendData = false,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 0)]
        public int $minTokenThreshold = 0,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 0)]
        public int $minRequestThreshold = 0,
    ) {
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
     * 检查是否有阈值过滤
     */
    public function hasThresholdFilter(): bool
    {
        return $this->minTokenThreshold > 0 || $this->minRequestThreshold > 0;
    }

    /**
     * 检查排序字段是否基于Token
     */
    public function isSortByTokens(): bool
    {
        return in_array($this->sortBy, ['total_tokens', 'input_tokens', 'output_tokens'], true);
    }

    /**
     * 获取日期范围的天数
     */
    public function getDateRangeDays(): int
    {
        $days = $this->startDate->diff($this->endDate)->days;

        return (false !== $days && $days > 0) ? $days : 1;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dimension_type' => $this->dimensionType,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate->format('Y-m-d H:i:s'),
            'limit' => $this->limit,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
            'models' => $this->models,
            'features' => $this->features,
            'include_inactive' => $this->includeInactive,
            'include_trend_data' => $this->includeTrendData,
            'min_token_threshold' => $this->minTokenThreshold,
            'min_request_threshold' => $this->minRequestThreshold,
        ];
    }
}
