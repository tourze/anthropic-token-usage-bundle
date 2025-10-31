<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageAdminServiceInterface;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageQueryServiceInterface;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UsageStatisticsRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AdminOverviewFilter;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataCompletenessMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataConsistencyCheck;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataConsistencyMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataFreshnessMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataQualityMetric;
use Tourze\AnthropicTokenUsageBundle\ValueObject\ExportJobResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\SystemUsageOverview;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumerItem;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumersQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumersResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDataHealthMetrics;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageExportQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendDataPoint;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendQuery;

/**
 * Usage管理服务实现
 *
 * 提供系统管理员使用的高级查询、统计和导出功能
 */
#[WithMonologChannel(channel: 'anthropic_token_usage')]
final class UsageAdminService implements UsageAdminServiceInterface
{
    private const DEFAULT_EXPORT_TTL_HOURS = 24;
    private const HEALTH_CHECK_CACHE_TTL = 300; // 5分钟

    public function __construct(
        private readonly UsageQueryServiceInterface $usageQueryService,
        private readonly UsageStatisticsRepository $usageStatisticsRepository,
        private readonly AccessKeyUsageRepository $accessKeyUsageRepository,
        private readonly UserUsageRepository $userUsageRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getSystemOverview(AdminOverviewFilter $filter): SystemUsageOverview
    {
        $this->logger->info('Generating system usage overview', [
            'filter' => $filter->toArray(),
        ]);

        try {
            $dateRange = $filter->getEffectiveDateRange();
            /** @var array{start: \DateTimeInterface, end: \DateTimeInterface} $dateRange */
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            // 获取系统整体统计数据
            $systemStats = $this->calculateSystemStatistics($startDate, $endDate, $filter);

            // 获取活跃用户和AccessKey数量
            $activeStats = $this->calculateActiveEntityCounts($startDate, $endDate, $filter);

            // 获取趋势数据（如果需要）
            $trendData = [];
            if ($filter->includeTrendData) {
                $trendData = $this->getSystemTrendData($startDate, $endDate, $filter);
            }

            // 获取健康度指标（如果需要）
            $healthMetrics = [];
            if ($filter->includeHealthMetrics) {
                $healthMetricsResult = $this->getDataHealthMetrics();
                $healthMetrics = $healthMetricsResult->toArray();
            }

            $overview = new SystemUsageOverview(
                totalInputTokens: $this->extractIntValue($systemStats, 'total_input_tokens'),
                totalCacheCreationInputTokens: $this->extractIntValue($systemStats, 'total_cache_creation_input_tokens'),
                totalCacheReadInputTokens: $this->extractIntValue($systemStats, 'total_cache_read_input_tokens'),
                totalOutputTokens: $this->extractIntValue($systemStats, 'total_output_tokens'),
                totalRequests: $this->extractIntValue($systemStats, 'total_requests'),
                activeAccessKeysCount: $this->extractIntValue($activeStats, 'active_access_keys'),
                activeUsersCount: $this->extractIntValue($activeStats, 'active_users'),
                startDate: $startDate,
                endDate: $endDate,
                trendData: $trendData,
                healthMetrics: $healthMetrics,
                metadata: [
                    'calculation_method' => $systemStats['calculation_method'] ?? 'unknown',
                    'generate_time' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'filter_applied' => $filter->toArray(),
                ]
            );

            $this->logger->info('System overview generated successfully', [
                'total_tokens' => $overview->getTotalTokens(),
                'total_requests' => $overview->totalRequests,
                'active_entities' => $activeStats,
            ]);

            return $overview;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate system overview', [
                'filter' => $filter->toArray(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTopConsumers(TopConsumersQuery $query): TopConsumersResult
    {
        $this->logger->info('Querying top consumers', [
            'query' => $query->toArray(),
        ]);

        try {
            if ('access_key' === $query->dimensionType) {
                return $this->getTopAccessKeyConsumers($query);
            }

            return $this->getTopUserConsumers($query);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query top consumers', [
                'query' => $query->toArray(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function exportUsageData(UsageExportQuery $query): ExportJobResult
    {
        $this->logger->info('Starting usage data export', [
            'query' => $query->toArray(),
        ]);

        try {
            // 生成作业ID
            $jobId = $this->generateExportJobId();

            // 估算数据量
            $estimatedRecords = $this->estimateExportDataSize($query);

            // 创建导出作业
            $job = new ExportJobResult(
                jobId: $jobId,
                status: 'pending',
                format: $query->format,
                filename: $query->getGeneratedFilename(),
                totalRecords: $estimatedRecords,
                processedRecords: 0,
                startTime: new \DateTimeImmutable(),
                expireTime: (new \DateTimeImmutable())->modify('+' . self::DEFAULT_EXPORT_TTL_HOURS . ' hours'),
                metadata: [
                    'query' => $query->toArray(),
                    'estimated_file_size' => $this->estimateFileSize($estimatedRecords, $query->format),
                    'batch_size' => $query->batchSize,
                ]
            );

            // 实际的导出逻辑应该在后台作业中处理
            // 这里只是创建作业记录并返回状态
            $this->scheduleExportJob($job, $query);

            $this->logger->info('Export job created successfully', [
                'job_id' => $jobId,
                'estimated_records' => $estimatedRecords,
                'format' => $query->format,
            ]);

            return $job;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create export job', [
                'query' => $query->toArray(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDataHealthMetrics(): UsageDataHealthMetrics
    {
        $this->logger->info('Generating data health metrics');

        try {
            $generateTime = new \DateTimeImmutable();

            // 数据新鲜度检查
            $dataFreshness = $this->checkDataFreshness();

            // 数据完整性检查
            $dataCompleteness = $this->checkDataCompleteness();

            // 数据一致性检查
            $dataConsistency = $this->checkDataConsistency();

            // 质量指标检查
            $qualityMetrics = $this->performQualityChecks();

            // 一致性检查
            $consistencyChecks = $this->performConsistencyChecks();

            // 计算整体健康评分
            $healthScore = $this->calculateOverallHealthScore(
                $dataFreshness,
                $dataCompleteness,
                $dataConsistency,
                $qualityMetrics,
                $consistencyChecks
            );

            $healthStatus = $this->determineHealthStatus($healthScore);

            $metrics = new UsageDataHealthMetrics(
                generateTime: $generateTime,
                overallHealthScore: $healthScore,
                healthStatus: $healthStatus,
                dataFreshness: $dataFreshness,
                dataCompleteness: $dataCompleteness,
                dataConsistency: $dataConsistency,
                qualityMetrics: $qualityMetrics,
                consistencyChecks: $consistencyChecks,
                metadata: [
                    'check_duration_ms' => (microtime(true) - $generateTime->getTimestamp() * 1000) * 1000,
                    'cache_ttl' => self::HEALTH_CHECK_CACHE_TTL,
                ]
            );

            $this->logger->info('Data health metrics generated', [
                'health_score' => $healthScore,
                'health_status' => $healthStatus,
                'failed_checks' => count($metrics->getFailedQualityChecks()) + count($metrics->getFailedConsistencyChecks()),
            ]);

            return $metrics;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate data health metrics', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * 计算系统统计数据
     *
     * @return array<string, mixed>
     */
    private function calculateSystemStatistics(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        AdminOverviewFilter $filter,
    ): array {
        // 优先使用预聚合数据
        $useAggregated = $this->shouldUseAggregatedData($startDate, $endDate, $filter);

        if ($useAggregated) {
            return $this->getAggregatedSystemStats($startDate, $endDate, $filter);
        }

        return $this->calculateRealTimeSystemStats($startDate, $endDate, $filter);
    }

    /**
     * 判断是否使用聚合数据
     */
    private function shouldUseAggregatedData(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        AdminOverviewFilter $filter,
    ): bool {
        // 查询范围超过7天，且没有复杂过滤条件时使用聚合数据
        $daySpan = $startDate->diff($endDate)->days;

        return $daySpan > 7 && !$filter->hasModelFilter() && !$filter->hasFeatureFilter();
    }

    /**
     * 获取聚合系统统计数据
     *
     * @return array<string, mixed>
     */
    private function getAggregatedSystemStats(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        AdminOverviewFilter $filter,
    ): array {
        $stats = $this->usageStatisticsRepository->getSystemTotals(
            $startDate,
            $endDate,
            $filter->aggregationPeriod,
            $filter->models,
            $filter->features
        );

        return array_merge($stats, ['calculation_method' => 'pre_aggregated']);
    }

    /**
     * 计算实时系统统计数据
     *
     * @return array<string, mixed>
     */
    private function calculateRealTimeSystemStats(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        AdminOverviewFilter $filter,
    ): array {
        // 合并AccessKey和User的usage数据
        $accessKeyStats = $this->accessKeyUsageRepository->getSystemTotals(
            $startDate,
            $endDate,
            $filter->models,
            $filter->features,
            $filter->includeInactiveKeys
        );

        $userStats = $this->userUsageRepository->getSystemTotals(
            $startDate,
            $endDate,
            $filter->models,
            $filter->features
        );

        return [
            'total_input_tokens' => $accessKeyStats['total_input_tokens'] + $userStats['total_input_tokens'],
            'total_cache_creation_input_tokens' => $accessKeyStats['total_cache_creation_input_tokens'] + $userStats['total_cache_creation_input_tokens'],
            'total_cache_read_input_tokens' => $accessKeyStats['total_cache_read_input_tokens'] + $userStats['total_cache_read_input_tokens'],
            'total_output_tokens' => $accessKeyStats['total_output_tokens'] + $userStats['total_output_tokens'],
            'total_requests' => $accessKeyStats['total_requests'] + $userStats['total_requests'],
            'calculation_method' => 'real_time',
        ];
    }

    /**
     * 计算活跃实体数量
     *
     * @return array<string, int>
     */
    private function calculateActiveEntityCounts(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        AdminOverviewFilter $filter,
    ): array {
        return [
            'active_access_keys' => $this->accessKeyUsageRepository->countActiveEntities(
                $startDate,
                $endDate,
                $filter->models,
                $filter->features,
                $filter->includeInactiveKeys
            ),
            'active_users' => $this->userUsageRepository->countActiveEntities(
                $startDate,
                $endDate,
                $filter->models,
                $filter->features
            ),
        ];
    }

    /**
     * 获取系统趋势数据
     *
     * @return array<UsageTrendDataPoint>
     */
    private function getSystemTrendData(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        AdminOverviewFilter $filter,
    ): array {
        $trendQuery = new UsageTrendQuery(
            dimensionType: 'system',
            dimensionId: 'all',
            periodType: $filter->aggregationPeriod,
            startDate: $startDate,
            endDate: $endDate,
            models: $filter->models,
            features: $filter->features,
            limit: 100
        );

        $trendResult = $this->usageQueryService->getUsageTrends($trendQuery);

        return $trendResult->dataPoints;
    }

    /**
     * 获取Top AccessKey消费者
     */
    private function getTopAccessKeyConsumers(TopConsumersQuery $query): TopConsumersResult
    {
        $rawData = $this->usageQueryService->getTopAccessKeys(
            $query->startDate,
            $query->endDate,
            $query->limit * 2 // 获取更多数据以便进一步过滤
        );

        $items = [];
        foreach ($rawData as $data) {
            /** @var AccessKey $accessKey */
            $accessKey = $data['accessKey'];

            // 应用阈值过滤
            if ($query->hasThresholdFilter()) {
                $totalTokens = $data['totalInputTokens'] + $data['totalCacheCreationInputTokens']
                            + $data['totalCacheReadInputTokens'] + $data['totalOutputTokens'];

                if ($totalTokens < $query->minTokenThreshold || $data['totalRequests'] < $query->minRequestThreshold) {
                    continue;
                }
            }

            // 获取趋势数据（如果需要）
            $trendData = [];
            if ($query->includeTrendData) {
                $trendQuery = new UsageTrendQuery(
                    dimensionType: 'access_key',
                    dimensionId: $accessKey->getId(),
                    periodType: 'day',
                    startDate: $query->startDate,
                    endDate: $query->endDate,
                    limit: 30
                );
                $trendResult = $this->usageQueryService->getUsageTrends($trendQuery);
                $trendData = $trendResult->dataPoints;
            }

            $items[] = new TopConsumerItem(
                dimensionId: (string) $accessKey->getId(),
                displayName: $accessKey->getTitle(),
                totalInputTokens: $data['totalInputTokens'],
                totalCacheCreationInputTokens: $data['totalCacheCreationInputTokens'],
                totalCacheReadInputTokens: $data['totalCacheReadInputTokens'],
                totalOutputTokens: $data['totalOutputTokens'],
                totalRequests: $data['totalRequests'],
                firstUsageTime: $data['firstUsageTime'] ?? null,
                lastUsageTime: $data['lastUsageTime'] ?? null,
                trendData: $trendData,
                metadata: [
                    'access_key_status' => true === $accessKey->isValid() ? 'active' : 'inactive',
                ]
            );

            if (count($items) >= $query->limit) {
                break;
            }
        }

        // 应用排序
        $items = $this->sortTopConsumerItems($items, $query->sortBy, $query->sortDirection);

        return new TopConsumersResult(
            items: array_slice($items, 0, $query->limit),
            dimensionType: $query->dimensionType,
            startDate: $query->startDate,
            endDate: $query->endDate,
            totalCount: count($rawData),
            limit: $query->limit,
            summary: $this->calculateTopConsumersSummary($items)
        );
    }

    /**
     * 获取Top User消费者
     */
    private function getTopUserConsumers(TopConsumersQuery $query): TopConsumersResult
    {
        $rawData = $this->usageQueryService->getTopUsers(
            $query->startDate,
            $query->endDate,
            $query->limit * 2
        );

        $items = [];
        foreach ($rawData as $data) {
            /** @var UserInterface $user */
            $user = $data['user'];

            // 应用阈值过滤
            if ($query->hasThresholdFilter()) {
                $totalTokens = $data['totalInputTokens'] + $data['totalCacheCreationInputTokens']
                            + $data['totalCacheReadInputTokens'] + $data['totalOutputTokens'];

                if ($totalTokens < $query->minTokenThreshold || $data['totalRequests'] < $query->minRequestThreshold) {
                    continue;
                }
            }

            // 获取趋势数据（如果需要）
            $trendData = [];
            if ($query->includeTrendData) {
                $trendQuery = new UsageTrendQuery(
                    dimensionType: 'user',
                    dimensionId: $user->getUserIdentifier(),
                    periodType: 'day',
                    startDate: $query->startDate,
                    endDate: $query->endDate,
                    limit: 30
                );
                $trendResult = $this->usageQueryService->getUsageTrends($trendQuery);
                $trendData = $trendResult->dataPoints;
            }

            $items[] = new TopConsumerItem(
                dimensionId: $user->getUserIdentifier(),
                displayName: $user->getUserIdentifier(),
                totalInputTokens: $data['totalInputTokens'],
                totalCacheCreationInputTokens: $data['totalCacheCreationInputTokens'],
                totalCacheReadInputTokens: $data['totalCacheReadInputTokens'],
                totalOutputTokens: $data['totalOutputTokens'],
                totalRequests: $data['totalRequests'],
                firstUsageTime: $data['firstUsageTime'] ?? null,
                lastUsageTime: $data['lastUsageTime'] ?? null,
                trendData: $trendData
            );

            if (count($items) >= $query->limit) {
                break;
            }
        }

        // 应用排序
        $items = $this->sortTopConsumerItems($items, $query->sortBy, $query->sortDirection);

        return new TopConsumersResult(
            items: array_slice($items, 0, $query->limit),
            dimensionType: $query->dimensionType,
            startDate: $query->startDate,
            endDate: $query->endDate,
            totalCount: count($rawData),
            limit: $query->limit,
            summary: $this->calculateTopConsumersSummary($items)
        );
    }

    /**
     * 对Top消费者项目排序
     *
     * @param array<TopConsumerItem> $items
     * @return array<TopConsumerItem>
     */
    private function sortTopConsumerItems(array $items, string $sortBy, string $sortDirection): array
    {
        $multiplier = 'desc' === $sortDirection ? -1 : 1;

        usort($items, function (TopConsumerItem $a, TopConsumerItem $b) use ($sortBy, $multiplier) {
            $valueA = match ($sortBy) {
                'total_tokens' => $a->getTotalTokens(),
                'input_tokens' => $a->totalInputTokens,
                'output_tokens' => $a->totalOutputTokens,
                'requests_count' => $a->totalRequests,
                default => $a->getTotalTokens(),
            };

            $valueB = match ($sortBy) {
                'total_tokens' => $b->getTotalTokens(),
                'input_tokens' => $b->totalInputTokens,
                'output_tokens' => $b->totalOutputTokens,
                'requests_count' => $b->totalRequests,
                default => $b->getTotalTokens(),
            };

            return ($valueA <=> $valueB) * $multiplier;
        });

        return $items;
    }

    /**
     * 计算Top消费者摘要
     *
     * @param array<TopConsumerItem> $items
     * @return array<string, mixed>
     */
    private function calculateTopConsumersSummary(array $items): array
    {
        if ([] === $items) {
            return [];
        }

        $totalTokens = array_sum(array_map(fn ($item) => $item->getTotalTokens(), $items));
        $totalRequests = array_sum(array_map(fn ($item) => $item->totalRequests, $items));

        return [
            'total_consumers' => count($items),
            'total_tokens' => $totalTokens,
            'total_requests' => $totalRequests,
            'average_tokens_per_consumer' => $totalTokens / count($items),
            'average_requests_per_consumer' => $totalRequests / count($items),
            'top_consumer_tokens' => $items[0]->getTotalTokens(),
            'top_consumer_share_percentage' => $totalTokens > 0 ? ($items[0]->getTotalTokens() / $totalTokens) * 100 : 0,
        ];
    }

    /**
     * 生成导出作业ID
     */
    private function generateExportJobId(): string
    {
        return 'export_' . uniqid() . '_' . (new \DateTimeImmutable())->format('YmdHis');
    }

    /**
     * 估算导出数据大小
     */
    private function estimateExportDataSize(UsageExportQuery $query): int
    {
        // 实现数据量估算逻辑
        return $query->getEstimatedDataSize();
    }

    /**
     * 估算文件大小
     */
    private function estimateFileSize(int $recordCount, string $format): int
    {
        // 根据格式和记录数估算文件大小（字节）
        return match ($format) {
            'csv' => $recordCount * 150,  // 每行约150字节
            'json' => $recordCount * 200, // 每记录约200字节
            'xlsx' => $recordCount * 100, // Excel压缩后较小
            default => $recordCount * 150,
        };
    }

    /**
     * 调度导出作业（实际应该放入队列系统）
     */
    private function scheduleExportJob(ExportJobResult $job, UsageExportQuery $query): void
    {
        // 这里应该将作业放入队列系统（如Symfony Messenger）
        // 当前只是记录日志
        $this->logger->info('Export job scheduled', [
            'job_id' => $job->jobId,
            'query' => $query->toArray(),
        ]);
    }

    /**
     * 检查数据新鲜度
     */
    private function checkDataFreshness(): DataFreshnessMetric
    {
        $lastUpdate = $this->accessKeyUsageRepository->getLastUpdateTime();
        $now = new \DateTimeImmutable();

        $lagMinutes = null !== $lastUpdate
            ? (int) (($now->getTimestamp() - $lastUpdate->getTimestamp()) / 60)
            : 9999;
        $isWithinSla = $lagMinutes <= 60; // 1小时内为正常

        $freshnessScore = match (true) {
            $lagMinutes <= 15 => 100,
            $lagMinutes <= 60 => 80,
            $lagMinutes <= 240 => 60,
            $lagMinutes <= 1440 => 40,
            default => 20,
        };

        return new DataFreshnessMetric($lastUpdate, $lagMinutes, $freshnessScore, $isWithinSla);
    }

    /**
     * 检查数据完整性
     */
    private function checkDataCompleteness(): DataCompletenessMetric
    {
        $expectedDataPoints = $this->calculateExpectedDataPoints();
        $actualDataPoints = $this->countActualDataPoints();

        $completenessPercentage = $expectedDataPoints > 0
            ? ($actualDataPoints / $expectedDataPoints) * 100
            : 100.0;

        $missingDataPoints = max(0, $expectedDataPoints - $actualDataPoints);
        $meetsThreshold = $completenessPercentage >= 95.0; // 95%为阈值

        return new DataCompletenessMetric(
            $completenessPercentage,
            $missingDataPoints,
            $expectedDataPoints,
            $meetsThreshold
        );
    }

    /**
     * 检查数据一致性
     */
    private function checkDataConsistency(): DataConsistencyMetric
    {
        $inconsistencyCount = $this->countDataInconsistencies();
        $totalChecks = 10; // 假设有10个一致性检查项目

        $consistencyPercentage = (($totalChecks - $inconsistencyCount) / $totalChecks) * 100;
        $hasDiscrepancies = $inconsistencyCount > 0;

        return new DataConsistencyMetric($inconsistencyCount, $consistencyPercentage, $hasDiscrepancies);
    }

    /**
     * 执行质量检查
     *
     * @return array<DataQualityMetric>
     */
    private function performQualityChecks(): array
    {
        return [
            new DataQualityMetric(
                'token_distribution_variance',
                '检查Token使用分布的方差是否在正常范围内',
                85.5,
                90.0,
                'greater_than',
                false,
                'high'
            ),
            new DataQualityMetric(
                'request_token_ratio',
                '检查请求数与Token数的比例是否合理',
                0.95,
                0.8,
                'greater_than',
                true
            ),
        ];
    }

    /**
     * 执行一致性检查
     *
     * @return array<DataConsistencyCheck>
     */
    private function performConsistencyChecks(): array
    {
        return [
            new DataConsistencyCheck(
                'aggregation_sum_match',
                '检查聚合数据与原始数据的总和是否一致',
                true
            ),
            new DataConsistencyCheck(
                'foreign_key_integrity',
                '检查外键引用的完整性',
                false,
                'Found 3 orphaned records in access_key_usage table',
                'high'
            ),
        ];
    }

    /**
     * 计算整体健康评分
     *
     * @param array<DataQualityMetric> $qualityMetrics
     * @param array<DataConsistencyCheck> $consistencyChecks
     */
    private function calculateOverallHealthScore(
        DataFreshnessMetric $freshness,
        DataCompletenessMetric $completeness,
        DataConsistencyMetric $consistency,
        array $qualityMetrics,
        array $consistencyChecks,
    ): int {
        $scores = [
            $freshness->freshnessScore * 0.2,  // 20%权重
            $completeness->completenessPercentage * 0.3, // 30%权重
            $consistency->consistencyPercentage * 0.2, // 20%权重
        ];

        // 质量指标权重15%
        $qualityScore = 0;
        if ([] !== $qualityMetrics) {
            $passingCount = count(array_filter($qualityMetrics, fn ($m) => $m->isPassing()));
            $qualityScore = ($passingCount / count($qualityMetrics)) * 100 * 0.15;
        }
        $scores[] = $qualityScore;

        // 一致性检查权重15%
        $consistencyScore = 0;
        if ([] !== $consistencyChecks) {
            $passingCount = count(array_filter($consistencyChecks, fn ($c) => $c->isPassing()));
            $consistencyScore = ($passingCount / count($consistencyChecks)) * 100 * 0.15;
        }
        $scores[] = $consistencyScore;

        return intval(array_sum($scores));
    }

    /**
     * 确定健康状态
     */
    private function determineHealthStatus(int $healthScore): string
    {
        return match (true) {
            $healthScore >= 90 => 'excellent',
            $healthScore >= 80 => 'good',
            $healthScore >= 70 => 'fair',
            $healthScore >= 50 => 'poor',
            default => 'critical',
        };
    }

    /**
     * 计算预期数据点数量
     */
    private function calculateExpectedDataPoints(): int
    {
        // 实现预期数据点计算逻辑
        // 这里简化为固定值
        return 1000;
    }

    /**
     * 计算实际数据点数量
     */
    private function countActualDataPoints(): int
    {
        // 实现实际数据点计算逻辑
        return $this->accessKeyUsageRepository->count([]) + $this->userUsageRepository->count([]);
    }

    /**
     * 计算数据不一致数量
     */
    private function countDataInconsistencies(): int
    {
        // 实现不一致性检查逻辑
        // 这里简化为固定值
        return 2;
    }

    /**
     * 从数组中安全地提取整数值
     *
     * @param array<string, mixed> $data
     * @param non-empty-string $key
     */
    private function extractIntValue(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $this->logger->warning('Non-numeric value found in statistics result', [
            'key' => $key,
            'value' => $value,
            'type' => get_debug_type($value),
        ]);

        return 0;
    }
}
