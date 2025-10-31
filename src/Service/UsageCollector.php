<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\Interface\UsageCollectorInterface;
use Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\AnthropicTokenUsageBundle\ValueObject\BatchProcessResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;

/**
 * Usage收集服务实现
 *
 * 负责收集Anthropic API token使用数据，支持异步和同步处理模式
 */
#[WithMonologChannel(channel: 'anthropic_token_usage')]
final readonly class UsageCollector implements UsageCollectorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private AccessKeyUsageRepository $accessKeyUsageRepository,
        private UserUsageRepository $userUsageRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function collectUsage(
        AnthropicUsageData $usageData,
        ?AccessKey $accessKey = null,
        ?UserInterface $user = null,
        array $metadata = [],
    ): bool {
        try {
            // 创建消息对象并发送到队列
            $message = new UsageCollectionMessage(
                $usageData,
                $accessKey?->getId(),
                $user?->getUserIdentifier(),
                $metadata
            );

            $this->messageBus->dispatch($message);

            $this->logger->info('Usage collection message dispatched successfully', [
                'access_key_id' => $accessKey?->getId(),
                'user_id' => $user?->getUserIdentifier(),
                'input_tokens' => $usageData->inputTokens,
                'output_tokens' => $usageData->outputTokens,
                'total_tokens' => $usageData->getTotalTokens(),
                'metadata' => $metadata,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to dispatch usage collection message', [
                'error' => $e->getMessage(),
                'access_key_id' => $accessKey?->getId(),
                'user_id' => $user?->getUserIdentifier(),
                'metadata' => $metadata,
                'exception' => $e,
            ]);

            // 优雅降级：异步失败时尝试同步保存
            return $this->collectUsageSync($usageData, $accessKey, $user, $metadata);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function collectBatchUsage(UsageCollectionBatch $batch): BatchProcessResult
    {
        if ($batch->isEmpty()) {
            return new BatchProcessResult(0, 0, 0);
        }

        $totalItems = $batch->getSize();
        $successCount = 0;
        $errors = [];
        $batchId = uniqid('batch_', true);

        $this->logger->info('Starting batch usage collection', [
            'batch_id' => $batchId,
            'total_items' => $totalItems,
            'total_tokens' => $batch->getTotalTokens(),
        ]);

        foreach ($batch->items as $index => $item) {
            try {
                $success = $this->collectUsage(
                    $item->usageData,
                    $item->accessKey,
                    $item->user,
                    array_merge($item->metadata, ['batch_id' => $batchId, 'batch_index' => $index])
                );

                if ($success) {
                    ++$successCount;
                } else {
                    $errors[] = "Item {$index}: Failed to collect usage data";
                }
            } catch (\Throwable $e) {
                $errors[] = "Item {$index}: {$e->getMessage()}";
                $this->logger->error('Batch item processing failed', [
                    'batch_id' => $batchId,
                    'item_index' => $index,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        $failureCount = $totalItems - $successCount;
        $result = new BatchProcessResult($totalItems, $successCount, $failureCount, $errors, $batchId);

        $this->logger->info('Batch usage collection completed', [
            'batch_id' => $batchId,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'success_rate' => $result->getSuccessRate(),
        ]);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function collectUsageSync(
        AnthropicUsageData $usageData,
        ?AccessKey $accessKey = null,
        ?UserInterface $user = null,
        array $metadata = [],
    ): bool {
        $errors = [];
        $occurTime = $this->getOccurTime($metadata);

        $errors = array_merge($errors, $this->collectAccessKeyUsage($usageData, $accessKey, $user, $metadata, $occurTime));
        $errors = array_merge($errors, $this->collectUserUsage($usageData, $user, $accessKey, $metadata, $occurTime));
        $this->handleNoContextWarning($accessKey, $user, $metadata, $usageData);

        return $this->handleCollectionResult($errors, $metadata);
    }

    /**
     * 填充AccessKeyUsage实体
     *
     * @param array<string, mixed> $metadata
     */
    private function fillUsageEntity(
        AccessKeyUsage $entity,
        AnthropicUsageData $usageData,
        AccessKey $accessKey,
        ?UserInterface $user,
        array $metadata,
        \DateTimeInterface $occurTime,
    ): void {
        $entity->setAccessKey($accessKey);
        $entity->setUser($user);

        $entity->setInputTokens($usageData->inputTokens);
        $entity->setCacheCreationInputTokens($usageData->cacheCreationInputTokens);
        $entity->setCacheReadInputTokens($usageData->cacheReadInputTokens);
        $entity->setOutputTokens($usageData->outputTokens);

        $entity->setOccurTime(\DateTimeImmutable::createFromInterface($occurTime));

        // 设置元数据字段
        if (isset($metadata['request_id']) && is_string($metadata['request_id'])) {
            $entity->setRequestId($metadata['request_id']);
        }
        if (isset($metadata['model']) && is_string($metadata['model'])) {
            $entity->setModel($metadata['model']);
        }
        if (isset($metadata['stop_reason']) && is_string($metadata['stop_reason'])) {
            $entity->setStopReason($metadata['stop_reason']);
        }
        if (isset($metadata['endpoint']) && is_string($metadata['endpoint'])) {
            $entity->setEndpoint($metadata['endpoint']);
        }
        if (isset($metadata['feature']) && is_string($metadata['feature'])) {
            $entity->setFeature($metadata['feature']);
        }
    }

    /**
     * 填充UserUsage实体
     *
     * @param array<string, mixed> $metadata
     */
    private function fillUserUsageEntity(
        UserUsage $entity,
        AnthropicUsageData $usageData,
        UserInterface $user,
        ?AccessKey $accessKey,
        array $metadata,
        \DateTimeInterface $occurTime,
    ): void {
        $entity->setUser($user);
        $entity->setAccessKey($accessKey);

        $entity->setInputTokens($usageData->inputTokens);
        $entity->setCacheCreationInputTokens($usageData->cacheCreationInputTokens);
        $entity->setCacheReadInputTokens($usageData->cacheReadInputTokens);
        $entity->setOutputTokens($usageData->outputTokens);

        $entity->setOccurTime(\DateTimeImmutable::createFromInterface($occurTime));

        // 设置元数据字段
        if (isset($metadata['request_id']) && is_string($metadata['request_id'])) {
            $entity->setRequestId($metadata['request_id']);
        }
        if (isset($metadata['model']) && is_string($metadata['model'])) {
            $entity->setModel($metadata['model']);
        }
        if (isset($metadata['stop_reason']) && is_string($metadata['stop_reason'])) {
            $entity->setStopReason($metadata['stop_reason']);
        }
        if (isset($metadata['endpoint']) && is_string($metadata['endpoint'])) {
            $entity->setEndpoint($metadata['endpoint']);
        }
        if (isset($metadata['feature']) && is_string($metadata['feature'])) {
            $entity->setFeature($metadata['feature']);
        }
    }

    /**
     * 获取发生时间
     *
     * @param array<string, mixed> $metadata
     */
    private function getOccurTime(array $metadata): \DateTimeInterface
    {
        return isset($metadata['occurTime']) && $metadata['occurTime'] instanceof \DateTimeInterface
            ? $metadata['occurTime']
            : new \DateTimeImmutable();
    }

    /**
     * 收集AccessKey使用数据
     *
     * @param array<string, mixed> $metadata
     * @return array<string>
     */
    private function collectAccessKeyUsage(
        AnthropicUsageData $usageData,
        ?AccessKey $accessKey,
        ?UserInterface $user,
        array $metadata,
        \DateTimeInterface $occurTime,
    ): array {
        if (null === $accessKey) {
            return [];
        }

        try {
            $accessKeyUsage = new AccessKeyUsage();
            $this->fillUsageEntity($accessKeyUsage, $usageData, $accessKey, $user, $metadata, $occurTime);
            $this->accessKeyUsageRepository->save($accessKeyUsage);

            $this->logger->debug('AccessKeyUsage saved successfully', [
                'access_key_id' => $accessKey->getId(),
                'usage_id' => $accessKeyUsage->getId(),
            ]);

            return [];
        } catch (\Throwable $e) {
            $error = 'AccessKeyUsage save failed: ' . $e->getMessage();
            $this->logger->error('Failed to save AccessKeyUsage', [
                'access_key_id' => $accessKey->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return [$error];
        }
    }

    /**
     * 收集User使用数据
     *
     * @param array<string, mixed> $metadata
     * @return array<string>
     */
    private function collectUserUsage(
        AnthropicUsageData $usageData,
        ?UserInterface $user,
        ?AccessKey $accessKey,
        array $metadata,
        \DateTimeInterface $occurTime,
    ): array {
        if (null === $user) {
            return [];
        }

        try {
            $userUsage = new UserUsage();
            $this->fillUserUsageEntity($userUsage, $usageData, $user, $accessKey, $metadata, $occurTime);
            $this->userUsageRepository->save($userUsage);

            $this->logger->debug('UserUsage saved successfully', [
                'user_id' => $user->getUserIdentifier(),
                'usage_id' => $userUsage->getId(),
            ]);

            return [];
        } catch (\Throwable $e) {
            $error = 'UserUsage save failed: ' . $e->getMessage();
            $this->logger->error('Failed to save UserUsage', [
                'user_id' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return [$error];
        }
    }

    /**
     * 处理无上下文警告
     *
     * @param array<string, mixed> $metadata
     */
    private function handleNoContextWarning(
        ?AccessKey $accessKey,
        ?UserInterface $user,
        array $metadata,
        AnthropicUsageData $usageData,
    ): void {
        if (null === $accessKey && null === $user) {
            $this->logger->warning('Usage collection without AccessKey or User context', [
                'metadata' => $metadata,
                'total_tokens' => $usageData->getTotalTokens(),
            ]);
        }
    }

    /**
     * 处理收集结果
     *
     * @param array<string> $errors
     * @param array<string, mixed> $metadata
     */
    private function handleCollectionResult(array $errors, array $metadata): bool
    {
        $success = [] === $errors;

        if (!$success) {
            $this->logger->error('Sync usage collection failed', [
                'errors' => $errors,
                'metadata' => $metadata,
            ]);
        }

        return $success;
    }
}
