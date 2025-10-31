<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * AccessKeyUsage 实体单元测试
 * 测试重点：数据完整性、业务逻辑、序列化行为
 * @internal
 */
#[CoversClass(AccessKeyUsage::class)]
class AccessKeyUsageTest extends AbstractEntityTestCase
{
    private AccessKeyUsage $accessKeyUsage;

    private AccessKey $accessKey;

    private UserInterface $user;

    protected function setUp(): void
    {
        $this->accessKeyUsage = new AccessKeyUsage();
        $this->accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
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
                return 'test_user';
            }
        };
    }

    protected function createEntity(): AccessKeyUsage
    {
        return new AccessKeyUsage();
    }

    public static function propertiesProvider(): iterable
    {
        $accessKey = new class () extends AccessKey {
            public function getId(): string
            {
                return 'ak_test_123';
            }
        };

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

        return [
            ['accessKey', $accessKey],
            ['user', $user],
            ['inputTokens', 100],
            ['cacheCreationInputTokens', 25],
            ['cacheReadInputTokens', 15],
            ['outputTokens', 50],
            ['requestId', 'req_test_456'],
            ['model', 'claude-3-sonnet'],
            ['stopReason', 'end_turn'],
            ['endpoint', '/v1/messages'],
            ['feature', 'chat_completion'],
            ['occurTime', new \DateTimeImmutable('2024-01-15 10:30:00')],
        ];
    }

    public function testConstructorSetsOccurTimeToCurrentTime(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        $entity = new AccessKeyUsage();
        $afterCreation = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($beforeCreation, $entity->getOccurTime());
        $this->assertLessThanOrEqual($afterCreation, $entity->getOccurTime());
    }

    public function testAccessKeySetterAndGetter(): void
    {
        $this->accessKeyUsage->setAccessKey($this->accessKey);

        $this->assertSame($this->accessKey, $this->accessKeyUsage->getAccessKey());
    }

    public function testUserSetterAndGetter(): void
    {
        // 测试设置用户
        $this->accessKeyUsage->setUser($this->user);
        $this->assertSame($this->user, $this->accessKeyUsage->getUser());

        // 测试设置为null（匿名调用场景）
        $this->accessKeyUsage->setUser(null);
        $this->assertNull($this->accessKeyUsage->getUser());
    }

    #[TestWith([0, 0, 0, 0])]
    #[TestWith([100, 0, 0, 0])]
    #[TestWith([0, 0, 0, 50])]
    #[TestWith([0, 25, 0, 0])]
    #[TestWith([0, 0, 15, 0])]
    #[TestWith([100, 25, 15, 50])]
    #[TestWith([10000, 2500, 1500, 5000])]
    public function testTokenCountSettersAndGetters(
        int $inputTokens,
        int $cacheCreationTokens,
        int $cacheReadTokens,
        int $outputTokens,
    ): void {
        $this->accessKeyUsage->setInputTokens($inputTokens);
        $this->accessKeyUsage->setCacheCreationInputTokens($cacheCreationTokens);
        $this->accessKeyUsage->setCacheReadInputTokens($cacheReadTokens);
        $this->accessKeyUsage->setOutputTokens($outputTokens);

        $this->assertSame($inputTokens, $this->accessKeyUsage->getInputTokens());
        $this->assertSame($cacheCreationTokens, $this->accessKeyUsage->getCacheCreationInputTokens());
        $this->assertSame($cacheReadTokens, $this->accessKeyUsage->getCacheReadInputTokens());
        $this->assertSame($outputTokens, $this->accessKeyUsage->getOutputTokens());
    }

    public function testTotalTokensCalculation(): void
    {
        $this->accessKeyUsage->setInputTokens(100);
        $this->accessKeyUsage->setCacheCreationInputTokens(50);
        $this->accessKeyUsage->setCacheReadInputTokens(25);
        $this->accessKeyUsage->setOutputTokens(75);

        $this->assertSame(250, $this->accessKeyUsage->getTotalTokens());
    }

    public function testTotalTokensWithZeroValues(): void
    {
        // 验证默认值为0
        $this->assertSame(0, $this->accessKeyUsage->getTotalTokens());
    }

    public function testRequestMetadataSettersAndGetters(): void
    {
        $requestId = 'req_test_123';
        $model = 'claude-3-sonnet';
        $stopReason = 'end_turn';

        $this->accessKeyUsage->setRequestId($requestId);
        $this->accessKeyUsage->setModel($model);
        $this->accessKeyUsage->setStopReason($stopReason);

        $this->assertSame($requestId, $this->accessKeyUsage->getRequestId());
        $this->assertSame($model, $this->accessKeyUsage->getModel());
        $this->assertSame($stopReason, $this->accessKeyUsage->getStopReason());
    }

    public function testBusinessDimensionSettersAndGetters(): void
    {
        $endpoint = '/v1/messages';
        $feature = 'chat_completion';

        $this->accessKeyUsage->setEndpoint($endpoint);
        $this->accessKeyUsage->setFeature($feature);

        $this->assertSame($endpoint, $this->accessKeyUsage->getEndpoint());
        $this->assertSame($feature, $this->accessKeyUsage->getFeature());
    }

    public function testOccurTimeSetterAndGetter(): void
    {
        $customTime = new \DateTimeImmutable('2024-01-15 10:30:00');

        $this->accessKeyUsage->setOccurTime($customTime);

        $this->assertSame($customTime, $this->accessKeyUsage->getOccurTime());
    }

    public function testFluentInterface(): void
    {
        // 由于setter现在返回void而不是$this，测试setter不返回值
        $this->accessKeyUsage->setAccessKey($this->accessKey);
        $this->accessKeyUsage->setUser($this->user);
        $this->accessKeyUsage->setInputTokens(100);
        $this->accessKeyUsage->setOutputTokens(50);

        // 验证设置的值是否正确
        $this->assertSame($this->accessKey, $this->accessKeyUsage->getAccessKey());
        $this->assertSame($this->user, $this->accessKeyUsage->getUser());
        $this->assertSame(100, $this->accessKeyUsage->getInputTokens());
        $this->assertSame(50, $this->accessKeyUsage->getOutputTokens());
    }

    public function testToStringRepresentation(): void
    {
        // AccessKey getId() already returns 'ak_test_123' from anonymous class

        $occurTime = new \DateTimeImmutable('2024-01-15 10:30:00');

        $this->accessKeyUsage->setAccessKey($this->accessKey);
        $this->accessKeyUsage->setInputTokens(100);
        $this->accessKeyUsage->setOutputTokens(50);
        $this->accessKeyUsage->setOccurTime($occurTime);

        $string = (string) $this->accessKeyUsage;

        $this->assertStringContainsString('AccessKeyUsage[ak_test_123]', $string);
        $this->assertStringContainsString('150 tokens', $string);
        $this->assertStringContainsString('2024-01-15 10:30:00', $string);
    }
}
