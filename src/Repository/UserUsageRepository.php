<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UserStub;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * UserUsage实体的Repository
 *
 * @extends ServiceEntityRepository<UserUsage>
 */
#[AsRepository(entityClass: UserUsage::class)]
class UserUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserUsage::class);
    }

    /**
     * 保存UserUsage实体
     */
    public function save(UserUsage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除UserUsage实体
     */
    public function remove(UserUsage $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据查询条件查找usage记录
     *
     * @return array<UserUsage>
     */
    public function findByQuery(UsageDetailQuery $query): array
    {
        $qb = $this->createQueryBuilder('uu');
        $this->applyQueryFilters($qb, $query);

        $qb->orderBy('uu.createTime', 'DESC')
            ->setFirstResult($query->getOffset())
            ->setMaxResults($query->limit)
        ;

        /** @var array<UserUsage> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 计算查询条件匹配的记录数量
     */
    public function countByQuery(UsageDetailQuery $query): int
    {
        $qb = $this->createQueryBuilder('uu')
            ->select('COUNT(uu.id)')
        ;
        $this->applyQueryFilters($qb, $query);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 计算User的统计数据
     *
     * @param array<string>|null $models
     * @param array<string>|null $features
     * @return array<string, mixed>
     */
    public function calculateStatistics(
        UserInterface $user,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null,
        ?array $models = null,
        ?array $features = null,
    ): array {
        $qb = $this->createStatisticsQueryBuilder()
            ->where('uu.user = :user')
            ->setParameter('user', $user)
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
     * @return array<array{user: UserInterface, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: ?\DateTimeInterface, lastUsageTime: ?\DateTimeInterface}>
     */
    public function findTopConsumers(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10,
    ): array {
        $qb = $this->createQueryBuilder('uu')
            ->select([
                'IDENTITY(uu.user) as userId',
                'COALESCE(SUM(uu.inputTokens), 0) as totalInputTokens',
                'COALESCE(SUM(uu.cacheCreationInputTokens), 0) as totalCacheCreationInputTokens',
                'COALESCE(SUM(uu.cacheReadInputTokens), 0) as totalCacheReadInputTokens',
                'COALESCE(SUM(uu.outputTokens), 0) as totalOutputTokens',
                'COUNT(uu.id) as totalRequests',
                'MIN(uu.createTime) as firstUsageTime',
                'MAX(uu.createTime) as lastUsageTime',
            ])
            ->where('uu.createTime >= :startDate')
            ->andWhere('uu.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('uu.user')
            ->orderBy('totalInputTokens + totalCacheCreationInputTokens + totalCacheReadInputTokens + totalOutputTokens', 'DESC')
            ->setMaxResults($limit)
        ;

        $results = $qb->getQuery()->getResult();
        assert(is_array($results));

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
    ): array {
        $qb = $this->createStatisticsQueryBuilder()
            ->where('uu.createTime >= :startDate')
            ->andWhere('uu.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

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
    ): int {
        $qb = $this->createQueryBuilder('uu')
            ->select('COUNT(DISTINCT IDENTITY(uu.user))')
            ->where('uu.createTime >= :startDate')
            ->andWhere('uu.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
        ;

        $this->applyModelAndFeatureFilters($qb, $models, $features);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * 应用UsageDetailQuery的过滤条件
     */
    private function applyQueryFilters(QueryBuilder $qb, UsageDetailQuery $query): void
    {
        if (null !== $query->dimensionId) {
            $qb->andWhere('IDENTITY(uu.user) = :dimensionId')
                ->setParameter('dimensionId', $query->dimensionId)
            ;
        }

        if (null !== $query->startDate) {
            $qb->andWhere('uu.createTime >= :startDate')
                ->setParameter('startDate', $query->startDate)
            ;
        }

        if (null !== $query->endDate) {
            $qb->andWhere('uu.createTime <= :endDate')
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
        return $this->createQueryBuilder('uu')
            ->select([
                'COALESCE(SUM(uu.inputTokens), 0) as total_input_tokens',
                'COALESCE(SUM(uu.cacheCreationInputTokens), 0) as total_cache_creation_input_tokens',
                'COALESCE(SUM(uu.cacheReadInputTokens), 0) as total_cache_read_input_tokens',
                'COALESCE(SUM(uu.outputTokens), 0) as total_output_tokens',
                'COUNT(uu.id) as total_requests',
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
            $qb->andWhere('uu.createTime >= :startDate')
                ->setParameter('startDate', $startDate)
            ;
        }

        if (null !== $endDate) {
            $qb->andWhere('uu.createTime <= :endDate')
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
            $qb->andWhere('uu.model IN (:models)')
                ->setParameter('models', $models)
            ;
        }

        if (null !== $features && [] !== $features) {
            $qb->andWhere('uu.feature IN (:features)')
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
     * @return array{user: UserInterface, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: \DateTimeImmutable|null, lastUsageTime: \DateTimeImmutable|null}
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

        $userId = $result['userId'] ?? '';
        if (!\is_string($userId) || '' === $userId) {
            throw new \InvalidArgumentException('User identifier must be a non-empty string');
        }

        return [
            'user' => $this->createUserStub($userId),
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
     * 获取指定时间范围内按 User 聚合的使用量数据
     *
     * @return array<\Tourze\AnthropicTokenUsageBundle\ValueObject\UsageAggregationData>
     */
    public function getAggregatedDataByUser(
        \DateTimeInterface $fromTime,
        \DateTimeInterface $toTime,
    ): array {
        $queryBuilder = $this->createQueryBuilder('uu')
            ->select('IDENTITY(uu.user) as userId')
            ->addSelect('SUM(uu.inputTokens) as totalInputTokens')
            ->addSelect('SUM(uu.cacheCreationInputTokens) as totalCacheCreationInputTokens')
            ->addSelect('SUM(uu.cacheReadInputTokens) as totalCacheReadInputTokens')
            ->addSelect('SUM(uu.outputTokens) as totalOutputTokens')
            ->addSelect('COUNT(uu.id) as totalRequests')
            ->where('uu.occurTime >= :fromTime')
            ->andWhere('uu.occurTime < :toTime')
            ->groupBy('uu.user')
            ->setParameter('fromTime', $fromTime)
            ->setParameter('toTime', $toTime)
        ;

        $results = $queryBuilder->getQuery()->getResult();
        assert(is_array($results));

        /** @var array<array<string, mixed>> $results */
        return array_map(
            static fn (array $result): \Tourze\AnthropicTokenUsageBundle\ValueObject\UsageAggregationData =>
                \Tourze\AnthropicTokenUsageBundle\ValueObject\UsageAggregationData::fromQueryResult($result),
            $results
        );
    }

    /**
     * 创建用户桩对象
     *
     * @param non-empty-string $userId
     */
    private function createUserStub(string $userId): UserInterface
    {
        return new UserStub($userId);
    }
}
