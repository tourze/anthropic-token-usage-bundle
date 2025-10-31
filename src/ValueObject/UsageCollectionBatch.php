<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;

/**
 * 批量Usage收集对象
 */
final readonly class UsageCollectionBatch
{
    /**
     * @param array<UsageCollectionItem> $items
     */
    public function __construct(
        public array $items,
    ) {
    }

    /**
     * 添加单个收集项
     *
     * @param array<string, mixed> $metadata
     */
    public function addItem(
        AnthropicUsageData $usageData,
        ?AccessKey $accessKey = null,
        ?UserInterface $user = null,
        array $metadata = [],
    ): self {
        $newItems = $this->items;
        $newItems[] = new UsageCollectionItem($usageData, $accessKey, $user, $metadata);

        return new self($newItems);
    }

    /**
     * 获取批次大小
     */
    public function getSize(): int
    {
        return count($this->items);
    }

    /**
     * 检查是否为空
     */
    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * 计算批次总Token数量
     */
    public function getTotalTokens(): int
    {
        return array_sum(array_map(
            fn (UsageCollectionItem $item) => $item->usageData->getTotalTokens(),
            $this->items
        ));
    }
}
