<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Interface;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\AnthropicTokenUsageBundle\ValueObject\BatchProcessResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;

/**
 * Usage收集服务接口
 */
interface UsageCollectorInterface
{
    /**
     * 收集并异步处理token usage数据
     *
     * @param array<string, mixed> $metadata 额外的元数据 (requestId, model, endpoint等)
     * @return bool 是否成功提交到处理队列
     */
    public function collectUsage(
        AnthropicUsageData $usageData,
        ?AccessKey $accessKey = null,
        ?UserInterface $user = null,
        array $metadata = [],
    ): bool;

    /**
     * 批量收集usage数据 (性能优化)
     *
     * @param UsageCollectionBatch $batch
     * @return BatchProcessResult
     */
    public function collectBatchUsage(UsageCollectionBatch $batch): BatchProcessResult;

    /**
     * 同步收集usage数据 (用于测试或紧急情况)
     *
     * @param array<string, mixed> $metadata
     * @return bool 是否成功保存
     */
    public function collectUsageSync(
        AnthropicUsageData $usageData,
        ?AccessKey $accessKey = null,
        ?UserInterface $user = null,
        array $metadata = [],
    ): bool;
}
