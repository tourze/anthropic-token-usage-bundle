<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Usage数据导出查询参数
 *
 * 定义导出usage数据的各种过滤条件和格式选项
 */
final readonly class UsageExportQuery
{
    /**
     * @param array<string>|null $dimensionTypes
     * @param array<string>|null $dimensionIds
     * @param array<string>|null $models
     * @param array<string>|null $features
     * @param array<string>|null $fields
     */
    public function __construct(
        public \DateTimeInterface $startDate,
        public \DateTimeInterface $endDate,
        #[Assert\Choice(choices: ['csv', 'json', 'xlsx'], multiple: false)]
        public string $format = 'csv',
        #[Assert\All(constraints: [
            new Assert\Choice(choices: ['access_key', 'user']),
        ])]
        public ?array $dimensionTypes = null,
        #[Assert\All(constraints: [
            new Assert\Type(type: 'string'),
            new Assert\Length(max: 100),
        ])]
        public ?array $dimensionIds = null,
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
        #[Assert\Choice(choices: ['raw', 'aggregated', 'both'])]
        public string $dataLevel = 'aggregated',
        #[Assert\Choice(choices: ['hour', 'day', 'month'])]
        public string $aggregationPeriod = 'day',
        #[Assert\All(constraints: [
            new Assert\Choice(choices: [
                'dimension_type', 'dimension_id', 'period_start', 'period_end',
                'model', 'feature', 'total_input_tokens', 'total_cache_creation_input_tokens',
                'total_cache_read_input_tokens', 'total_output_tokens', 'total_requests',
            ]),
        ])]
        public ?array $fields = null,
        #[Assert\Type(type: 'bool')]
        public bool $includeMetadata = true,
        #[Assert\Type(type: 'bool')]
        public bool $compressOutput = false,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 1000, max: 100000)]
        public int $batchSize = 10000,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 0)]
        public int $minTokenThreshold = 0,
        #[Assert\Type(type: 'string')]
        #[Assert\Length(max: 255)]
        public string $filename = '',
        #[Assert\Type(type: 'string')]
        #[Assert\Length(max: 1000)]
        public string $description = '',
    ) {
    }

    /**
     * 检查是否有维度类型过滤
     */
    public function hasDimensionTypeFilter(): bool
    {
        return null !== $this->dimensionTypes && [] !== $this->dimensionTypes;
    }

    /**
     * 检查是否有维度ID过滤
     */
    public function hasDimensionIdFilter(): bool
    {
        return null !== $this->dimensionIds && [] !== $this->dimensionIds;
    }

    /**
     * 检查是否有模型过滤
     */
    public function hasModelFilter(): bool
    {
        return null !== $this->models && [] !== $this->models;
    }

    /**
     * 检查是否有功能过滤
     */
    public function hasFeatureFilter(): bool
    {
        return null !== $this->features && [] !== $this->features;
    }

    /**
     * 检查是否有字段筛选
     */
    public function hasFieldSelection(): bool
    {
        return null !== $this->fields && [] !== $this->fields;
    }

    /**
     * 检查是否包含原始数据
     */
    public function includesRawData(): bool
    {
        return in_array($this->dataLevel, ['raw', 'both'], true);
    }

    /**
     * 检查是否包含聚合数据
     */
    public function includesAggregatedData(): bool
    {
        return in_array($this->dataLevel, ['aggregated', 'both'], true);
    }

    /**
     * 获取默认字段列表
     *
     * @return array<string>
     */
    public function getEffectiveFields(): array
    {
        if ($this->hasFieldSelection()) {
            return $this->fields ?? [];
        }

        // 根据数据级别返回默认字段
        if ('raw' === $this->dataLevel) {
            return [
                'dimension_type', 'dimension_id', 'createdAt',
                'model', 'feature', 'total_input_tokens', 'total_cache_creation_input_tokens',
                'total_cache_read_input_tokens', 'total_output_tokens', 'total_requests',
            ];
        }

        return [
            'dimension_type', 'dimension_id', 'period_start', 'period_end',
            'total_input_tokens', 'total_cache_creation_input_tokens',
            'total_cache_read_input_tokens', 'total_output_tokens', 'total_requests',
        ];
    }

    /**
     * 获取生成的文件名
     */
    public function getGeneratedFilename(): string
    {
        if ('' !== $this->filename) {
            return $this->filename;
        }

        $dateRange = $this->startDate->format('Ymd') . '_' . $this->endDate->format('Ymd');
        $timestamp = (new \DateTimeImmutable())->format('YmdHis');

        return "usage_export_{$this->dataLevel}_{$dateRange}_{$timestamp}.{$this->format}";
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
     * 估算导出数据量
     */
    public function getEstimatedDataSize(): int
    {
        $days = $this->getDateRangeDays();
        $dimensionCount = $this->hasDimensionIdFilter() ? (is_countable($this->dimensionIds) ? count($this->dimensionIds) : 0) : 100; // 默认估算

        // 根据聚合级别估算记录数
        $baseRecords = match ($this->aggregationPeriod) {
            'hour' => $days * 24,
            'day' => $days,
            'month' => max(1, intval($days / 30)),
            default => $days,
        };

        return $baseRecords * $dimensionCount;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate->format('Y-m-d H:i:s'),
            'format' => $this->format,
            'dimension_types' => $this->dimensionTypes,
            'dimension_ids' => $this->dimensionIds,
            'models' => $this->models,
            'features' => $this->features,
            'data_level' => $this->dataLevel,
            'aggregation_period' => $this->aggregationPeriod,
            'fields' => $this->getEffectiveFields(),
            'include_metadata' => $this->includeMetadata,
            'compress_output' => $this->compressOutput,
            'batch_size' => $this->batchSize,
            'min_token_threshold' => $this->minTokenThreshold,
            'generated_filename' => $this->getGeneratedFilename(),
            'description' => $this->description,
            'estimated_records' => $this->getEstimatedDataSize(),
        ];
    }
}
