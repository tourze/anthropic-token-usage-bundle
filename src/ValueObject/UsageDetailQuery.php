<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AccessKeyBundle\Entity\AccessKey;

/**
 * Usage详情查询参数
 */
final readonly class UsageDetailQuery
{
    public function __construct(
        public ?AccessKey $accessKey = null,
        public ?UserInterface $user = null,
        public ?\DateTimeInterface $startDate = null,
        public ?\DateTimeInterface $endDate = null,
        #[Assert\Length(max: 50)]
        public ?string $model = null,
        #[Assert\Length(max: 50)]
        public ?string $feature = null,
        #[Assert\Length(max: 64)]
        public ?string $dimensionId = null,

        /** @var string[]|null */
        public ?array $models = null,

        /** @var string[]|null */
        public ?array $features = null,
        #[Assert\Length(max: 64)]
        public ?string $requestId = null,
        #[Assert\Type(type: 'int')]
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 1, max: 1000)]
        public int $limit = 20,
        #[Assert\Choice(choices: ['occurTime', 'createdAt', 'total_tokens'])]
        public string $orderBy = 'occurTime',
        #[Assert\Choice(choices: ['ASC', 'DESC'])]
        public string $orderDirection = 'DESC',
    ) {
    }

    /**
     * 计算查询偏移量
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * 检查是否有AccessKey过滤
     */
    public function hasAccessKeyFilter(): bool
    {
        return null !== $this->accessKey;
    }

    /**
     * 检查是否有User过滤
     */
    public function hasUserFilter(): bool
    {
        return null !== $this->user;
    }

    /**
     * 检查是否有日期范围过滤
     */
    public function hasDateRangeFilter(): bool
    {
        return null !== $this->startDate || null !== $this->endDate;
    }

    /**
     * 检查是否有dimensionId过滤
     */
    public function hasDimensionIdFilter(): bool
    {
        return null !== $this->dimensionId;
    }

    /**
     * 检查是否有models过滤
     */
    public function hasModelsFilter(): bool
    {
        return null !== $this->models && [] !== $this->models;
    }

    /**
     * 检查是否有features过滤
     */
    public function hasFeaturesFilter(): bool
    {
        return null !== $this->features && [] !== $this->features;
    }

    /**
     * 转换为数组格式，用于日志记录
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'accessKey' => $this->accessKey?->getId(),
            'user' => $this->user?->getUserIdentifier(),
            'startDate' => $this->startDate?->format('Y-m-d H:i:s'),
            'endDate' => $this->endDate?->format('Y-m-d H:i:s'),
            'model' => $this->model,
            'feature' => $this->feature,
            'dimensionId' => $this->dimensionId,
            'models' => $this->models,
            'features' => $this->features,
            'requestId' => $this->requestId,
            'page' => $this->page,
            'limit' => $this->limit,
            'orderBy' => $this->orderBy,
            'orderDirection' => $this->orderDirection,
        ];
    }
}
