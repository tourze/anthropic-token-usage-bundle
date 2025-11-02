<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageQueryServiceInterface;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UsageStatisticsRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\PaginatedUsageDetailResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendDataPoint;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendResult;

/**
 * Usage查询服务实现
 *
 * 提供多维度、高性能的usage数据查询功能
 */
#[WithMonologChannel(channel: 'anthropic_token_usage')]
final readonly class UsageQueryService implements UsageQueryServiceInterface
{
    public function __construct(
        private AccessKeyUsageRepository $accessKeyUsageRepository,
        private UserUsageRepository $userUsageRepository,
        private UsageStatisticsRepository $usageStatisticsRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function getAccessKeyUsageStatistics(
        AccessKey $accessKey,
        UsageQueryFilter $filter,
    ): UsageStatisticsResult {
        $this->logger->debug('Querying AccessKey usage statistics', [
            'access_key_id' => $accessKey->getId(),
            'filter' => $this->filterToArray($filter),
        ]);

        try {
            // 优先使用预聚合数据
            if ($this->shouldUsePreAggregatedData($filter)) {
                return $this->getAccessKeyPreAggregatedStats($accessKey, $filter);
            }

            // 实时计算统计数据
            return $this->calculateAccessKeyStats($accessKey, $filter);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query AccessKey usage statistics', [
                'access_key_id' => $accessKey->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUserUsageStatistics(
        UserInterface $user,
        UsageQueryFilter $filter,
    ): UsageStatisticsResult {
        $this->logger->debug('Querying User usage statistics', [
            'user_id' => $user->getUserIdentifier(),
            'filter' => $this->filterToArray($filter),
        ]);

        try {
            // 优先使用预聚合数据
            if ($this->shouldUsePreAggregatedData($filter)) {
                return $this->getUserPreAggregatedStats($user, $filter);
            }

            // 实时计算统计数据
            return $this->calculateUserStats($user, $filter);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query User usage statistics', [
                'user_id' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUsageDetails(UsageDetailQuery $query): PaginatedUsageDetailResult
    {
        $this->logger->debug('Querying usage details', [
            'query' => $query->toArray(),
        ]);

        try {
            if ($query->hasAccessKeyFilter()) {
                $results = $this->accessKeyUsageRepository->findByQuery($query);
                $totalCount = $this->accessKeyUsageRepository->countByQuery($query);
            } else {
                $results = $this->userUsageRepository->findByQuery($query);
                $totalCount = $this->userUsageRepository->countByQuery($query);
            }

            return new PaginatedUsageDetailResult(
                items: $results,
                totalCount: $totalCount,
                page: $query->page,
                limit: $query->limit
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query usage details', [
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
    public function getUsageTrends(UsageTrendQuery $query): UsageTrendResult
    {
        $this->logger->debug('Querying usage trends', [
            'dimension_type' => $query->dimensionType,
            'dimension_id' => $query->dimensionId,
            'period_type' => $query->periodType,
            'date_range' => [
                'start' => $query->startDate->format('Y-m-d H:i:s'),
                'end' => $query->endDate->format('Y-m-d H:i:s'),
            ],
        ]);

        try {
            $statistics = $this->usageStatisticsRepository->findTrendData(
                dimensionType: $query->dimensionType ?? 'access_key',
                dimensionId: $query->dimensionId ?? 'all',
                periodType: $query->periodType,
                startDate: $query->startDate,
                endDate: $query->endDate,
                models: $query->models,
                features: $query->features,
                limit: $query->limit
            );

            $dataPoints = array_map(
                function (array $stat): UsageTrendDataPoint {
                    assert(is_string($stat['period_start']));
                    assert(is_string($stat['period_end']));
                    assert(is_int($stat['total_input_tokens']));
                    assert(is_int($stat['total_cache_creation_input_tokens']));
                    assert(is_int($stat['total_cache_read_input_tokens']));
                    assert(is_int($stat['total_output_tokens']));
                    assert(is_int($stat['total_requests']));

                    return new UsageTrendDataPoint(
                        periodStart: new \DateTimeImmutable($stat['period_start']),
                        periodEnd: new \DateTimeImmutable($stat['period_end']),
                        totalInputTokens: $stat['total_input_tokens'],
                        totalCacheCreationInputTokens: $stat['total_cache_creation_input_tokens'],
                        totalCacheReadInputTokens: $stat['total_cache_read_input_tokens'],
                        totalOutputTokens: $stat['total_output_tokens'],
                        totalRequests: $stat['total_requests']
                    );
                },
                $statistics
            );

            return new UsageTrendResult(
                dataPoints: $dataPoints,
                startDate: $query->startDate,
                endDate: $query->endDate,
                periodType: $query->periodType,
                summary: $this->calculateTrendSummary($dataPoints)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query usage trends', [
                'query_params' => [
                    'dimension_type' => $query->dimensionType,
                    'dimension_id' => $query->dimensionId,
                    'period_type' => $query->periodType,
                ],
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTopAccessKeys(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10,
    ): array {
        $this->logger->debug('Querying top AccessKeys', [
            'date_range' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s'),
            ],
            'limit' => $limit,
        ]);

        try {
            return $this->accessKeyUsageRepository->findTopConsumers($startDate, $endDate, $limit);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query top AccessKeys', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTopUsers(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10,
    ): array {
        $this->logger->debug('Querying top Users', [
            'date_range' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s'),
            ],
            'limit' => $limit,
        ]);

        try {
            return $this->userUsageRepository->findTopConsumers($startDate, $endDate, $limit);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to query top Users', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * 判断是否应该使用预聚合数据
     */
    private function shouldUsePreAggregatedData(UsageQueryFilter $filter): bool
    {
        // 如果查询时间范围超过7天，优先使用预聚合数据
        if (null !== $filter->startDate && null !== $filter->endDate) {
            $daySpan = $filter->startDate->diff($filter->endDate)->days;

            return $daySpan > 7;
        }

        // 如果没有复杂过滤条件，使用预聚合数据
        return (null === $filter->models || [] === $filter->models)
            && (null === $filter->features || [] === $filter->features);
    }

    /**
     * 获取AccessKey的预聚合统计数据
     */
    private function getAccessKeyPreAggregatedStats(AccessKey $accessKey, UsageQueryFilter $filter): UsageStatisticsResult
    {
        $accessKeyId = $accessKey->getId();
        if (null === $accessKeyId) {
            throw new \InvalidArgumentException('AccessKey ID cannot be null');
        }

        $statistics = $this->usageStatisticsRepository->findByDimension(
            dimensionType: 'access_key',
            dimensionId: $accessKeyId,
            periodType: $filter->aggregationPeriod ?? 'day',
            startDate: $filter->startDate,
            endDate: $filter->endDate
        );

        return $this->buildStatisticsResult($statistics, $filter);
    }

    /**
     * 获取User的预聚合统计数据
     */
    private function getUserPreAggregatedStats(UserInterface $user, UsageQueryFilter $filter): UsageStatisticsResult
    {
        $statistics = $this->usageStatisticsRepository->findByDimension(
            dimensionType: 'user',
            dimensionId: $user->getUserIdentifier(),
            periodType: $filter->aggregationPeriod ?? 'day',
            startDate: $filter->startDate,
            endDate: $filter->endDate
        );

        return $this->buildStatisticsResult($statistics, $filter);
    }

    /**
     * 实时计算AccessKey统计数据
     */
    private function calculateAccessKeyStats(AccessKey $accessKey, UsageQueryFilter $filter): UsageStatisticsResult
    {
        $usageData = $this->accessKeyUsageRepository->calculateStatistics(
            $accessKey,
            $filter->startDate,
            $filter->endDate,
            $filter->models,
            $filter->features
        );

        assert(is_int($usageData['total_input_tokens']));
        assert(is_int($usageData['total_cache_creation_input_tokens']));
        assert(is_int($usageData['total_cache_read_input_tokens']));
        assert(is_int($usageData['total_output_tokens']));
        assert(is_int($usageData['total_requests']));

        return new UsageStatisticsResult(
            totalInputTokens: $usageData['total_input_tokens'],
            totalCacheCreationInputTokens: $usageData['total_cache_creation_input_tokens'],
            totalCacheReadInputTokens: $usageData['total_cache_read_input_tokens'],
            totalOutputTokens: $usageData['total_output_tokens'],
            totalRequests: $usageData['total_requests'],
            periods: [], // 实时计算没有周期数据
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            metadata: [
                'dimension_type' => 'access_key',
                'dimension_id' => $accessKey->getId(),
                'calculation_method' => 'real_time',
                'filters' => $this->filterToArray($filter),
            ]
        );
    }

    /**
     * 实时计算User统计数据
     */
    private function calculateUserStats(UserInterface $user, UsageQueryFilter $filter): UsageStatisticsResult
    {
        $usageData = $this->userUsageRepository->calculateStatistics(
            $user,
            $filter->startDate,
            $filter->endDate,
            $filter->models,
            $filter->features
        );

        assert(is_int($usageData['total_input_tokens']));
        assert(is_int($usageData['total_cache_creation_input_tokens']));
        assert(is_int($usageData['total_cache_read_input_tokens']));
        assert(is_int($usageData['total_output_tokens']));
        assert(is_int($usageData['total_requests']));

        return new UsageStatisticsResult(
            totalInputTokens: $usageData['total_input_tokens'],
            totalCacheCreationInputTokens: $usageData['total_cache_creation_input_tokens'],
            totalCacheReadInputTokens: $usageData['total_cache_read_input_tokens'],
            totalOutputTokens: $usageData['total_output_tokens'],
            totalRequests: $usageData['total_requests'],
            periods: [], // 实时计算没有周期数据
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            metadata: [
                'dimension_type' => 'user',
                'dimension_id' => $user->getUserIdentifier(),
                'calculation_method' => 'real_time',
                'filters' => $this->filterToArray($filter),
            ]
        );
    }

    /**
     * 构建统计结果对象
     *
     * @param array<array<string, mixed>> $statistics
     */
    private function buildStatisticsResult(array $statistics, UsageQueryFilter $filter): UsageStatisticsResult
    {
        $totals = [
            'total_input_tokens' => 0,
            'total_cache_creation_input_tokens' => 0,
            'total_cache_read_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_requests' => 0,
        ];

        foreach ($statistics as $stat) {
            assert(is_int($stat['total_input_tokens']));
            assert(is_int($stat['total_cache_creation_input_tokens']));
            assert(is_int($stat['total_cache_read_input_tokens']));
            assert(is_int($stat['total_output_tokens']));
            assert(is_int($stat['total_requests']));

            $totals['total_input_tokens'] += $stat['total_input_tokens'];
            $totals['total_cache_creation_input_tokens'] += $stat['total_cache_creation_input_tokens'];
            $totals['total_cache_read_input_tokens'] += $stat['total_cache_read_input_tokens'];
            $totals['total_output_tokens'] += $stat['total_output_tokens'];
            $totals['total_requests'] += $stat['total_requests'];
        }

        return new UsageStatisticsResult(
            totalInputTokens: $totals['total_input_tokens'],
            totalCacheCreationInputTokens: $totals['total_cache_creation_input_tokens'],
            totalCacheReadInputTokens: $totals['total_cache_read_input_tokens'],
            totalOutputTokens: $totals['total_output_tokens'],
            totalRequests: $totals['total_requests'],
            periods: [], // 简化实现，不返回周期详情
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            metadata: [
                'calculation_method' => 'pre_aggregated',
                'data_points' => count($statistics),
                'filters' => $this->filterToArray($filter),
            ]
        );
    }

    /**
     * 计算趋势数据摘要
     *
     * @param array<UsageTrendDataPoint> $dataPoints
     * @return array<string, mixed>
     */
    private function calculateTrendSummary(array $dataPoints): array
    {
        if ([] === $dataPoints) {
            return [];
        }

        $totalTokens = array_sum(array_map(fn (UsageTrendDataPoint $point): int => $point->getTotalTokens(), $dataPoints));
        $totalRequests = array_sum(array_map(fn (UsageTrendDataPoint $point): int => $point->totalRequests, $dataPoints));

        $peakPoint = null;
        foreach ($dataPoints as $point) {
            if (null === $peakPoint || $point->getTotalTokens() > $peakPoint->getTotalTokens()) {
                $peakPoint = $point;
            }
        }

        // dataPoints 非空时 peakPoint 必定非空（已通过前面的空检查）
        // 移除冗余的 assert，因为逻辑保证 $peakPoint 不为 null

        return [
            'total_tokens' => $totalTokens,
            'total_requests' => $totalRequests,
            'average_tokens_per_period' => $totalTokens / count($dataPoints), // 已确保非空，移除冗余检查
            'peak_usage' => $peakPoint->toArray(),
            'data_point_count' => count($dataPoints),
        ];
    }

    /**
     * 将过滤器转换为数组格式（用于日志记录）
     *
     * @return array<string, mixed>
     */
    private function filterToArray(UsageQueryFilter $filter): array
    {
        return [
            'start_date' => $filter->startDate?->format('Y-m-d H:i:s'),
            'end_date' => $filter->endDate?->format('Y-m-d H:i:s'),
            'models' => $filter->models,
            'features' => $filter->features,
            'aggregation_period' => $filter->aggregationPeriod,
        ];
    }
}
