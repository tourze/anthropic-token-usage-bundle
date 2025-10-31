<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\MessageHandler;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\ORM\Cache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadataFactory as PersistenceClassMetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Interface\AccessKeyFinderInterface;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage;
use Tourze\AnthropicTokenUsageBundle\MessageHandler\UsageCollectionMessageHandler;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

/**
 * UsageCollectionMessageHandler 消息处理器单元测试
 * 测试重点：消息处理逻辑、数据持久化、错误处理
 * @internal
 * @phpstan-ignore-next-line
 */
#[CoversClass(UsageCollectionMessageHandler::class)]
class UsageCollectionMessageHandlerTest extends TestCase
{
    private UsageCollectionMessageHandler $handler;

    private EntityManagerInterface $entityManager;

    private AccessKeyFinderInterface $accessKeyFinder;

    private AccessKeyUsageRepository $accessKeyUsageRepository;

    private UserUsageRepository $userUsageRepository;

    private LoggerInterface $logger;

    /**
     * @phpstan-ignore-next-line
     */
    protected function setUp(): void
    {
        /** @phpstan-ignore-next-line */
        $this->entityManager = new class () implements EntityManagerInterface {
            /** @var list<array{0: string}> */
            public array $calls = [];

            public bool $transactionActive = false;

            public function beginTransaction(): void
            {
                $this->calls[] = ['beginTransaction'];
                $this->transactionActive = true;
            }

            public function flush(): void
            {
                $this->calls[] = ['flush'];
            }

            public function commit(): void
            {
                $this->calls[] = ['commit'];
                $this->transactionActive = false;
            }

            public function rollback(): void
            {
                $this->calls[] = ['rollback'];
                $this->transactionActive = false;
            }

            public function getConnection(): Connection
            {
                $driver = new class () implements Driver {
                    public function connect(array $params): Driver\Connection
                    {
                        throw new \RuntimeException('Not implemented');
                    }

                    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
                    {
                        throw new \RuntimeException('Not implemented');
                    }

                    /**
                     * @return AbstractSchemaManager<AbstractPlatform>
                     */
                    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
                    {
                        throw new \RuntimeException('Not implemented');
                    }

                    public function getExceptionConverter(): ExceptionConverter
                    {
                        throw new \RuntimeException('Not implemented');
                    }
                };

                $config = new Configuration();

                return new class ([], $driver, $config) extends Connection {
                    public function isTransactionActive(): bool
                    {
                        return true;
                    }
                };
            }

            // Other required methods with default implementations
            public function find(string $className, mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?object
            {
                return null;
            }

            public function persist(object $object): void
            {
            }

            public function remove(object $object): void
            {
            }

            public function merge(object $object): object
            {
                return $object;
            }

            public function clear(?string $objectName = null): void
            {
            }

            public function detach(object $object): void
            {
            }

            public function refresh(object $object, LockMode|int|null $lockMode = null): void
            {
            }

            public function getClassMetadata(string $className): ClassMetadata
            {
                throw new \RuntimeException('Not implemented');
            }

            /**
             * @phpstan-ignore method.childReturnType
             */
            public function getMetadataFactory(): ClassMetadataFactory
            {
                throw new \RuntimeException('Not implemented');
            }

            public function initializeObject(object $obj): void
            {
            }

            public function contains(object $object): bool
            {
                return false;
            }

            public function getRepository(string $className): EntityRepository
            {
                throw new \RuntimeException('Not implemented');
            }

            public function getConfiguration(): Configuration
            {
                throw new \RuntimeException('Not implemented');
            }

            public function isOpen(): bool
            {
                return true;
            }

            public function getUnitOfWork(): UnitOfWork
            {
                throw new \RuntimeException('Not implemented');
            }

            public function getHydrator(string|int $hydrationMode): AbstractHydrator
            {
                throw new \RuntimeException('Not implemented');
            }

            public function newHydrator(string|int $hydrationMode): AbstractHydrator
            {
                throw new \RuntimeException('Not implemented');
            }

            public function getProxyFactory(): ProxyFactory
            {
                throw new \RuntimeException('Not implemented');
            }

            public function createQuery(string $dql = ''): Query
            {
                throw new \RuntimeException('Not implemented');
            }

            public function createNamedQuery(string $name): Query
            {
                throw new \RuntimeException('Not implemented');
            }

            public function createNativeQuery(string $sql, ResultSetMapping $rsm): NativeQuery
            {
                throw new \RuntimeException('Not implemented');
            }

            public function createNamedNativeQuery(string $name): NativeQuery
            {
                throw new \RuntimeException('Not implemented');
            }

            public function createQueryBuilder(): QueryBuilder
            {
                throw new \RuntimeException('Not implemented');
            }

            public function getReference(string $entityName, $id): ?object
            {
                return null;
            }

            public function getPartialReference(string $entityName, mixed $identifier): object
            {
                throw new \RuntimeException('Not implemented');
            }

            public function close(): void
            {
            }

            public function copy(object $entity, bool $deep = false): object
            {
                return $entity;
            }

            public function lock(object $entity, LockMode|int $lockMode, \DateTimeInterface|int|null $lockVersion = null): void
            {
            }

            public function getEventManager(): EventManager
            {
                throw new \RuntimeException('Not implemented');
            }

            public function getFilters(): FilterCollection
            {
                throw new \RuntimeException('Not implemented');
            }

            public function isFiltersStateClean(): bool
            {
                return true;
            }

            public function hasFilters(): bool
            {
                return false;
            }

            public function getCache(): ?Cache
            {
                return null;
            }

            public function getExpressionBuilder(): Expr
            {
                throw new \RuntimeException('Not implemented');
            }

            public function wrapInTransaction(callable $func): mixed
            {
                return $func($this);
            }

            public function isUninitializedObject(mixed $value): bool
            {
                return false;
            }
        };

        $finder = new class () implements AccessKeyFinderInterface {
            /** @var list<int|string> */
            public array $findCalls = [];

            /** @var array<string, AccessKey> */
            public array $returnValues = [];

            public function findRequiredById(int|string $accessKeyId): AccessKey
            {
                $this->findCalls[] = $accessKeyId;

                if (!isset($this->returnValues[$accessKeyId])) {
                    throw new \InvalidArgumentException('AccessKey not found: ' . $accessKeyId);
                }

                return $this->returnValues[$accessKeyId];
            }

            public function findById(int|string $accessKeyId): ?AccessKey
            {
                $this->findCalls[] = $accessKeyId;

                return $this->returnValues[$accessKeyId] ?? null;
            }
        };
        $this->accessKeyFinder = $finder;

        $mockRegistry = $this->createMock(ManagerRegistry::class);
        $this->accessKeyUsageRepository = new class ($mockRegistry) extends AccessKeyUsageRepository {
            /** @var list<AccessKeyUsage> */
            public array $savedEntities = [];

            public bool $shouldThrowException = false;

            public string $exceptionMessage = 'Database error';

            public function save(AccessKeyUsage $entity, bool $flush = false): void
            {
                if ($this->shouldThrowException) {
                    throw new \RuntimeException($this->exceptionMessage);
                }
                $this->savedEntities[] = $entity;
            }
        };

        $mockRegistry2 = $this->createMock(ManagerRegistry::class);
        $this->userUsageRepository = new class ($mockRegistry2) extends UserUsageRepository {
            /** @var list<UserUsage> */
            public array $savedEntities = [];

            public function save(UserUsage $entity, bool $flush = false): void
            {
                $this->savedEntities[] = $entity;
            }
        };

        $this->logger = new class () implements LoggerInterface {
            /** @var list<array{0: mixed, 1: string|\Stringable, 2: array<mixed>}> */
            public array $logs = [];

            public function emergency(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['emergency', (string) $message, $context];
            }

            public function alert(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['alert', (string) $message, $context];
            }

            public function critical(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['critical', (string) $message, $context];
            }

            public function error(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['error', (string) $message, $context];
            }

            public function warning(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['warning', (string) $message, $context];
            }

            public function notice(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['notice', (string) $message, $context];
            }

            public function info(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['info', (string) $message, $context];
            }

            public function debug(\Stringable|string $message, array $context = []): void
            {
                $this->logs[] = ['debug', (string) $message, $context];
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };

        $this->handler = new UsageCollectionMessageHandler(
            $this->entityManager,
            $this->accessKeyFinder,
            $this->accessKeyUsageRepository,
            $this->userUsageRepository,
            $this->logger
        );
    }

    public function testHandleMessageWithAccessKeyAndUser(): void
    {
        $usageData = new AnthropicUsageData(
            inputTokens: 100,
            cacheCreationInputTokens: 25,
            cacheReadInputTokens: 50,
            outputTokens: 75
        );
        $metadata = [
            'request_id' => 'req_test_123',
            'model' => 'claude-3-sonnet',
            'endpoint' => '/v1/messages',
            'feature' => 'chat_completion',
        ];

        $message = new UsageCollectionMessage($usageData, 'ak_test_123', 'user_test_456', $metadata);

        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };

        // Set up access key to be found
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyFinder->returnValues['ak_test_123'] = $accessKey;

        $this->handler->__invoke($message);

        // Verify transaction management
        $transactionCalls = array_filter($this->getTransactionCalls(), fn ($call) => in_array($call[0], ['beginTransaction', 'flush', 'commit'], true));
        $this->assertCount(3, $transactionCalls);
        $transactionCalls = array_values($transactionCalls);
        $this->assertSame('beginTransaction', $transactionCalls[0][0]);
        $this->assertSame('flush', $transactionCalls[1][0]);
        $this->assertSame('commit', $transactionCalls[2][0]);

        // Verify access key lookup
        $this->assertContains('ak_test_123', $this->getFindCalls());

        // Verify AccessKeyUsage entity was saved
        $savedUsages = $this->getSavedAccessKeyUsages();
        $this->assertCount(1, $savedUsages);
        $usage = $savedUsages[0];
        $this->assertSame($usageData->inputTokens, $usage->getInputTokens());
        $this->assertSame($usageData->cacheCreationInputTokens, $usage->getCacheCreationInputTokens());
        $this->assertSame($usageData->cacheReadInputTokens, $usage->getCacheReadInputTokens());
        $this->assertSame($usageData->outputTokens, $usage->getOutputTokens());
        $this->assertSame($metadata['request_id'], $usage->getRequestId());
        $this->assertSame($metadata['model'], $usage->getModel());
        $this->assertSame($metadata['endpoint'], $usage->getEndpoint());
        $this->assertSame($metadata['feature'], $usage->getFeature());

        // UserUsage不会被保存，因为用户解析逻辑未完整实现
        $this->assertCount(0, $this->getSavedUserUsages());

        // Logger应该被调用多次：处理开始、调试信息、处理完成
        $logs = $this->getLoggerLogs();
        $infoLogs = array_filter($logs, fn ($log) => 'info' === $log[0]);
        $debugLogs = array_filter($logs, fn ($log) => 'debug' === $log[0]);
        $this->assertGreaterThanOrEqual(1, count($infoLogs));
        $this->assertGreaterThanOrEqual(1, count($debugLogs));
    }

    public function testHandleMessageWithOnlyAccessKey(): void
    {
        $usageData = new AnthropicUsageData(200, 0, 0, 100);
        $message = new UsageCollectionMessage($usageData, 'ak_test_123', null, []);

        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyFinder->returnValues['ak_test_123'] = $accessKey;

        $this->handler->__invoke($message);

        // 应该只保存AccessKeyUsage
        $this->assertCount(1, $this->getSavedAccessKeyUsages());

        // 不应该保存UserUsage
        $this->assertCount(0, $this->getSavedUserUsages());
    }

    public function testHandleMessageWithOnlyUser(): void
    {
        $usageData = new AnthropicUsageData(150, 25, 0, 75);
        $message = new UsageCollectionMessage($usageData, null, 'user_test_456', []);

        $this->handler->__invoke($message);

        // 应该有事务回滚
        $rollbackCalls = array_filter($this->getTransactionCalls(), fn ($call) => 'rollback' === $call[0]);
        $this->assertCount(1, $rollbackCalls);

        // 不应该查找AccessKey
        $this->assertEmpty($this->getFindCalls());

        // 不应该保存AccessKeyUsage
        $this->assertCount(0, $this->getSavedAccessKeyUsages());

        // 不应该保存UserUsage，因为用户解析逻辑未完整实现
        $this->assertCount(0, $this->getSavedUserUsages());

        // 应该记录警告日志，因为没有实体被保存
        $warningLogs = array_filter($this->getLoggerLogs(), fn ($log) => 'warning' === $log[0]);
        $this->assertGreaterThanOrEqual(1, count($warningLogs));
    }

    public function testHandleMessageWithNeitherAccessKeyNorUser(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);
        $message = new UsageCollectionMessage($usageData, null, null, []);

        $this->handler->__invoke($message);

        // 由于没有实体保存，应该回滚
        $rollbackCalls = array_filter($this->getTransactionCalls(), fn ($call) => 'rollback' === $call[0]);
        $this->assertCount(1, $rollbackCalls);

        // 不应该查找AccessKey
        $this->assertEmpty($this->getFindCalls());

        // 不应该保存任何Usage实体
        $this->assertCount(0, $this->getSavedAccessKeyUsages());
        $this->assertCount(0, $this->getSavedUserUsages());

        // 应该记录警告日志
        $warningLogs = array_filter($this->getLoggerLogs(), fn ($log) => 'warning' === $log[0] && 'No entities to save for usage collection message' === $log[1]);
        $this->assertCount(1, $warningLogs);
    }

    public function testHandleMessageWithNonExistentAccessKey(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);
        $message = new UsageCollectionMessage($usageData, 'non_existent_key', 'user_test_456', []);

        // 应该抛出异常，而不是记录警告
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AccessKey not found: non_existent_key');

        $this->handler->__invoke($message);
    }

    public function testHandleMessageWithRepositoryException(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);
        $message = new UsageCollectionMessage($usageData, 'ak_test_123', 'user_test_456', []);

        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyFinder->returnValues['ak_test_123'] = $accessKey;
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyUsageRepository->shouldThrowException = true;
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyUsageRepository->exceptionMessage = 'Database error';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        try {
            $this->handler->__invoke($message);
        } catch (\RuntimeException $e) {
            // 应该有事务回滚
            $rollbackCalls = array_filter($this->getTransactionCalls(), fn ($call) => 'rollback' === $call[0]);
            $this->assertCount(1, $rollbackCalls);

            // 应该记录错误日志
            $errorLogs = array_filter($this->getLoggerLogs(), fn ($log) => 'error' === $log[0] && 'Failed to process usage collection message' === $log[1]);
            $this->assertCount(1, $errorLogs);
            $errorLogsArray = array_values($errorLogs);
            $this->assertSame('Database error', $errorLogsArray[0][2]['error']);

            throw $e;
        }
    }

    public function testHandleMessageSetsCorrectOccurTime(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);
        $message = new UsageCollectionMessage($usageData, 'ak_test_123', null, []);

        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyFinder->returnValues['ak_test_123'] = $accessKey;

        $beforeProcessing = new \DateTimeImmutable();

        $this->handler->__invoke($message);
        $savedUsages = $this->getSavedAccessKeyUsages();
        $this->assertCount(1, $savedUsages);
        $usage = $savedUsages[0];
        $this->assertGreaterThanOrEqual($beforeProcessing, $usage->getOccurTime());
    }

    public function testHandleMessageWithEmptyMetadata(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);
        $message = new UsageCollectionMessage($usageData, 'ak_test_123', null, []);

        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyFinder->returnValues['ak_test_123'] = $accessKey;

        $this->handler->__invoke($message);
        $savedUsages = $this->getSavedAccessKeyUsages();
        $this->assertCount(1, $savedUsages);
        $usage = $savedUsages[0];
        $this->assertNull($usage->getRequestId());
        $this->assertNull($usage->getModel());
        $this->assertNull($usage->getEndpoint());
        $this->assertNull($usage->getFeature());
    }

    public function testPerformanceRequirementForMessageProcessing(): void
    {
        $usageData = new AnthropicUsageData(100, 0, 0, 50);
        $message = new UsageCollectionMessage($usageData, 'ak_test_123', null, []);

        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };
        // @phpstan-ignore property.notFound, offsetAccess.nonOffsetAccessible
        $this->accessKeyFinder->returnValues['ak_test_123'] = $accessKey;

        $startTime = microtime(true);
        $this->handler->__invoke($message);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // 转换为毫秒

        // 验证消息处理延迟满足性能要求
        $this->assertLessThan(100, $executionTime, 'Message processing should complete within 100ms');
    }

    /**
     * @return list<AccessKeyUsage>
     */
    private function getSavedAccessKeyUsages(): array
    {
        // @phpstan-ignore property.notFound
        $entities = $this->accessKeyUsageRepository->savedEntities;
        /** @var list<AccessKeyUsage> $entities */
        return $entities;
    }

    /**
     * @return list<UserUsage>
     */
    private function getSavedUserUsages(): array
    {
        // @phpstan-ignore property.notFound
        $entities = $this->userUsageRepository->savedEntities;
        /** @var list<UserUsage> $entities */
        return $entities;
    }

    /**
     * @return list<array{0: string}>
     */
    private function getTransactionCalls(): array
    {
        // @phpstan-ignore property.notFound
        $calls = $this->entityManager->calls;
        /** @var list<array{0: string}> $calls */
        return $calls;
    }

    /**
     * @return list<array{0: mixed, 1: string|\Stringable, 2: array<mixed>}>
     */
    private function getLoggerLogs(): array
    {
        // @phpstan-ignore property.notFound
        $logs = $this->logger->logs;
        /** @var list<array{0: mixed, 1: string|\Stringable, 2: array<mixed>}> $logs */
        return $logs;
    }

    /**
     * @return list<int|string>
     */
    private function getFindCalls(): array
    {
        // @phpstan-ignore property.notFound
        $calls = $this->accessKeyFinder->findCalls;
        /** @var list<int|string> $calls */
        return $calls;
    }
}
