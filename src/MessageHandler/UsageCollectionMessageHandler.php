<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Interface\AccessKeyFinderInterface;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;

/**
 * Usage收集消息处理器
 *
 * 异步处理token usage数据的存储
 */
#[AsMessageHandler]
#[WithMonologChannel(channel: 'anthropic_token_usage')]
final class UsageCollectionMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccessKeyFinderInterface $accessKeyFinder,
        private readonly AccessKeyUsageRepository $accessKeyUsageRepository,
        private readonly UserUsageRepository $userUsageRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(UsageCollectionMessage $message): void
    {
        $messageId = $message->getMessageId();

        $this->logProcessingStart($message, $messageId);

        try {
            $this->entityManager->beginTransaction();

            $context = $this->resolveMessageContext($message, $messageId);
            $savedEntities = $this->persistUsageEntities($message, $context, $messageId);

            $this->commitTransaction($savedEntities, $messageId, $message);
        } catch (\Throwable $e) {
            $this->handleProcessingError($e, $messageId, $message);
            throw $e;
        }
    }

    /**
     * 记录处理开始日志
     */
    private function logProcessingStart(UsageCollectionMessage $message, string $messageId): void
    {
        $this->logger->info('Processing usage collection message', [
            'message_id' => $messageId,
            'access_key_id' => $message->accessKeyId,
            'user_id' => $message->userId,
            'total_tokens' => $message->usageData->getTotalTokens(),
        ]);
    }

    /**
     * 解析消息上下文（AccessKey和User）
     *
     * @return array{accessKey: ?AccessKey, user: ?UserInterface, occurTime: \DateTimeInterface}
     */
    private function resolveMessageContext(UsageCollectionMessage $message, string $messageId): array
    {
        $accessKey = null;
        $user = null;

        // 解析 AccessKey
        if (null !== $message->accessKeyId) {
            $accessKey = $this->accessKeyFinder->findRequiredById($message->accessKeyId);
        }

        // 解析 User（目前为null，但保留扩展点）
        if (null !== $message->userId) {
            $this->logger->debug('User context available', [
                'user_id' => $message->userId,
                'message_id' => $messageId,
            ]);
            // TODO: 未来可扩展User解析逻辑
        }

        $now = new \DateTimeImmutable();
        $occurTime = isset($message->metadata['occurTime']) && $message->metadata['occurTime'] instanceof \DateTimeInterface
            ? $message->metadata['occurTime']
            : $now;

        return [
            'accessKey' => $accessKey,
            'user' => $user,
            'occurTime' => $occurTime,
        ];
    }

    /**
     * 持久化Usage实体
     *
     * @param array{accessKey: ?AccessKey, user: ?UserInterface, occurTime: \DateTimeInterface} $context
     */
    private function persistUsageEntities(UsageCollectionMessage $message, array $context, string $messageId): int
    {
        $savedEntities = 0;

        // 保存 AccessKeyUsage
        if (null !== $context['accessKey']) {
            $this->persistAccessKeyUsage($message, $context, $messageId);
            ++$savedEntities;
        }

        // 保存 UserUsage
        if (null !== $context['user']) {
            $this->persistUserUsage($message, $context, $messageId);
            ++$savedEntities;
        }

        return $savedEntities;
    }

    /**
     * 持久化AccessKeyUsage实体
     *
     * @param array{accessKey: ?AccessKey, user: ?UserInterface, occurTime: \DateTimeInterface} $context
     */
    private function persistAccessKeyUsage(UsageCollectionMessage $message, array $context, string $messageId): void
    {
        $accessKey = $context['accessKey'];
        assert($accessKey instanceof AccessKey);

        $accessKeyUsage = new AccessKeyUsage();
        $this->fillAccessKeyUsage($accessKeyUsage, $message, $accessKey, $context['user'], $context['occurTime']);
        $this->accessKeyUsageRepository->save($accessKeyUsage, false);

        $this->logger->debug('AccessKeyUsage entity prepared', [
            'message_id' => $messageId,
            'access_key_id' => $accessKey->getId(),
            'usage_id' => $accessKeyUsage->getId(),
        ]);
    }

    /**
     * 持久化UserUsage实体
     *
     * @param array{accessKey: ?AccessKey, user: ?UserInterface, occurTime: \DateTimeInterface} $context
     */
    private function persistUserUsage(UsageCollectionMessage $message, array $context, string $messageId): void
    {
        $user = $context['user'];
        assert($user instanceof UserInterface);

        $userUsage = new UserUsage();
        $this->fillUserUsage($userUsage, $message, $user, $context['accessKey'], $context['occurTime']);
        $this->userUsageRepository->save($userUsage, false);

        $this->logger->debug('UserUsage entity prepared', [
            'message_id' => $messageId,
            'user_id' => $user->getUserIdentifier(),
            'usage_id' => $userUsage->getId(),
        ]);
    }

    /**
     * 提交事务或回滚
     */
    private function commitTransaction(int $savedEntities, string $messageId, UsageCollectionMessage $message): void
    {
        if ($savedEntities > 0) {
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Usage collection message processed successfully', [
                'message_id' => $messageId,
                'saved_entities' => $savedEntities,
                'processing_time' => microtime(true),
            ]);
        } else {
            $this->entityManager->rollback();

            $this->logger->warning('No entities to save for usage collection message', [
                'message_id' => $messageId,
                'access_key_id' => $message->accessKeyId,
                'user_id' => $message->userId,
            ]);
        }
    }

    /**
     * 处理处理错误
     */
    private function handleProcessingError(\Throwable $e, string $messageId, UsageCollectionMessage $message): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        $this->logger->error('Failed to process usage collection message', [
            'message_id' => $messageId,
            'error' => $e->getMessage(),
            'access_key_id' => $message->accessKeyId,
            'user_id' => $message->userId,
            'metadata' => $message->metadata,
            'exception' => $e,
        ]);
    }

    /**
     * 填充AccessKeyUsage实体
     */
    private function fillAccessKeyUsage(
        AccessKeyUsage $entity,
        UsageCollectionMessage $message,
        AccessKey $accessKey,
        ?UserInterface $user,
        \DateTimeInterface $occurTime,
    ): void {
        $entity->setAccessKey($accessKey);
        $entity->setUser($user);

        // 设置token数据
        $entity->setInputTokens($message->usageData->inputTokens);
        $entity->setCacheCreationInputTokens($message->usageData->cacheCreationInputTokens);
        $entity->setCacheReadInputTokens($message->usageData->cacheReadInputTokens);
        $entity->setOutputTokens($message->usageData->outputTokens);

        $entity->setOccurTime(\DateTimeImmutable::createFromInterface($occurTime));

        // 设置元数据
        $this->setMetadataFields($entity, $message->metadata);
    }

    /**
     * 填充UserUsage实体
     */
    private function fillUserUsage(
        UserUsage $entity,
        UsageCollectionMessage $message,
        UserInterface $user,
        ?AccessKey $accessKey,
        \DateTimeInterface $occurTime,
    ): void {
        $entity->setUser($user);
        $entity->setAccessKey($accessKey);

        // 设置token数据
        $entity->setInputTokens($message->usageData->inputTokens);
        $entity->setCacheCreationInputTokens($message->usageData->cacheCreationInputTokens);
        $entity->setCacheReadInputTokens($message->usageData->cacheReadInputTokens);
        $entity->setOutputTokens($message->usageData->outputTokens);

        $entity->setOccurTime(\DateTimeImmutable::createFromInterface($occurTime));

        // 设置元数据
        $this->setMetadataFields($entity, $message->metadata);
    }

    /**
     * 设置通用元数据字段
     *
     * @param AccessKeyUsage|UserUsage $entity
     * @param array<string, mixed> $metadata
     */
    private function setMetadataFields(AccessKeyUsage|UserUsage $entity, array $metadata): void
    {
        if (isset($metadata['request_id']) && is_scalar($metadata['request_id'])) {
            $entity->setRequestId((string) $metadata['request_id']);
        }
        if (isset($metadata['model']) && is_scalar($metadata['model'])) {
            $entity->setModel((string) $metadata['model']);
        }
        if (isset($metadata['stop_reason']) && is_scalar($metadata['stop_reason'])) {
            $entity->setStopReason((string) $metadata['stop_reason']);
        }
        if (isset($metadata['endpoint']) && is_scalar($metadata['endpoint'])) {
            $entity->setEndpoint((string) $metadata['endpoint']);
        }
        if (isset($metadata['feature']) && is_scalar($metadata['feature'])) {
            $entity->setFeature((string) $metadata['feature']);
        }
    }
}
