<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * UserUsage 实体单元测试
 * 测试重点：与AccessKeyUsage的行为一致性、用户维度的数据完整性
 * @internal
 */
#[CoversClass(UserUsage::class)]
class UserUsageTest extends AbstractEntityTestCase
{
    private UserUsage $userUsage;

    private UserInterface $user;

    private AccessKey $accessKey;

    protected function setUp(): void
    {
        $this->userUsage = new UserUsage();
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
                return 'test_user';
            }
        };
        // 使用反射设置 AccessKey ID，避免重写 final 方法
        $this->accessKey = new AccessKey();
        $reflection = new \ReflectionProperty(AccessKey::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($this->accessKey, 'ak_test_123');
    }

    protected function createEntity(): UserUsage
    {
        return new UserUsage();
    }

    public static function propertiesProvider(): iterable
    {
        $user = new class () implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'test_user';
            }
        };

        // 使用反射设置 AccessKey ID，避免重写 final 方法
        $accessKey = new AccessKey();
        $reflection = new \ReflectionProperty(AccessKey::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($accessKey, 'ak_test_123');

        return [
            ['user', $user],
            ['accessKey', $accessKey],
            ['inputTokens', 200],
            ['cacheCreationInputTokens', 50],
            ['cacheReadInputTokens', 30],
            ['outputTokens', 100],
            ['requestId', 'req_user_456'],
            ['model', 'claude-3-opus'],
            ['stopReason', 'max_tokens'],
            ['endpoint', '/v1/completions'],
            ['feature', 'text_generation'],
            ['occurTime', new \DateTimeImmutable('2024-02-20 15:45:30')],
        ];
    }

    public function testConstructorSetsOccurTimeToCurrentTime(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        $entity = new UserUsage();
        $afterCreation = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getOccurTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getOccurTime());
    }

    public function testUserSetterAndGetter(): void
    {
        $this->userUsage->setUser($this->user);

        $this->assertSame($this->user, $this->userUsage->getUser());
    }

    public function testAccessKeySetterAndGetter(): void
    {
        // 测试设置AccessKey
        $this->userUsage->setAccessKey($this->accessKey);
        $this->assertSame($this->accessKey, $this->userUsage->getAccessKey());

        // 测试设置为null（直接用户调用场景）
        $this->userUsage->setAccessKey(null);
        $this->assertNull($this->userUsage->getAccessKey());
    }

    #[TestWith([0, 0, 0, 0])]
    #[TestWith([200, 0, 0, 0])]
    #[TestWith([0, 0, 0, 100])]
    #[TestWith([0, 50, 0, 0])]
    #[TestWith([0, 0, 30, 0])]
    #[TestWith([200, 50, 30, 100])]
    #[TestWith([20000, 5000, 3000, 10000])]
    public function testTokenCountSettersAndGetters(
        int $inputTokens,
        int $cacheCreationTokens,
        int $cacheReadTokens,
        int $outputTokens,
    ): void {
        $this->userUsage->setInputTokens($inputTokens);
        $this->userUsage->setCacheCreationInputTokens($cacheCreationTokens);
        $this->userUsage->setCacheReadInputTokens($cacheReadTokens);
        $this->userUsage->setOutputTokens($outputTokens);

        $this->assertSame($inputTokens, $this->userUsage->getInputTokens());
        $this->assertSame($cacheCreationTokens, $this->userUsage->getCacheCreationInputTokens());
        $this->assertSame($cacheReadTokens, $this->userUsage->getCacheReadInputTokens());
        $this->assertSame($outputTokens, $this->userUsage->getOutputTokens());
    }

    public function testTotalTokensCalculation(): void
    {
        $this->userUsage->setInputTokens(200);
        $this->userUsage->setCacheCreationInputTokens(100);
        $this->userUsage->setCacheReadInputTokens(50);
        $this->userUsage->setOutputTokens(150);

        $this->assertSame(500, $this->userUsage->getTotalTokens());
    }

    public function testTotalTokensWithZeroValues(): void
    {
        // 验证默认值为0
        $this->assertSame(0, $this->userUsage->getTotalTokens());
    }

    public function testRequestMetadataSettersAndGetters(): void
    {
        $requestId = 'req_user_456';
        $model = 'claude-3-opus';
        $stopReason = 'max_tokens';

        $this->userUsage->setRequestId($requestId);
        $this->userUsage->setModel($model);
        $this->userUsage->setStopReason($stopReason);

        $this->assertSame($requestId, $this->userUsage->getRequestId());
        $this->assertSame($model, $this->userUsage->getModel());
        $this->assertSame($stopReason, $this->userUsage->getStopReason());
    }

    public function testBusinessDimensionSettersAndGetters(): void
    {
        $endpoint = '/v1/completions';
        $feature = 'text_generation';

        $this->userUsage->setEndpoint($endpoint);
        $this->userUsage->setFeature($feature);

        $this->assertSame($endpoint, $this->userUsage->getEndpoint());
        $this->assertSame($feature, $this->userUsage->getFeature());
    }

    public function testOccurTimeSetterAndGetter(): void
    {
        $customTime = new \DateTimeImmutable('2024-02-20 15:45:30');

        $this->userUsage->setOccurTime($customTime);

        $this->assertSame($customTime, $this->userUsage->getOccurTime());
    }

    public function testFluentInterface(): void
    {
        // 由于setter现在返回void而不是$this，测试setter不返回值
        $this->userUsage->setUser($this->user);
        $this->userUsage->setAccessKey($this->accessKey);
        $this->userUsage->setInputTokens(200);
        $this->userUsage->setOutputTokens(100);

        // 验证设置的值是否正确
        $this->assertSame($this->user, $this->userUsage->getUser());
        $this->assertSame($this->accessKey, $this->userUsage->getAccessKey());
        $this->assertSame(200, $this->userUsage->getInputTokens());
        $this->assertSame(100, $this->userUsage->getOutputTokens());
    }

    public function testToStringRepresentation(): void
    {
        $occurTime = new \DateTimeImmutable('2024-02-20 15:45:30');

        $this->userUsage->setUser($this->user);
        $this->userUsage->setInputTokens(200);
        $this->userUsage->setOutputTokens(100);
        $this->userUsage->setOccurTime($occurTime);

        $string = (string) $this->userUsage;

        // 验证字符串包含基本信息
        $this->assertStringContainsString('UserUsage[', $string);
        $this->assertStringContainsString('300 tokens', $string);
        $this->assertStringContainsString('2024-02-20 15:45:30', $string);
    }

    /**
     * 验证UserUsage与AccessKeyUsage行为一致性的测试
     */
    public function testBehaviorConsistencyWithAccessKeyUsage(): void
    {
        $inputTokens = 150;
        $cacheCreationTokens = 75;
        $cacheReadTokens = 25;
        $outputTokens = 100;

        $this->userUsage->setInputTokens($inputTokens);
        $this->userUsage->setCacheCreationInputTokens($cacheCreationTokens);
        $this->userUsage->setCacheReadInputTokens($cacheReadTokens);
        $this->userUsage->setOutputTokens($outputTokens);

        // 验证总token计算与AccessKeyUsage一致
        $expectedTotal = $inputTokens + $cacheCreationTokens + $cacheReadTokens + $outputTokens;
        $this->assertSame($expectedTotal, $this->userUsage->getTotalTokens());
    }
}
