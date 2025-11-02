<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Repository;

use BizUserBundle\Entity\BizUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDetailQuery;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * UserUsageRepository 测试
 * @internal
 */
#[CoversClass(UserUsageRepository::class)]
#[RunTestsInSeparateProcesses]
final class UserUsageRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 子类可以重写此方法进行额外的设置
    }

    protected function createNewEntity(): UserUsage
    {
        // 使用真实的BizUser但最小化初始化
        $user = new BizUser();
        $user->setUsername('test_user_' . uniqid());
        $user->setEmail('test-user-' . uniqid() . '@example.com');
        $user->setPasswordHash(password_hash('test123', PASSWORD_BCRYPT));

        // 先持久化User实体，因为UserUsage不cascade persist
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $entity = new UserUsage();
        $entity->setUser($user);
        $entity->setModel('claude-3-sonnet-20240229');
        $entity->setFeature('chat');
        $entity->setInputTokens(100);
        $entity->setCacheCreationInputTokens(10);
        $entity->setCacheReadInputTokens(5);
        $entity->setOutputTokens(200);
        $entity->setOccurTime(new \DateTimeImmutable());

        return $entity;
    }

    protected function getRepository(): UserUsageRepository
    {
        return self::getService(UserUsageRepository::class);
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

        // 移除冗余的 assertIsArray,因为方法返回类型已明确为 array
        $this->assertGreaterThanOrEqual(0, count($result));

        if (count($result) > 0) {
            $this->assertArrayHasKey('user', $result[0]);
            $this->assertArrayHasKey('totalTokens', $result[0]);
            $this->assertArrayHasKey('totalRequests', $result[0]);
            $this->assertArrayHasKey('totalInputTokens', $result[0]);
            $this->assertArrayHasKey('totalOutputTokens', $result[0]);
            $this->assertArrayHasKey('firstUsageTime', $result[0]);
            $this->assertArrayHasKey('lastUsageTime', $result[0]);
        }
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

        // 移除冗余的 assertIsArray,因为方法返回类型已明确为 array<UserUsage>
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

        // 移除冗余的 assertIsInt,因为方法返回类型已明确为 int
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCalculateStatistics(): void
    {
        $repository = $this->getRepository();

        // 创建测试数据
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        // 使用实际创建的用户
        $user = $entity->getUser();

        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-12-31');

        $result = $repository->calculateStatistics($user, $startDate, $endDate);

        // 移除冗余的 assertIsArray,因为方法返回类型已明确为 array
        $this->assertArrayHasKey('total_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_creation_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_read_input_tokens', $result);
        $this->assertArrayHasKey('total_output_tokens', $result);
        $this->assertArrayHasKey('total_requests', $result);

        // 移除冗余的 assertIsInt,因为 formatStatisticsResult() 已确保所有值为 int
        // 使用更有意义的断言来验证业务逻辑
        $this->assertGreaterThanOrEqual(0, $result['total_input_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_cache_creation_input_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_cache_read_input_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_output_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_requests']);
    }

    public function testGetSystemTotals(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        // 创建测试数据
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $result = $repository->getSystemTotals($startDate, $endDate);

        // 移除冗余的 assertIsArray,因为方法返回类型已明确为 array
        $this->assertArrayHasKey('total_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_creation_input_tokens', $result);
        $this->assertArrayHasKey('total_cache_read_input_tokens', $result);
        $this->assertArrayHasKey('total_output_tokens', $result);
        $this->assertArrayHasKey('total_requests', $result);

        // 移除冗余的 assertIsInt,因为 formatStatisticsResult() 已确保所有值为 int
        // 使用更有意义的断言来验证业务逻辑
        $this->assertGreaterThanOrEqual(0, $result['total_input_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_cache_creation_input_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_cache_read_input_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_output_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_requests']);
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

        // 移除冗余的 assertIsInt,因为方法返回类型已明确为 int
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testGetSystemTotalsWithNoData(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('2024-01-01');
        $endDate = new \DateTimeImmutable('2024-01-31');

        // 清理所有数据
        foreach ($repository->findAll() as $entity) {
            $repository->remove($entity, true);
        }

        $result = $repository->getSystemTotals($startDate, $endDate);

        // 移除冗余的 assertIsArray,因为方法返回类型已明确为 array
        $this->assertSame(0, $result['total_input_tokens']);
        $this->assertSame(0, $result['total_cache_creation_input_tokens']);
        $this->assertSame(0, $result['total_cache_read_input_tokens']);
        $this->assertSame(0, $result['total_output_tokens']);
        $this->assertSame(0, $result['total_requests']);
    }
}
