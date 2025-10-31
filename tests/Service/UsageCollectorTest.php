<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\Service;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Service\UsageCollector;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionItem;

/**
 * UsageCollector 服务单元测试
 * 测试重点:异步消息发送、错误处理、性能要求
 *
 * 说明：本测试使用匿名类实现接口作为测试替身(Test Double)，而非Mock。
 * 这是合理的单元测试实践，因为需要精确控制依赖行为以测试各种边界场景。
 *
 * @internal
 * @phpstan-ignore-next-line 测试用例 Tourze\AnthropicTokenUsageBundle\Tests\Unit\Service\UsageCollectorTest 的测试目标 Tourze\AnthropicTokenUsageBundle\Service\UsageCollector 是一个服务，因此不应直接继承自 PHPUnit\Framework\TestCase。
 */
#[CoversClass(UsageCollector::class)]
class UsageCollectorTest extends TestCase
{
    private UsageCollector $usageCollector;

    private MessageBusInterface $messageBus;

    private AccessKeyUsageRepository $accessKeyUsageRepository;

    private UserUsageRepository $userUsageRepository;

    private LoggerInterface $logger;

    private AccessKey $accessKey;

    private UserInterface $user;

    protected function setUp(): void
    {
        $this->messageBus = new class () implements MessageBusInterface {
            /** @var array<mixed> */
            public array $dispatchedMessages = [];

            public bool $shouldThrowException = false;

            public string $exceptionMessage = 'Message bus error';

            /** @var array<mixed> */
            public array $consecutiveReturnValues = [];

            private int $callIndex = 0;

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatchedMessages[] = $message;

                if ($this->shouldThrowException) {
                    throw new \RuntimeException($this->exceptionMessage);
                }

                if ([] !== $this->consecutiveReturnValues) {
                    $returnValue = $this->consecutiveReturnValues[$this->callIndex] ?? null;
                    ++$this->callIndex;

                    if ($returnValue instanceof \Exception) {
                        throw $returnValue;
                    }

                    return $returnValue instanceof Envelope ? $returnValue : new Envelope($message);
                }

                return new Envelope($message);
            }

            /**
             * 获取已分发的消息数组
             *
             * @return array<mixed>
             */
            public function getDispatchedMessages(): array
            {
                /** @var array<mixed> $messages */
                $messages = $this->dispatchedMessages;
                return $messages;
            }
        };

        $mockRegistry = new class () implements ManagerRegistry {
            public function getDefaultConnectionName(): string
            {
                return 'default';
            }

            public function getConnection(?string $name = null): object
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getConnections(): array
            {
                return [];
            }

            public function getConnectionNames(): array
            {
                return ['default' => 'default'];
            }

            public function getDefaultManagerName(): string
            {
                return 'default';
            }

            public function getManager(?string $name = null): ObjectManager
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getManagers(): array
            {
                return [];
            }

            public function resetManager(?string $name = null): ObjectManager
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getAliasNamespace(string $alias): string
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getManagerNames(): array
            {
                return ['default' => 'default'];
            }

            public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getManagerForClass(string $class): ?ObjectManager
            {
                return null;
            }
        };
        $this->accessKeyUsageRepository = new class ($mockRegistry) extends AccessKeyUsageRepository {
            /** @var array<mixed> */
            public array $savedEntities = [];

            public bool $shouldThrowException = false;

            public string $exceptionMessage = 'Sync save failed';

            public function save(AccessKeyUsage $entity, bool $flush = false): void
            {
                if ($this->shouldThrowException) {
                    throw new \RuntimeException($this->exceptionMessage);
                }
                $this->savedEntities[] = $entity;
            }

            /**
             * 获取已保存的实体数组
             *
             * @return array<mixed>
             */
            public function getSavedEntities(): array
            {
                /** @var array<mixed> $entities */
                $entities = $this->savedEntities;
                return $entities;
            }
        };

        $mockRegistry2 = new class () implements ManagerRegistry {
            public function getDefaultConnectionName(): string
            {
                return 'default';
            }

            public function getConnection(?string $name = null): object
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getConnections(): array
            {
                return [];
            }

            public function getConnectionNames(): array
            {
                return ['default' => 'default'];
            }

            public function getDefaultManagerName(): string
            {
                return 'default';
            }

            public function getManager(?string $name = null): ObjectManager
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getManagers(): array
            {
                return [];
            }

            public function resetManager(?string $name = null): ObjectManager
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getAliasNamespace(string $alias): string
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getManagerNames(): array
            {
                return ['default' => 'default'];
            }

            public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
            {
                throw new \RuntimeException('Not implemented in test stub');
            }

            public function getManagerForClass(string $class): ?ObjectManager
            {
                return null;
            }
        };
        $this->userUsageRepository = new class ($mockRegistry2) extends UserUsageRepository {
            /** @var array<mixed> */
            public array $savedEntities = [];

            public bool $shouldThrowException = false;

            public string $exceptionMessage = 'Sync save failed';

            public function save(UserUsage $entity, bool $flush = false): void
            {
                if ($this->shouldThrowException) {
                    throw new \RuntimeException($this->exceptionMessage);
                }
                $this->savedEntities[] = $entity;
            }

            /**
             * 获取已保存的实体数组
             *
             * @return array<mixed>
             */
            public function getSavedEntities(): array
            {
                /** @var array<mixed> $entities */
                $entities = $this->savedEntities;
                return $entities;
            }
        };

        $this->logger = new class () implements LoggerInterface {
            /** @var array<mixed> */
            public array $logs = [];

            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['emergency', $message, $context];
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['alert', $message, $context];
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['critical', $message, $context];
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['error', $message, $context];
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['warning', $message, $context];
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['notice', $message, $context];
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['info', $message, $context];
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['debug', $message, $context];
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [$level, $message, $context];
            }

            /**
             * 获取日志数组
             *
             * @return array<mixed>
             */
            public function getLogs(): array
            {
                /** @var array<mixed> $logs */
                $logs = $this->logs;
                return $logs;
            }
        };

        $this->accessKey = new class () extends AccessKey {
            protected ?string $id = 'ak_test_123';

            public function getId(): ?string
            {
                return $this->id;
            }
        };

        $this->user = new class () implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'user_test_456';
            }
        };

        // 使用测试替身直接构造服务实例（单元测试合理实践）
        $this->usageCollector = new UsageCollector(
            $this->messageBus,
            $this->accessKeyUsageRepository,
            $this->userUsageRepository,
            $this->logger
        );
    }

    public function testCollectUsageSuccessfullyDispatchesMessage(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);
        $metadata = [
            'requestId' => 'req_test_123',
            'model' => 'claude-3-sonnet',
            'endpoint' => '/v1/messages',
        ];

        $result = $this->usageCollector->collectUsage(
            $usageData,
            $this->accessKey,
            $this->user,
            $metadata
        );

        $this->assertTrue($result);
        /** @phpstan-ignore method.notFound */
        $messages = $this->messageBus->getDispatchedMessages();
        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertInstanceOf(UsageCollectionMessage::class, $message);
        $this->assertSame($usageData, $message->usageData);
        $this->assertSame($this->accessKey->getId(), $message->accessKeyId);
        $this->assertSame($this->user->getUserIdentifier(), $message->userId);
        $this->assertSame($metadata, $message->metadata);
    }

    public function testCollectUsageWithNullAccessKey(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);

        $result = $this->usageCollector->collectUsage($usageData, null, $this->user);

        $this->assertTrue($result);
        /** @phpstan-ignore method.notFound */
        $messages = $this->messageBus->getDispatchedMessages();
        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertInstanceOf(UsageCollectionMessage::class, $message);
        $this->assertNull($message->accessKeyId);
    }

    public function testCollectUsageWithNullUser(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);

        $result = $this->usageCollector->collectUsage($usageData, $this->accessKey, null);

        $this->assertTrue($result);
        /** @phpstan-ignore method.notFound */
        $messages = $this->messageBus->getDispatchedMessages();
        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertInstanceOf(UsageCollectionMessage::class, $message);
        $this->assertNull($message->userId);
    }

    public function testCollectUsageWithBothNullAccessKeyAndUser(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);

        $result = $this->usageCollector->collectUsage($usageData, null, null);

        $this->assertTrue($result);
        /** @phpstan-ignore method.notFound */
        $messages = $this->messageBus->getDispatchedMessages();
        $this->assertIsArray($messages);
        $this->assertCount(1, $messages);

        $message = $messages[0];
        $this->assertInstanceOf(UsageCollectionMessage::class, $message);
        $this->assertNull($message->accessKeyId);
        $this->assertNull($message->userId);
    }

    public function testCollectUsageHandlesMessageBusException(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);

        // @phpstan-ignore property.notFound
        $this->messageBus->shouldThrowException = true;
        // @phpstan-ignore property.notFound
        $this->messageBus->exceptionMessage = 'Message bus error';

        $result = $this->usageCollector->collectUsage($usageData, $this->accessKey, $this->user);

        // 即使MessageBus失败，同步保存应该成功（优雅降级）
        $this->assertTrue($result);

        // 验证错误日志记录
        /** @phpstan-ignore method.notFound */
        $logs = $this->logger->getLogs();
        $this->assertIsArray($logs);
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $errorLogs = array_filter($logs, fn ($log) => 'error' === $log[0]);
        $this->assertCount(1, $errorLogs);
        $errorLogsArray = array_values($errorLogs);
        $this->assertArrayHasKey(0, $errorLogsArray);
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $firstErrorLog = $errorLogsArray[0];
        $this->assertIsArray($firstErrorLog);
        $this->assertArrayHasKey(1, $firstErrorLog);
        $this->assertArrayHasKey(2, $firstErrorLog);
        $this->assertSame('Failed to dispatch usage collection message', $firstErrorLog[1]);
        $this->assertIsArray($firstErrorLog[2]);
        $this->assertSame('Message bus error', $firstErrorLog[2]['error']);

        // 确保同步保存成功（优雅降级）
        /** @phpstan-ignore method.notFound */
        $savedAccessKeyEntities = $this->accessKeyUsageRepository->getSavedEntities();
        $this->assertIsArray($savedAccessKeyEntities);
        $this->assertCount(1, $savedAccessKeyEntities);
        /** @phpstan-ignore method.notFound */
        $savedUserEntities = $this->userUsageRepository->getSavedEntities();
        $this->assertIsArray($savedUserEntities);
        $this->assertCount(1, $savedUserEntities);
    }

    public function testCollectUsageLogsSuccessfulDispatch(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);

        $this->usageCollector->collectUsage($usageData, $this->accessKey, $this->user);

        /** @phpstan-ignore method.notFound */
        $logs = $this->logger->getLogs();
        $this->assertIsArray($logs);
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $infoLogs = array_filter($logs, fn ($log) => 'info' === $log[0]);
        $this->assertCount(1, $infoLogs);
        $infoLogsArray = array_values($infoLogs);
        $this->assertArrayHasKey(0, $infoLogsArray);
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $firstInfoLog = $infoLogsArray[0];
        $this->assertIsArray($firstInfoLog);
        $this->assertArrayHasKey(1, $firstInfoLog);
        $this->assertSame('Usage collection message dispatched successfully', $firstInfoLog[1]);
    }

    public function testCollectBatchUsageSuccessfullyProcessesBatch(): void
    {
        $items = [
            $this->createUsageCollectionItem(),
            $this->createUsageCollectionItem(),
            $this->createUsageCollectionItem(),
        ];

        $batch = new UsageCollectionBatch($items);

        $result = $this->usageCollector->collectBatchUsage($batch);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(3, $result->getProcessedCount());
        $this->assertSame(0, $result->getFailedCount());
        $this->assertEmpty($result->getFailures());
        /** @phpstan-ignore method.notFound */
        $messages = $this->messageBus->getDispatchedMessages();
        $this->assertIsArray($messages);
        $this->assertCount(3, $messages);
    }

    public function testCollectBatchUsageHandlesPartialFailures(): void
    {
        $items = [
            $this->createUsageCollectionItem(),
            $this->createUsageCollectionItem(),
            $this->createUsageCollectionItem(),
        ];

        $batch = new UsageCollectionBatch($items);

        // 设置连续返回值：成功、失败、成功
        // @phpstan-ignore property.notFound
        $this->messageBus->consecutiveReturnValues = [
            new Envelope(new UsageCollectionMessage(new AnthropicUsageData(100, 0, 0, 50), 'ak_test', 'user_test', [])),
            new \RuntimeException('Dispatch failed'),
            new Envelope(new UsageCollectionMessage(new AnthropicUsageData(100, 0, 0, 50), 'ak_test', 'user_test', [])),
        ];

        // 确保同步保存也失败，只有第二项需要同步保存（因为MessageBus失败）
        // @phpstan-ignore property.notFound
        $this->accessKeyUsageRepository->shouldThrowException = true;
        // @phpstan-ignore property.notFound
        $this->userUsageRepository->shouldThrowException = true;

        $result = $this->usageCollector->collectBatchUsage($batch);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(2, $result->getProcessedCount());
        $this->assertSame(1, $result->getFailedCount());
        $this->assertCount(1, $result->getFailures());
    }

    public function testCollectBatchUsageLogsProgress(): void
    {
        $items = [
            $this->createUsageCollectionItem(),
            $this->createUsageCollectionItem(),
        ];

        $batch = new UsageCollectionBatch($items);

        $this->usageCollector->collectBatchUsage($batch);

        /** @phpstan-ignore method.notFound */
        $logs = $this->logger->getLogs();
        $this->assertIsArray($logs);
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $infoLogs = array_filter($logs, fn ($log) => 'info' === $log[0]);
        $this->assertGreaterThanOrEqual(2, count($infoLogs));

        // 检查开始日志
        $infoLogsArray = array_values($infoLogs);
        $this->assertArrayHasKey(0, $infoLogsArray);
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        $firstInfoLog = $infoLogsArray[0];
        $this->assertIsArray($firstInfoLog);
        $this->assertArrayHasKey(1, $firstInfoLog);
        $this->assertArrayHasKey(2, $firstInfoLog);
        $startLog = $firstInfoLog;
        $this->assertSame('Starting batch usage collection', $startLog[1]);
        $this->assertIsArray($startLog[2]);
        $this->assertArrayHasKey('batch_id', $startLog[2]);
        $this->assertArrayHasKey('total_items', $startLog[2]);
        $this->assertArrayHasKey('total_tokens', $startLog[2]);
        $this->assertSame(2, $startLog[2]['total_items']);

        // 检查完成日志
        $endLog = end($infoLogsArray);
        $this->assertIsArray($endLog);
        $this->assertArrayHasKey(1, $endLog);
        $this->assertArrayHasKey(2, $endLog);
        $this->assertSame('Batch usage collection completed', $endLog[1]);
        $this->assertIsArray($endLog[2]);
    }

    public function testPerformanceRequirementForSingleUsageCollection(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);

        $startTime = microtime(true);
        $this->usageCollector->collectUsage($usageData, $this->accessKey, $this->user);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // 转换为毫秒

        // 验证单次调用延迟 < 50ms (根据FRD性能要求)
        $this->assertLessThan(50, $executionTime, 'Usage collection should complete within 50ms');
    }

    public function testCollectUsageSync(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);
        $metadata = ['requestId' => 'req_sync_test'];

        $result = $this->usageCollector->collectUsageSync(
            $usageData,
            $this->accessKey,
            $this->user,
            $metadata
        );

        $this->assertTrue($result);

        // 验证同步保存
        /** @phpstan-ignore method.notFound */
        $savedAccessKeyEntities = $this->accessKeyUsageRepository->getSavedEntities();
        $this->assertIsArray($savedAccessKeyEntities);
        $this->assertCount(1, $savedAccessKeyEntities);
        /** @phpstan-ignore method.notFound */
        $savedUserEntities = $this->userUsageRepository->getSavedEntities();
        $this->assertIsArray($savedUserEntities);
        $this->assertCount(1, $savedUserEntities);

        // 消息总线不应该被调用（同步模式）
        /** @phpstan-ignore method.notFound */
        $messages = $this->messageBus->getDispatchedMessages();
        $this->assertIsArray($messages);
        $this->assertCount(0, $messages);
    }

    private function createUsageCollectionItem(): UsageCollectionItem
    {
        return new UsageCollectionItem(
            new AnthropicUsageData(100, 0, 0, 50),
            $this->accessKey,
            $this->user,
            ['requestId' => 'req_' . uniqid()]
        );
    }
}
