<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * 分页的Usage详情结果
 */
final readonly class PaginatedUsageDetailResult
{
    /**
     * @param array<mixed> $items
     */
    public function __construct(
        public array $items,
        public int $totalCount,
        public int $page,
        public int $limit,
    ) {
    }

    /**
     * 获取总页数
     */
    public function getTotalPages(): int
    {
        return (int) ceil($this->totalCount / $this->limit);
    }

    /**
     * 检查是否有下一页
     */
    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }

    /**
     * 检查是否有上一页
     */
    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }
}
