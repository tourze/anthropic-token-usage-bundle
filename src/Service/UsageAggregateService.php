<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageAggregateServiceInterface;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UsageStatisticsRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AggregationResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\RebuildResult;

/**
 * Usage聚合服务实现
 *
 * 负责聚合 AccessKeyUsage 和 UserUsage 数据到 UsageStatistics 表
 */
#[WithMonologChannel(channel: 'anthropic_token_usage')]
final readonly class UsageAggregateService implements UsageAggregateServiceInterface
{
    public function __construct(
        private AccessKeyUsageRepository $accessKeyUsageRepository,
        private UserUsageRepository $userUsageRepository,
        private UsageStatisticsRepository $usageStatisticsRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function performIncrementalAggregation(
        \DateTimeInterface $fromTime,
        \DateTimeInterface $toTime,
    ): AggregationResult {
        $processedRecords = 0;
        $updatedStatistics = 0;
        $errors = [];

        $this->logger->info('Starting incremental aggregation', [
            'from_time' => $fromTime->format('Y-m-d H:i:s'),
            'to_time' => $toTime->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->entityManager->beginTransaction();

            // 聚合 AccessKey 维度数据
            $accessKeyResult = $this->aggregateAccessKeyUsage($fromTime, $toTime);
            $processedRecords += $accessKeyResult['processed'];
            $updatedStatistics += $accessKeyResult['updated'];
            $errors = array_merge($errors, $accessKeyResult['errors']);

            // 聚合 User 维度数据
            $userResult = $this->aggregateUserUsage($fromTime, $toTime);
            $processedRecords += $userResult['processed'];
            $updatedStatistics += $userResult['updated'];
            $errors = array_merge($errors, $userResult['errors']);

            $this->entityManager->commit();

            $success = [] === $errors;

            $this->logger->info('Incremental aggregation completed', [
                'success' => $success,
                'processed_records' => $processedRecords,
                'updated_statistics' => $updatedStatistics,
                'error_count' => count($errors),
            ]);

            return new AggregationResult($success, $processedRecords, $updatedStatistics, $errors);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $error = 'Aggregation transaction failed: ' . $e->getMessage();
            $errors[] = $error;

            $this->logger->error($error, [
                'exception' => $e,
                'from_time' => $fromTime->format('Y-m-d H:i:s'),
                'to_time' => $toTime->format('Y-m-d H:i:s'),
            ]);

            return new AggregationResult(false, $processedRecords, $updatedStatistics, $errors);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function rebuildAggregateData(
        string $dimensionType,
        string $dimensionId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): RebuildResult {
        $rebuiltRecords = 0;
        $deletedRecords = 0;
        $errors = [];

        if (!in_array($dimensionType, [UsageStatistics::DIMENSION_ACCESS_KEY, UsageStatistics::DIMENSION_USER], true)) {
            $error = "Invalid dimension type: {$dimensionType}";
            $errors[] = $error;

            $this->logger->error($error, [
                'dimension_type' => $dimensionType,
                'dimension_id' => $dimensionId,
            ]);

            return new RebuildResult(false, 0, 0, $dimensionType, $dimensionId, $errors);
        }

        $this->logger->info('Starting aggregate data rebuild', [
            'dimension_type' => $dimensionType,
            'dimension_id' => $dimensionId,
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->entityManager->beginTransaction();

            // 删除现有的聚合数据
            $deletedRecords = $this->deleteExistingStatistics($dimensionType, $dimensionId, $startDate, $endDate);

            // 重新构建聚合数据
            if (UsageStatistics::DIMENSION_ACCESS_KEY === $dimensionType) {
                $rebuiltRecords = $this->rebuildAccessKeyStatistics($dimensionId, $startDate, $endDate);
            } else {
                $rebuiltRecords = $this->rebuildUserStatistics($dimensionId, $startDate, $endDate);
            }

            $this->entityManager->commit();

            $this->logger->info('Aggregate data rebuild completed', [
                'dimension_type' => $dimensionType,
                'dimension_id' => $dimensionId,
                'rebuilt_records' => $rebuiltRecords,
                'deleted_records' => $deletedRecords,
            ]);

            return new RebuildResult(true, $rebuiltRecords, $deletedRecords, $dimensionType, $dimensionId, $errors);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $error = 'Rebuild transaction failed: ' . $e->getMessage();
            $errors[] = $error;

            $this->logger->error($error, [
                'exception' => $e,
                'dimension_type' => $dimensionType,
                'dimension_id' => $dimensionId,
            ]);

            return new RebuildResult(false, $rebuiltRecords, $deletedRecords, $dimensionType, $dimensionId, $errors);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function cleanupExpiredData(\DateTimeInterface $before): int
    {
        $this->logger->info('Starting expired data cleanup', [
            'before' => $before->format('Y-m-d H:i:s'),
        ]);

        try {
            $queryBuilder = $this->usageStatisticsRepository->createQueryBuilder('us');
            $queryBuilder->delete()
                ->where('us.periodEnd < :before')
                ->setParameter('before', $before)
            ;

            $deletedCount = $queryBuilder->getQuery()->execute();
            assert(is_int($deletedCount));

            $this->logger->info('Expired data cleanup completed', [
                'deleted_count' => $deletedCount,
                'before' => $before->format('Y-m-d H:i:s'),
            ]);

            return $deletedCount;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cleanup expired data', [
                'exception' => $e,
                'before' => $before->format('Y-m-d H:i:s'),
            ]);

            return 0;
        }
    }

    /**
     * 聚合 AccessKey 维度的使用数据
     *
     * @return array{processed: int, updated: int, errors: array<string>}
     */
    private function aggregateAccessKeyUsage(\DateTimeInterface $fromTime, \DateTimeInterface $toTime): array
    {
        $processed = 0;
        $updated = 0;
        $errors = [];

        try {
            // 使用 Repository 方法获取类型安全的聚合数据
            $aggregations = $this->accessKeyUsageRepository->getAggregatedDataByAccessKey($fromTime, $toTime);
            $processed = count($aggregations);

            // 为每个聚合结果创建或更新统计记录
            foreach ($aggregations as $aggregation) {
                // 验证 dimensionId 非空
                if ('' === $aggregation->dimensionId) {
                    $errors[] = 'Empty accessKeyId encountered in aggregation';
                    $this->logger->warning('Empty accessKeyId in aggregation', [
                        'from_time' => $fromTime->format('Y-m-d H:i:s'),
                        'to_time' => $toTime->format('Y-m-d H:i:s'),
                    ]);
                    continue;
                }

                // 为每个时间周期类型创建或更新统计记录
                foreach ([UsageStatistics::PERIOD_HOUR, UsageStatistics::PERIOD_DAY, UsageStatistics::PERIOD_MONTH] as $periodType) {
                    $periodTimes = $this->calculatePeriodTimes($fromTime, $toTime, $periodType);

                    foreach ($periodTimes as $periodTime) {
                        $statistic = $this->usageStatisticsRepository->findOrCreate(
                            UsageStatistics::DIMENSION_ACCESS_KEY,
                            $aggregation->dimensionId,
                            $periodType,
                            $periodTime['start'],
                            $periodTime['end']
                        );

                        $statistic->addUsageData(
                            $aggregation->totalInputTokens,
                            $aggregation->totalCacheCreationInputTokens,
                            $aggregation->totalCacheReadInputTokens,
                            $aggregation->totalOutputTokens,
                            $aggregation->totalRequests
                        );

                        $this->usageStatisticsRepository->save($statistic);
                        ++$updated;
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'AccessKey aggregation failed: ' . $e->getMessage();
            $this->logger->error('AccessKey aggregation failed', [
                'exception' => $e,
                'from_time' => $fromTime->format('Y-m-d H:i:s'),
                'to_time' => $toTime->format('Y-m-d H:i:s'),
            ]);
        }

        return ['processed' => $processed, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * 聚合 User 维度的使用数据
     *
     * @return array{processed: int, updated: int, errors: array<string>}
     */
    private function aggregateUserUsage(\DateTimeInterface $fromTime, \DateTimeInterface $toTime): array
    {
        $processed = 0;
        $updated = 0;
        $errors = [];

        try {
            // 使用 Repository 方法获取类型安全的聚合数据
            $aggregations = $this->userUsageRepository->getAggregatedDataByUser($fromTime, $toTime);
            $processed = count($aggregations);

            // 为每个聚合结果创建或更新统计记录
            foreach ($aggregations as $aggregation) {
                // 验证 dimensionId 非空
                if ('' === $aggregation->dimensionId) {
                    $errors[] = 'Empty userId encountered in aggregation';
                    $this->logger->warning('Empty userId in aggregation', [
                        'from_time' => $fromTime->format('Y-m-d H:i:s'),
                        'to_time' => $toTime->format('Y-m-d H:i:s'),
                    ]);
                    continue;
                }

                // 为每个时间周期类型创建或更新统计记录
                foreach ([UsageStatistics::PERIOD_HOUR, UsageStatistics::PERIOD_DAY, UsageStatistics::PERIOD_MONTH] as $periodType) {
                    $periodTimes = $this->calculatePeriodTimes($fromTime, $toTime, $periodType);

                    foreach ($periodTimes as $periodTime) {
                        $statistic = $this->usageStatisticsRepository->findOrCreate(
                            UsageStatistics::DIMENSION_USER,
                            $aggregation->dimensionId,
                            $periodType,
                            $periodTime['start'],
                            $periodTime['end']
                        );

                        $statistic->addUsageData(
                            $aggregation->totalInputTokens,
                            $aggregation->totalCacheCreationInputTokens,
                            $aggregation->totalCacheReadInputTokens,
                            $aggregation->totalOutputTokens,
                            $aggregation->totalRequests
                        );

                        $this->usageStatisticsRepository->save($statistic);
                        ++$updated;
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'User aggregation failed: ' . $e->getMessage();
            $this->logger->error('User aggregation failed', [
                'exception' => $e,
                'from_time' => $fromTime->format('Y-m-d H:i:s'),
                'to_time' => $toTime->format('Y-m-d H:i:s'),
            ]);
        }

        return ['processed' => $processed, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * 删除现有的统计数据
     */
    private function deleteExistingStatistics(
        string $dimensionType,
        string $dimensionId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): int {
        $queryBuilder = $this->usageStatisticsRepository->createQueryBuilder('us');
        $queryBuilder->delete()
            ->where('us.dimensionType = :dimensionType')
            ->andWhere('us.dimensionId = :dimensionId')
            ->andWhere('us.periodStart >= :startDate')
            ->andWhere('us.periodEnd <= :endDate')
            ->setParameter('dimensionType', $dimensionType)
            ->setParameter('dimensionId', $dimensionId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        $deletedCount = $queryBuilder->getQuery()->execute();
        assert(is_int($deletedCount));

        return $deletedCount;
    }

    /**
     * 重建 AccessKey 统计数据
     */
    private function rebuildAccessKeyStatistics(
        string $accessKeyId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): int {
        $rebuilt = 0;

        $queryBuilder = $this->accessKeyUsageRepository->createQueryBuilder('aku')
            ->select('aku.occurTime')
            ->addSelect('SUM(aku.inputTokens) as totalInputTokens')
            ->addSelect('SUM(aku.cacheCreationInputTokens) as totalCacheCreationInputTokens')
            ->addSelect('SUM(aku.cacheReadInputTokens) as totalCacheReadInputTokens')
            ->addSelect('SUM(aku.outputTokens) as totalOutputTokens')
            ->addSelect('COUNT(aku.id) as totalRequests')
            ->where('IDENTITY(aku.accessKey) = :accessKeyId')
            ->andWhere('aku.occurTime >= :startDate')
            ->andWhere('aku.occurTime <= :endDate')
            ->groupBy('aku.occurTime')
            ->setParameter('accessKeyId', $accessKeyId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        $results = $queryBuilder->getQuery()->getResult();
        assert(is_array($results));

        foreach ($results as $result) {
            assert(is_array($result));
            $occurredAt = $result['occurTime'];
            assert($occurredAt instanceof \DateTimeInterface);

            // 显式类型校验和转换
            $totalInputTokens = $result['totalInputTokens'] ?? 0;
            $totalCacheCreationInputTokens = $result['totalCacheCreationInputTokens'] ?? 0;
            $totalCacheReadInputTokens = $result['totalCacheReadInputTokens'] ?? 0;
            $totalOutputTokens = $result['totalOutputTokens'] ?? 0;
            $totalRequests = $result['totalRequests'] ?? 0;

            assert(is_numeric($totalInputTokens));
            assert(is_numeric($totalCacheCreationInputTokens));
            assert(is_numeric($totalCacheReadInputTokens));
            assert(is_numeric($totalOutputTokens));
            assert(is_numeric($totalRequests));

            foreach ([UsageStatistics::PERIOD_HOUR, UsageStatistics::PERIOD_DAY, UsageStatistics::PERIOD_MONTH] as $periodType) {
                $periodTimes = $this->calculatePeriodTimesForDate($occurredAt, $periodType);

                $statistic = $this->usageStatisticsRepository->findOrCreate(
                    UsageStatistics::DIMENSION_ACCESS_KEY,
                    $accessKeyId,
                    $periodType,
                    $periodTimes['start'],
                    $periodTimes['end']
                );

                $statistic->addUsageData(
                    (int) $totalInputTokens,
                    (int) $totalCacheCreationInputTokens,
                    (int) $totalCacheReadInputTokens,
                    (int) $totalOutputTokens,
                    (int) $totalRequests
                );

                $this->usageStatisticsRepository->save($statistic);
                ++$rebuilt;
            }
        }

        return $rebuilt;
    }

    /**
     * 重建 User 统计数据
     */
    private function rebuildUserStatistics(
        string $userId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): int {
        $rebuilt = 0;

        $queryBuilder = $this->userUsageRepository->createQueryBuilder('uu')
            ->select('uu.occurTime')
            ->addSelect('SUM(uu.inputTokens) as totalInputTokens')
            ->addSelect('SUM(uu.cacheCreationInputTokens) as totalCacheCreationInputTokens')
            ->addSelect('SUM(uu.cacheReadInputTokens) as totalCacheReadInputTokens')
            ->addSelect('SUM(uu.outputTokens) as totalOutputTokens')
            ->addSelect('COUNT(uu.id) as totalRequests')
            ->where('IDENTITY(uu.user) = :userId')
            ->andWhere('uu.occurTime >= :startDate')
            ->andWhere('uu.occurTime <= :endDate')
            ->groupBy('uu.occurTime')
            ->setParameter('userId', $userId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        $results = $queryBuilder->getQuery()->getResult();
        assert(is_array($results));

        foreach ($results as $result) {
            assert(is_array($result));
            $occurredAt = $result['occurTime'];
            assert($occurredAt instanceof \DateTimeInterface);

            // 显式类型校验和转换
            $totalInputTokens = $result['totalInputTokens'] ?? 0;
            $totalCacheCreationInputTokens = $result['totalCacheCreationInputTokens'] ?? 0;
            $totalCacheReadInputTokens = $result['totalCacheReadInputTokens'] ?? 0;
            $totalOutputTokens = $result['totalOutputTokens'] ?? 0;
            $totalRequests = $result['totalRequests'] ?? 0;

            assert(is_numeric($totalInputTokens));
            assert(is_numeric($totalCacheCreationInputTokens));
            assert(is_numeric($totalCacheReadInputTokens));
            assert(is_numeric($totalOutputTokens));
            assert(is_numeric($totalRequests));

            foreach ([UsageStatistics::PERIOD_HOUR, UsageStatistics::PERIOD_DAY, UsageStatistics::PERIOD_MONTH] as $periodType) {
                $periodTimes = $this->calculatePeriodTimesForDate($occurredAt, $periodType);

                $statistic = $this->usageStatisticsRepository->findOrCreate(
                    UsageStatistics::DIMENSION_USER,
                    $userId,
                    $periodType,
                    $periodTimes['start'],
                    $periodTimes['end']
                );

                $statistic->addUsageData(
                    (int) $totalInputTokens,
                    (int) $totalCacheCreationInputTokens,
                    (int) $totalCacheReadInputTokens,
                    (int) $totalOutputTokens,
                    (int) $totalRequests
                );

                $this->usageStatisticsRepository->save($statistic);
                ++$rebuilt;
            }
        }

        return $rebuilt;
    }

    /**
     * 计算时间周期范围
     *
     * @return array<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function calculatePeriodTimes(
        \DateTimeInterface $fromTime,
        \DateTimeInterface $toTime,
        string $periodType,
    ): array {
        $periods = [];
        $current = \DateTimeImmutable::createFromInterface($fromTime);
        $end = \DateTimeImmutable::createFromInterface($toTime);

        while ($current < $end) {
            $periodTimes = $this->calculatePeriodTimesForDate($current, $periodType);
            $periods[] = $periodTimes;

            // 移动到下一个周期
            $current = match ($periodType) {
                UsageStatistics::PERIOD_HOUR => $current->add(new \DateInterval('PT1H')),
                UsageStatistics::PERIOD_DAY => $current->add(new \DateInterval('P1D')),
                UsageStatistics::PERIOD_MONTH => $current->add(new \DateInterval('P1M')),
                default => throw new \InvalidArgumentException("Invalid period type: {$periodType}"),
            };
        }

        return $periods;
    }

    /**
     * 计算特定日期的时间周期
     *
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}
     */
    private function calculatePeriodTimesForDate(\DateTimeInterface $date, string $periodType): array
    {
        $dateTime = \DateTimeImmutable::createFromInterface($date);

        return match ($periodType) {
            UsageStatistics::PERIOD_HOUR => [
                'start' => $dateTime->setTime((int) $dateTime->format('H'), 0, 0),
                'end' => $dateTime->setTime((int) $dateTime->format('H'), 59, 59),
            ],
            UsageStatistics::PERIOD_DAY => [
                'start' => $dateTime->setTime(0, 0, 0),
                'end' => $dateTime->setTime(23, 59, 59),
            ],
            UsageStatistics::PERIOD_MONTH => [
                'start' => $dateTime->modify('first day of this month')->setTime(0, 0, 0),
                'end' => $dateTime->modify('last day of this month')->setTime(23, 59, 59),
            ],
            default => throw new \InvalidArgumentException("Invalid period type: {$periodType}"),
        };
    }
}
