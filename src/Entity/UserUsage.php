<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Repository\UserUsageRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * User维度的Anthropic Token使用记录
 */
#[ORM\Entity(repositoryClass: UserUsageRepository::class)]
#[ORM\Table(name: 'user_usage', options: ['comment' => 'User维度Token使用记录'])]
#[ORM\Index(name: 'user_usage_idx_user_occur', columns: ['user_id', 'occurTime'])]
#[ORM\Index(name: 'user_usage_idx_access_key_occur', columns: ['access_key_id', 'occurTime'])]
class UserUsage implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private UserInterface $user;

    #[ORM\ManyToOne(targetEntity: AccessKey::class)]
    #[ORM\JoinColumn(name: 'access_key_id', nullable: true, onDelete: 'SET NULL')]
    private ?AccessKey $accessKey = null;

    // Token消费明细 (与AccessKeyUsage保持一致)
    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0, 'comment' => '输入Token数量'])]
    private int $inputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0, 'comment' => '缓存创建输入Token数量'])]
    private int $cacheCreationInputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0, 'comment' => '缓存读取输入Token数量'])]
    private int $cacheReadInputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0, 'comment' => '输出Token数量'])]
    private int $outputTokens = 0;

    // 请求元数据
    #[Assert\Length(max: 64)]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '请求追踪ID'])]
    private ?string $requestId = null;

    #[Assert\Length(max: 50)]
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '使用的模型名称'])]
    private ?string $model = null;

    #[Assert\Length(max: 20)]
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, options: ['comment' => '停止原因'])]
    private ?string $stopReason = null;

    // 实际发生时间(考虑异步处理延迟)
    #[Assert\Type(type: \DateTimeInterface::class)]
    #[IndexColumn]
    #[ORM\Column(name: 'occurTime', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['comment' => '实际发生时间'])]
    private \DateTimeImmutable $occurTime;

    // 业务维度
    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '调用的API端点'])]
    private ?string $endpoint = null;

    #[Assert\Length(max: 50)]
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '功能标识'])]
    private ?string $feature = null;

    public function __construct()
    {
        $this->occurTime = new \DateTimeImmutable();
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getAccessKey(): ?AccessKey
    {
        return $this->accessKey;
    }

    public function setAccessKey(?AccessKey $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function setInputTokens(int $inputTokens): void
    {
        $this->inputTokens = $inputTokens;
    }

    public function getCacheCreationInputTokens(): int
    {
        return $this->cacheCreationInputTokens;
    }

    public function setCacheCreationInputTokens(int $cacheCreationInputTokens): void
    {
        $this->cacheCreationInputTokens = $cacheCreationInputTokens;
    }

    public function getCacheReadInputTokens(): int
    {
        return $this->cacheReadInputTokens;
    }

    public function setCacheReadInputTokens(int $cacheReadInputTokens): void
    {
        $this->cacheReadInputTokens = $cacheReadInputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }

    public function setOutputTokens(int $outputTokens): void
    {
        $this->outputTokens = $outputTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->cacheCreationInputTokens + $this->cacheReadInputTokens + $this->outputTokens;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): void
    {
        $this->model = $model;
    }

    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    public function setStopReason(?string $stopReason): void
    {
        $this->stopReason = $stopReason;
    }

    public function getOccurTime(): \DateTimeImmutable
    {
        return $this->occurTime;
    }

    public function setOccurTime(\DateTimeImmutable $occurTime): void
    {
        $this->occurTime = $occurTime;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    public function setFeature(?string $feature): void
    {
        $this->feature = $feature;
    }

    public function __toString(): string
    {
        $id = $this->getId();
        $idStr = $id ?? 'unknown';
        $userStr = isset($this->user) && method_exists($this->user, '__toString') ? (string) $this->user : (isset($this->user) ? $this->user::class : 'unknown');

        return sprintf(
            'UserUsage[%s] - %s tokens by user %s at %s',
            $idStr,
            $this->getTotalTokens(),
            $userStr,
            $this->occurTime->format('Y-m-d H:i:s')
        );
    }
}
