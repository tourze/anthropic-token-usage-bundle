<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * UsageStatistics实体的Repository
 *
 * @extends ServiceEntityRepository<UsageStatistics>
 */
#[AsRepository(entityClass: UsageStatistics::class)]
class UsageStatisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UsageStatistics::class);
    }

    /**
     * 保存UsageStatistics实体
     */
    public function save(UsageStatistics $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除UsageStatistics实体
     */
    public function remove(UsageStatistics $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找或创建统计记录
     */
    public function findOrCreate(
        string $dimensionType,
        string $dimensionId,
        string $periodType,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): UsageStatistics {
        $entity = $this->findOneBy([
            'dimensionType' => $dimensionType,
            'dimensionId' => $dimensionId,
            'periodType' => $periodType,
            'periodStart' => $periodStart,
        ]);

        if (null === $entity) {
            $entity = new UsageStatistics();
            $entity->setDimensionType($dimensionType);
            $entity->setDimensionId($dimensionId);
            $entity->setPeriodType($periodType);
            $entity->setPeriodStart($periodStart);
            $entity->setPeriodEnd($periodEnd);

            $this->save($entity);
        }

        return $entity;
    }

    /**
     * 根据维度查找统计数据
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     * @return array<array<string, mixed>>
     */
    public function findByDimension(
        string $dimensionType,
        string $dimensionId,
        string $periodType,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?array $models = null,
        ?array $features = null,
    ): array {
        $qb = $this->createBaseQueryBuilder($dimensionType, $dimensionId, $periodType);
        $this->applyDateFilters($qb, $startDate, $endDate);
        $this->applyModelAndFeatureFilters($qb, $models, $features);

        $qb->orderBy('us.periodStart', 'ASC');
        $results = $qb->getQuery()->getArrayResult();
        /** @var array<array<string, mixed>> $results */

        return $this->transformResults($results);
    }

    /**
     * 创建基础查询构建器
     */
    private function createBaseQueryBuilder(string $dimensionType, string $dimensionId, string $periodType): QueryBuilder
    {
        return $this->createQueryBuilder('us')
            ->where('us.dimensionType = :dimensionType')
            ->andWhere('us.dimensionId = :dimensionId')
            ->andWhere('us.periodType = :periodType')
            ->setParameter('dimensionType', $dimensionType)
            ->setParameter('dimensionId', $dimensionId)
            ->setParameter('periodType', $periodType)
        ;
    }

    /**
     * 应用日期过滤器
     */
    private function applyDateFilters(QueryBuilder $qb, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        if (null !== $startDate) {
            $qb->andWhere('us.periodStart >= :startDate')
                ->setParameter('startDate', $startDate)
            ;
        }

        if (null !== $endDate) {
            $qb->andWhere('us.periodEnd <= :endDate')
                ->setParameter('endDate', $endDate)
            ;
        }
    }

    /**
     * 应用模型和功能过滤器
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     */
    private function applyModelAndFeatureFilters(QueryBuilder $qb, ?array $models, ?array $features): void
    {
        if (null !== $models && [] !== $models) {
            $qb->andWhere('us.model IN (:models)')
                ->setParameter('models', $models)
            ;
        }

        if (null !== $features && [] !== $features) {
            $qb->andWhere('us.feature IN (:features)')
                ->setParameter('features', $features)
            ;
        }
    }

    /**
     * 转换查询结果
     *
     * @param array<array<string, mixed>> $results
     * @return array<array<string, mixed>>
     */
    private function transformResults(array $results): array
    {
        return array_map(function (array $result): array {
            return [
                'total_input_tokens' => is_numeric($result['totalInputTokens'] ?? 0) ? (int) ($result['totalInputTokens'] ?? 0) : 0,
                'total_cache_creation_input_tokens' => is_numeric($result['totalCacheCreationInputTokens'] ?? 0) ? (int) ($result['totalCacheCreationInputTokens'] ?? 0) : 0,
                'total_cache_read_input_tokens' => is_numeric($result['totalCacheReadInputTokens'] ?? 0) ? (int) ($result['totalCacheReadInputTokens'] ?? 0) : 0,
                'total_output_tokens' => is_numeric($result['totalOutputTokens'] ?? 0) ? (int) ($result['totalOutputTokens'] ?? 0) : 0,
                'total_requests' => is_numeric($result['totalRequests'] ?? 0) ? (int) ($result['totalRequests'] ?? 0) : 0,
                'period_start' => isset($result['periodStart']) && $result['periodStart'] instanceof \DateTimeInterface
                    ? $result['periodStart']->format('Y-m-d H:i:s') : '',
                'period_end' => isset($result['periodEnd']) && $result['periodEnd'] instanceof \DateTimeInterface
                    ? $result['periodEnd']->format('Y-m-d H:i:s') : '',
                'model' => $result['model'] ?? '',
                'feature' => $result['feature'] ?? '',
            ];
        }, $results);
    }

    /**
     * 归一化趋势数据行
     *
     * @param array<string, mixed> $row
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     total_input_tokens: int,
     *     total_cache_creation_input_tokens: int,
     *     total_cache_read_input_tokens: int,
     *     total_output_tokens: int,
     *     total_requests: int
     * }
     */
    private function normalizeTrendRow(array $row): array
    {
        // period_start 和 period_end 是 DateTime 对象
        $periodStart = $row['period_start'] ?? null;
        $periodEnd = $row['period_end'] ?? null;

        if (!$periodStart instanceof \DateTimeInterface || !$periodEnd instanceof \DateTimeInterface) {
            throw new \UnexpectedValueException('Period start and end must be DateTimeInterface instances');
        }

        return [
            'period_start' => $periodStart->format('Y-m-d H:i:s'),
            'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            'total_input_tokens' => $this->normalizeIntValue($row['total_input_tokens'] ?? 0),
            'total_cache_creation_input_tokens' => $this->normalizeIntValue($row['total_cache_creation_input_tokens'] ?? 0),
            'total_cache_read_input_tokens' => $this->normalizeIntValue($row['total_cache_read_input_tokens'] ?? 0),
            'total_output_tokens' => $this->normalizeIntValue($row['total_output_tokens'] ?? 0),
            'total_requests' => $this->normalizeIntValue($row['total_requests'] ?? 0),
        ];
    }

    /**
     * 归一化整数值
     *
     * 处理数据库返回的 mixed 类型(int|string|null)，确保返回 int
     */
    private function normalizeIntValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (null === $value) {
            return 0;
        }

        throw new \UnexpectedValueException(sprintf('Expected int, numeric string, or null, got %s', get_debug_type($value)));
    }

    /**
     * 获取趋势数据
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     * @return array<array<string, mixed>>
     */
    public function findTrendData(
        string $dimensionType,
        string $dimensionId,
        string $periodType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?array $models = null,
        ?array $features = null,
        int $limit = 100,
    ): array {
        $qb = $this->createQueryBuilder('us')
            ->select([
                'us.periodStart as period_start',
                'us.periodEnd as period_end',
                'SUM(us.totalInputTokens) as total_input_tokens',
                'SUM(us.totalCacheCreationInputTokens) as total_cache_creation_input_tokens',
                'SUM(us.totalCacheReadInputTokens) as total_cache_read_input_tokens',
                'SUM(us.totalOutputTokens) as total_output_tokens',
                'SUM(us.totalRequests) as total_requests',
            ])
            ->where('us.dimensionType = :dimensionType')
            ->andWhere('us.periodType = :periodType')
            ->andWhere('us.periodStart >= :startDate')
            ->andWhere('us.periodEnd <= :endDate')
            ->setParameter('dimensionType', $dimensionType)
            ->setParameter('periodType', $periodType)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        if ('all' !== $dimensionId) {
            $qb->andWhere('us.dimensionId = :dimensionId')
                ->setParameter('dimensionId', $dimensionId)
            ;
        }

        if (null !== $models && [] !== $models) {
            $qb->andWhere('us.model IN (:models)')
                ->setParameter('models', $models)
            ;
        }

        if (null !== $features && [] !== $features) {
            $qb->andWhere('us.feature IN (:features)')
                ->setParameter('features', $features)
            ;
        }

        $qb->groupBy('us.periodStart', 'us.periodEnd')
            ->orderBy('us.periodStart', 'ASC')
            ->setMaxResults($limit)
        ;

        /** @var array<array<string, mixed>> */
        $results = $qb->getQuery()->getResult();

        return array_map(fn (array $row): array => $this->normalizeTrendRow($row), $results);
    }

    /**
     * 获取系统总计数据
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     * @return array<string, int>
     */
    public function getSystemTotals(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $periodType = 'day',
        ?array $models = null,
        ?array $features = null,
    ): array {
        $qb = $this->createQueryBuilder('us')
            ->select([
                'COALESCE(SUM(us.totalInputTokens), 0) as total_input_tokens',
                'COALESCE(SUM(us.totalCacheCreationInputTokens), 0) as total_cache_creation_input_tokens',
                'COALESCE(SUM(us.totalCacheReadInputTokens), 0) as total_cache_read_input_tokens',
                'COALESCE(SUM(us.totalOutputTokens), 0) as total_output_tokens',
                'COALESCE(SUM(us.totalRequests), 0) as total_requests',
            ])
            ->where('us.periodType = :periodType')
            ->andWhere('us.periodStart >= :startDate')
            ->andWhere('us.periodEnd <= :endDate')
            ->setParameter('periodType', $periodType)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        if (null !== $models && [] !== $models) {
            $qb->andWhere('us.model IN (:models)')
                ->setParameter('models', $models)
            ;
        }

        if (null !== $features && [] !== $features) {
            $qb->andWhere('us.feature IN (:features)')
                ->setParameter('features', $features)
            ;
        }

        $result = $qb->getQuery()->getSingleResult();
        assert(is_array($result));

        return [
            'total_input_tokens' => is_numeric($result['total_input_tokens'] ?? 0) ? (int) ($result['total_input_tokens'] ?? 0) : 0,
            'total_cache_creation_input_tokens' => is_numeric($result['total_cache_creation_input_tokens'] ?? 0) ? (int) ($result['total_cache_creation_input_tokens'] ?? 0) : 0,
            'total_cache_read_input_tokens' => is_numeric($result['total_cache_read_input_tokens'] ?? 0) ? (int) ($result['total_cache_read_input_tokens'] ?? 0) : 0,
            'total_output_tokens' => is_numeric($result['total_output_tokens'] ?? 0) ? (int) ($result['total_output_tokens'] ?? 0) : 0,
            'total_requests' => is_numeric($result['total_requests'] ?? 0) ? (int) ($result['total_requests'] ?? 0) : 0,
        ];
    }
}
