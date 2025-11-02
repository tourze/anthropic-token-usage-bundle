<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageAggregationData;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * AccessKeyUsage实体的Repository
 *
 * @extends ServiceEntityRepository<AccessKeyUsage>
 */
#[AsRepository(entityClass: AccessKeyUsage::class)]
class AccessKeyUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessKeyUsage::class);
    }

    /**
     * 保存AccessKeyUsage实体
     */
    public function save(AccessKeyUsage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除AccessKeyUsage实体
     */
    public function remove(AccessKeyUsage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据查询条件查找usage记录
     *
     * @return array<AccessKeyUsage>
     */
    public function findByQuery(UsageDetailQuery $query): array
    {
        $qb = $this->createQueryBuilder('aku');
        $this->applyQueryFilters($qb, $query);

        $qb->orderBy('aku.createTime', 'DESC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->limit)
        ;

        /** @var array<AccessKeyUsage> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 计算查询条件匹配的记录数量
     */
    public function countByQuery(UsageDetailQuery $query): int
    {
        $qb = $this->createQueryBuilder('aku')
            ->select('COUNT(aku.id)')
        ;
        $this->applyQueryFilters($qb, $query);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 计算AccessKey的统计数据
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     * @return array<string, mixed>
     */
    public function calculateStatistics(
        AccessKey $accessKey,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?array $models = null,
        ?array $features = null,
    ): array {
        $qb = $this->createStatisticsQueryBuilder()
            ->where('aku.accessKey = :accessKey')
            ->setParameter('accessKey', $accessKey)
        ;

        $this->applyDateAndFilterParams($qb, $startDate, $endDate, $models, $features);

        $result = $qb->getQuery()->getSingleResult();
        assert(is_array($result));
        /** @var array<string, mixed> $result */

        return $this->formatStatisticsResult($result);
    }

    /**
     * 获取Top消费者
     *
     * @return array<array{accessKey: AccessKey, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: ?\DateTimeInterface, lastUsageTime: ?\DateTimeInterface}>
     */
    public function findTopConsumers(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10,
    ): array {
        $qb = $this->createQueryBuilder('aku')
            ->select([
                'aku',
                'ak as accessKey',
                'COALESCE(SUM(aku.inputTokens), 0) as totalInputTokens',
                'COALESCE(SUM(aku.cacheCreationInputTokens), 0) as totalCacheCreationInputTokens',
                'COALESCE(SUM(aku.cacheReadInputTokens), 0) as totalCacheReadInputTokens',
                'COALESCE(SUM(aku.outputTokens), 0) as totalOutputTokens',
                'COUNT(aku.id) as totalRequests',
                'MIN(aku.createTime) as firstUsageTime',
                'MAX(aku.createTime) as lastUsageTime',
                'SUM(aku.inputTokens + aku.cacheCreationInputTokens + aku.cacheReadInputTokens + aku.outputTokens) as totalTokens',
            ])
            ->join('aku.accessKey', 'ak')
            ->where('aku.createTime >= :startDate')
            ->andWhere('aku.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('ak.id')
            ->orderBy('totalTokens', 'DESC')
            ->setMaxResults($limit)
        ;

        $results = $qb->getQuery()->getResult();
        assert(is_array($results));

        /** @var list<array{accessKey: AccessKey, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: \DateTimeImmutable|null, lastUsageTime: \DateTimeImmutable|null}> */
        return array_map(fn (mixed $result): array => $this->formatTopConsumerResult($result), $results);
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
        ?array $models = null,
        ?array $features = null,
        bool $includeInactive = false,
    ): array {
        $qb = $this->createStatisticsQueryBuilder()
            ->where('aku.createTime >= :startDate')
            ->andWhere('aku.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        if (!$includeInactive) {
            $qb->join('aku.accessKey', 'ak')
                ->andWhere('ak.valid = true')
            ;
        }

        $this->applyModelAndFeatureFilters($qb, $models, $features);

        $result = $qb->getQuery()->getSingleResult();
        assert(is_array($result));
        /** @var array<string, mixed> $result */

        return $this->formatStatisticsResult($result);
    }

    /**
     * 计算活跃实体数量
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     */
    public function countActiveEntities(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?array $models = null,
        ?array $features = null,
        bool $includeInactive = false,
    ): int {
        $qb = $this->createQueryBuilder('aku')
            ->select('COUNT(DISTINCT aku.accessKey)')
            ->where('aku.createTime >= :startDate')
            ->andWhere('aku.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        if (!$includeInactive) {
            $qb->join('aku.accessKey', 'ak')
                ->andWhere('ak.valid = true')
            ;
        }

        $this->applyModelAndFeatureFilters($qb, $models, $features);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 获取最后更新时间
     */
    public function getLastUpdateTime(): ?\DateTimeInterface
    {
        $qb = $this->createQueryBuilder('aku')
            ->select('MAX(aku.createTime)')
            ->setMaxResults(1)
        ;

        $result = $qb->getQuery()->getSingleScalarResult();

        return (null !== $result && '' !== $result) ? new \DateTimeImmutable((string) $result) : null;
    }

    /**
     * 应用UsageDetailQuery的过滤条件
     */
    private function applyQueryFilters(QueryBuilder $qb, UsageDetailQuery $query): void
    {
        if (null !== $query->dimensionId) {
            $qb->andWhere('aku.accessKey = :dimensionId')
                ->setParameter('dimensionId', $query->dimensionId)
            ;
        }

        if (null !== $query->startDate) {
            $qb->andWhere('aku.createTime >= :startDate')
                ->setParameter('startDate', $query->startDate)
            ;
        }

        if (null !== $query->endDate) {
            $qb->andWhere('aku.createTime <= :endDate')
                ->setParameter('endDate', $query->endDate)
            ;
        }

        $this->applyModelAndFeatureFilters($qb, $query->models, $query->features);
    }

    /**
     * 创建统计查询构建器
     */
    private function createStatisticsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('aku')
            ->select([
                'COALESCE(SUM(aku.inputTokens), 0) as total_input_tokens',
                'COALESCE(SUM(aku.cacheCreationInputTokens), 0) as total_cache_creation_input_tokens',
                'COALESCE(SUM(aku.cacheReadInputTokens), 0) as total_cache_read_input_tokens',
                'COALESCE(SUM(aku.outputTokens), 0) as total_output_tokens',
                'COUNT(aku.id) as total_requests',
            ])
        ;
    }

    /**
     * 应用日期和过滤参数
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     */
    private function applyDateAndFilterParams(
        QueryBuilder $qb,
        ?\DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate,
        ?array $models,
        ?array $features,
    ): void {
        if (null !== $startDate) {
            $qb->andWhere('aku.createTime >= :startDate')
                ->setParameter('startDate', $startDate)
            ;
        }

        if (null !== $endDate) {
            $qb->andWhere('aku.createTime <= :endDate')
                ->setParameter('endDate', $endDate)
            ;
        }

        $this->applyModelAndFeatureFilters($qb, $models, $features);
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
            $qb->andWhere('aku.model IN (:models)')
                ->setParameter('models', $models)
            ;
        }

        if (null !== $features && [] !== $features) {
            $qb->andWhere('aku.feature IN (:features)')
                ->setParameter('features', $features)
            ;
        }
    }

    /**
     * 格式化统计结果
     *
     * @param array<string, mixed> $result
     * @return array<string, int>
     */
    private function formatStatisticsResult(array $result): array
    {
        return [
            'total_input_tokens' => is_numeric($result['total_input_tokens'] ?? 0) ? (int) ($result['total_input_tokens'] ?? 0) : 0,
            'total_cache_creation_input_tokens' => is_numeric($result['total_cache_creation_input_tokens'] ?? 0) ? (int) ($result['total_cache_creation_input_tokens'] ?? 0) : 0,
            'total_cache_read_input_tokens' => is_numeric($result['total_cache_read_input_tokens'] ?? 0) ? (int) ($result['total_cache_read_input_tokens'] ?? 0) : 0,
            'total_output_tokens' => is_numeric($result['total_output_tokens'] ?? 0) ? (int) ($result['total_output_tokens'] ?? 0) : 0,
            'total_requests' => is_numeric($result['total_requests'] ?? 0) ? (int) ($result['total_requests'] ?? 0) : 0,
        ];
    }

    /**
     * 格式化Top消费者结果
     *
     * @return array{accessKey: AccessKey, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: \DateTimeImmutable|null, lastUsageTime: \DateTimeImmutable|null}
     */
    private function formatTopConsumerResult(mixed $result): array
    {
        assert(is_array($result));
        $totalInputTokens = is_numeric($result['totalInputTokens'] ?? 0) ? (int) ($result['totalInputTokens'] ?? 0) : 0;
        $totalCacheCreationInputTokens = is_numeric($result['totalCacheCreationInputTokens'] ?? 0) ? (int) ($result['totalCacheCreationInputTokens'] ?? 0) : 0;
        $totalCacheReadInputTokens = is_numeric($result['totalCacheReadInputTokens'] ?? 0) ? (int) ($result['totalCacheReadInputTokens'] ?? 0) : 0;
        $totalOutputTokens = is_numeric($result['totalOutputTokens'] ?? 0) ? (int) ($result['totalOutputTokens'] ?? 0) : 0;
        $totalTokens = $totalInputTokens + $totalCacheCreationInputTokens
                     + $totalCacheReadInputTokens + $totalOutputTokens;

        assert(isset($result['accessKey']) && $result['accessKey'] instanceof AccessKey);

        return [
            'accessKey' => $result['accessKey'],
            'totalTokens' => $totalTokens,
            'totalRequests' => is_numeric($result['totalRequests'] ?? 0) ? (int) ($result['totalRequests'] ?? 0) : 0,
            'totalInputTokens' => $totalInputTokens,
            'totalCacheCreationInputTokens' => $totalCacheCreationInputTokens,
            'totalCacheReadInputTokens' => $totalCacheReadInputTokens,
            'totalOutputTokens' => $totalOutputTokens,
            'firstUsageTime' => isset($result['firstUsageTime']) && $result['firstUsageTime'] instanceof \DateTimeInterface
                ? new \DateTimeImmutable($result['firstUsageTime']->format('Y-m-d H:i:s')) : null,
            'lastUsageTime' => isset($result['lastUsageTime']) && $result['lastUsageTime'] instanceof \DateTimeInterface
                ? new \DateTimeImmutable($result['lastUsageTime']->format('Y-m-d H:i:s')) : null,
        ];
    }

    /**
     * 获取指定时间范围内按 AccessKey 聚合的使用量数据
     *
     * @return array<UsageAggregationData>
     */
    public function getAggregatedDataByAccessKey(
        \DateTimeInterface $fromTime,
        \DateTimeInterface $toTime,
    ): array {
        $queryBuilder = $this->createQueryBuilder('aku')
            ->select('IDENTITY(aku.accessKey) as accessKeyId')
            ->addSelect('SUM(aku.inputTokens) as totalInputTokens')
            ->addSelect('SUM(aku.cacheCreationInputTokens) as totalCacheCreationInputTokens')
            ->addSelect('SUM(aku.cacheReadInputTokens) as totalCacheReadInputTokens')
            ->addSelect('SUM(aku.outputTokens) as totalOutputTokens')
            ->addSelect('COUNT(aku.id) as totalRequests')
            ->where('aku.occurTime >= :fromTime')
            ->andWhere('aku.occurTime < :toTime')
            ->groupBy('aku.accessKey')
            ->setParameter('fromTime', $fromTime)
            ->setParameter('toTime', $toTime)
        ;

        $results = $queryBuilder->getQuery()->getResult();
        assert(is_array($results));

        /** @var array<array<string, mixed>> $results */
        return array_map(
            static fn (array $result): UsageAggregationData => UsageAggregationData::fromQueryResult($result),
            $results
        );
    }
}
