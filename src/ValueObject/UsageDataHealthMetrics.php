<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Usage数据健康度指标
 *
 * 提供系统数据质量和健康状态的综合评估
 */
final readonly class UsageDataHealthMetrics
{
    /**
     * @param array<DataQualityMetric> $qualityMetrics
     * @param array<DataConsistencyCheck> $consistencyChecks
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public \DateTimeInterface $generateTime,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 0, max: 100)]
        public int $overallHealthScore,
        #[Assert\Choice(choices: ['excellent', 'good', 'fair', 'poor', 'critical'])]
        public string $healthStatus,
        public DataFreshnessMetric $dataFreshness,
        public DataCompletenessMetric $dataCompleteness,
        public DataConsistencyMetric $dataConsistency,

        /** @var array<DataQualityMetric> */
        #[Assert\All(constraints: [
            new Assert\Type(type: DataQualityMetric::class),
        ])]
        public array $qualityMetrics = [],

        /** @var array<DataConsistencyCheck> */
        #[Assert\All(constraints: [
            new Assert\Type(type: DataConsistencyCheck::class),
        ])]
        public array $consistencyChecks = [],

        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {
    }

    /**
     * 检查整体健康状态是否良好
     */
    public function isHealthy(): bool
    {
        return in_array($this->healthStatus, ['excellent', 'good'], true);
    }

    /**
     * 检查是否需要关注
     */
    public function needsAttention(): bool
    {
        return in_array($this->healthStatus, ['fair', 'poor'], true);
    }

    /**
     * 检查是否处于关键状态
     */
    public function isCritical(): bool
    {
        return 'critical' === $this->healthStatus;
    }

    /**
     * 获取失败的质量检查
     *
     * @return array<DataQualityMetric>
     */
    public function getFailedQualityChecks(): array
    {
        return array_filter($this->qualityMetrics, fn ($metric) => !$metric->isPassing());
    }

    /**
     * 获取失败的一致性检查
     *
     * @return array<DataConsistencyCheck>
     */
    public function getFailedConsistencyChecks(): array
    {
        return array_filter($this->consistencyChecks, fn ($check) => !$check->isPassing());
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generateTime->format('Y-m-d H:i:s'),
            'overall_health_score' => $this->overallHealthScore,
            'health_status' => $this->healthStatus,
            'data_freshness' => $this->dataFreshness->toArray(),
            'data_completeness' => $this->dataCompleteness->toArray(),
            'data_consistency' => $this->dataConsistency->toArray(),
            'quality_metrics' => array_map(fn ($metric) => $metric->toArray(), $this->qualityMetrics),
            'consistency_checks' => array_map(fn ($check) => $check->toArray(), $this->consistencyChecks),
            'flags' => [
                'is_healthy' => $this->isHealthy(),
                'needs_attention' => $this->needsAttention(),
                'is_critical' => $this->isCritical(),
            ],
            'failed_checks' => [
                'quality' => array_map(fn ($metric) => $metric->toArray(), $this->getFailedQualityChecks()),
                'consistency' => array_map(fn ($check) => $check->toArray(), $this->getFailedConsistencyChecks()),
            ],
            'metadata' => $this->metadata,
        ];
    }
}
