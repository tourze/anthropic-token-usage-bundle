<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Repository\AccessKeyUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * AccessKeyUsageRepository 测试
 * @internal
 */
#[CoversClass(AccessKeyUsageRepository::class)]
#[RunTestsInSeparateProcesses]
final class AccessKeyUsageRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类可以重写此方法进行额外的设置
    }

    protected function createNewEntity(): AccessKeyUsage
    {
        // 查找或创建一个测试用的 AccessKey
        $entityManager = self::getEntityManager();
        $accessKey = $entityManager->getRepository(AccessKey::class)->findOneBy(['title' => 'Test Access Key']);

        if (null === $accessKey) {
            $accessKey = new AccessKey();
            $accessKey->setTitle('Test Access Key');
            $accessKey->setAppId('test-app-' . uniqid());
            $accessKey->setValid(true);
            $entityManager->persist($accessKey);
            $entityManager->flush();
        }

        $entity = new AccessKeyUsage();
        $entity->setAccessKey($accessKey);
        $entity->setModel('claude-3-sonnet-20240229');
        $entity->setFeature('chat');
        $entity->setInputTokens(100);
        $entity->setCacheCreationInputTokens(10);
        $entity->setCacheReadInputTokens(5);
        $entity->setOutputTokens(200);
        $entity->setOccurTime(new \DateTimeImmutable());

        return $entity;
    }

    protected function getRepository(): AccessKeyUsageRepository
    {
        return self::getService(AccessKeyUsageRepository::class);
    }

    public function testFindTopConsumers(): void
    {
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');
        $repository = $this->getRepository();

        // 创建测试数据
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $result = $repository->findTopConsumers($startDate, $endDate, 5);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(0, count($result));

        if (count($result) > 0) {
            $this->assertArrayHasKey('accessKey', $result[0]);
            $this->assertArrayHasKey('totalTokens', $result[0]);
            $this->assertArrayHasKey('totalRequests', $result[0]);
            $this->assertArrayHasKey('totalInputTokens', $result[0]);
            $this->assertArrayHasKey('totalOutputTokens', $result[0]);
            $this->assertArrayHasKey('firstUsageTime', $result[0]);
            $this->assertArrayHasKey('lastUsageTime', $result[0]);
        }
    }

    public function testGetLastUpdateTime(): void
    {
        $repository = $this->getRepository();

        // 创建一个实体并保存
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $result = $repository->getLastUpdateTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $result);
    }

    public function testGetLastUpdateTimeWithNoData(): void
    {
        $repository = $this->getRepository();

        // 清理所有数据
        foreach ($repository->findAll() as $entity) {
            $repository->remove($entity, true);
        }

        $result = $repository->getLastUpdateTime();

        $this->assertNull($result);
    }

    public function testFindByQuery(): void
    {
        $repository = $this->getRepository();

        // 创建测试数据
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $query = new UsageDetailQuery(
            page: 1,
            limit: 10,
            dimensionId: null,
            startDate: null,
            endDate: null,
            models: null,
            features: null
        );

        $result = $repository->findByQuery($query);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(0, count($result));
    }

    public function testCountByQuery(): void
    {
        $repository = $this->getRepository();

        // 创建测试数据
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $query = new UsageDetailQuery(
            page: 1,
            limit: 10,
            dimensionId: null,
            startDate: null,
            endDate: null,
            models: null,
            features: null
        );

        $result = $repository->countByQuery($query);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCalculateStatistics(): void
    {
        $repository = $this->getRepository();

        // 创建测试数据
        $entity = $this->createNewEntity();
        $accessKey = $entity->getAccessKey(); // 使用 createNewEntity 中创建的 accessKey
        $repository->save($entity, true);

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $result = $repository->calculateStatistics($accessKey, $startDate, $endDate);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_creation_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_read_input_tokens', $result);
        $this->assertArrayHasKey('total_output_tokens', $result);
        $this->assertArrayHasKey('total_requests', $result);

        $this->assertIsInt($result['total_input_tokens']);
        $this->assertIsInt($result['total_cache_creation_input_tokens']);
        $this->assertIsInt($result['total_cache_read_input_tokens']);
        $this->assertIsInt($result['total_output_tokens']);
        $this->assertIsInt($result['total_requests']);
    }

    public function testCountActiveEntities(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        // 创建测试数据
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $result = $repository->countActiveEntities($startDate, $endDate);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }
}
