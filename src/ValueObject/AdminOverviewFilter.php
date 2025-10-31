<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 管理概览过滤器
 *
 * 用于系统概览查询的过滤条件
 */
final readonly class AdminOverviewFilter
{
    /**
     * @param array<string>|null $models
     * @param array<string>|null $features
     */
    public function __construct(
        public ?\DateTimeInterface $startDate = null,
        public ?\DateTimeInterface $endDate = null,
        #[Assert\Choice(choices: ['hour', 'day', 'month'])]
        public string $aggregationPeriod = 'day',
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
        public bool $includeInactiveKeys = false,
        #[Assert\Type(type: 'bool')]
        public bool $includeTrendData = true,
        #[Assert\Type(type: 'bool')]
        public bool $includeHealthMetrics = true,
    ) {
    }

    /**
     * 获取有效的日期范围
     *
     * 如果没有设置日期范围，默认返回最近30天
     *
     * @return array{start: \DateTimeInterface, end: \DateTimeInterface}
     */
    public function getEffectiveDateRange(): array
    {
        $end = $this->endDate ?? new \DateTimeImmutable();
        $start = $this->startDate ?? (new \DateTimeImmutable())->modify('-30 days');

        return ['start' => $start, 'end' => $end];
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
        $dateRange = $this->getEffectiveDateRange();

        return [
            'start_date' => $dateRange['start']->format('Y-m-d H:i:s'),
            'end_date' => $dateRange['end']->format('Y-m-d H:i:s'),
            'aggregation_period' => $this->aggregationPeriod,
            'models' => $this->models,
            'features' => $this->features,
            'include_inactive_keys' => $this->includeInactiveKeys,
            'include_trend_data' => $this->includeTrendData,
            'include_health_metrics' => $this->includeHealthMetrics,
        ];
    }
}
