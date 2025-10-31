<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Interface;

use Tourze\AnthropicTokenUsageBundle\ValueObject\AggregationResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\RebuildResult;

/**
 * Usage聚合服务接口
 */
interface UsageAggregateServiceInterface
{
    /**
     * 执行增量聚合更新 (由定时任务调用)
     */
    public function performIncrementalAggregation(
        \DateTimeInterface $fromTime,
        \DateTimeInterface $toTime,
    ): AggregationResult;

    /**
     * 重建特定维度的聚合数据 (数据修复)
     */
    public function rebuildAggregateData(
        string $dimensionType,
        string $dimensionId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): RebuildResult;

    /**
     * 清理过期的聚合数据
     */
    public function cleanupExpiredData(\DateTimeInterface $before): int;
}
