<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Interface;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\ValueObject\PaginatedUsageDetailResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageTrendResult;

/**
 * Usage查询服务接口
 */
interface UsageQueryServiceInterface
{
    /**
     * 查询AccessKey维度的usage统计
     */
    public function getAccessKeyUsageStatistics(
        AccessKey $accessKey,
        UsageQueryFilter $filter,
    ): UsageStatisticsResult;

    /**
     * 查询User维度的usage统计
     */
    public function getUserUsageStatistics(
        UserInterface $user,
        UsageQueryFilter $filter,
    ): UsageStatisticsResult;

    /**
     * 获取详细的usage记录列表 (支持分页)
     */
    public function getUsageDetails(UsageDetailQuery $query): PaginatedUsageDetailResult;

    /**
     * 获取聚合趋势数据 (用于图表展示)
     */
    public function getUsageTrends(UsageTrendQuery $query): UsageTrendResult;

    /**
     * 获取指定时间段内的Top消费AccessKey
     *
     * @param int $limit 返回数量限制
     * @return array<array{accessKey: AccessKey, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: ?\DateTimeInterface, lastUsageTime: ?\DateTimeInterface}>
     */
    public function getTopAccessKeys(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10,
    ): array;

    /**
     * 获取指定时间段内的Top消费User
     *
     * @param int $limit 返回数量限制
     * @return array<array{user: UserInterface, totalTokens: int, totalRequests: int, totalInputTokens: int, totalCacheCreationInputTokens: int, totalCacheReadInputTokens: int, totalOutputTokens: int, firstUsageTime: ?\DateTimeInterface, lastUsageTime: ?\DateTimeInterface}>
     */
    public function getTopUsers(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10,
    ): array;
}
