<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * Usage趋势查询参数
 */
final readonly class UsageTrendQuery
{
    /**
     * @param array<string>|null $models 筛选特定模型
     * @param array<string>|null $features 筛选特定功能
     */
    public function __construct(
        public \DateTimeInterface $startDate,
        public \DateTimeInterface $endDate,
        public ?string $dimensionType = null,      // 'access_key' or 'user'
        public ?string $dimensionId = null,        // AccessKey.id or User.id
        public string $periodType = 'day',         // 'hour', 'day', 'month'
        /** @var array<string>|null */
        public ?array $models = null,              // 筛选特定模型
        /** @var array<string>|null */
        public ?array $features = null,            // 筛选特定功能
        public int $limit = 100,                    // 返回数据点数量限制
    ) {
    }

    /**
     * 创建AccessKey维度的趋势查询
     */
    public static function forAccessKey(
        string $accessKeyId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $periodType = 'day',
    ): self {
        return new self(
            startDate: $startDate,
            endDate: $endDate,
            dimensionType: 'access_key',
            dimensionId: $accessKeyId,
            periodType: $periodType
        );
    }

    /**
     * 创建User维度的趋势查询
     */
    public static function forUser(
        string $userId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $periodType = 'day',
    ): self {
        return new self(
            startDate: $startDate,
            endDate: $endDate,
            dimensionType: 'user',
            dimensionId: $userId,
            periodType: $periodType
        );
    }

    /**
     * 获取时间段天数
     */
    public function getDaySpan(): int
    {
        return (int) $this->startDate->diff($this->endDate)->days + 1;
    }

    /**
     * 检查是否为单一维度查询
     */
    public function isSingleDimension(): bool
    {
        return null !== $this->dimensionType && null !== $this->dimensionId;
    }

    /**
     * 获取合适的聚合周期（基于查询时间范围自动选择）
     */
    public function getOptimalPeriodType(): string
    {
        $daySpan = $this->getDaySpan();

        return match (true) {
            $daySpan <= 7 => 'hour',
            $daySpan <= 90 => 'day',
            default => 'month',
        };
    }
}
