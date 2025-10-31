<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Top消费者查询结果
 *
 * 包含Top消费者列表及相关统计信息
 */
final readonly class TopConsumersResult
{
    /**
     * @param array<TopConsumerItem> $items
     * @param array<string, mixed> $summary
     */
    public function __construct(
        #[Assert\All(constraints: [
            new Assert\Type(type: TopConsumerItem::class),
        ])]
        public array $items,
        #[Assert\Choice(choices: ['access_key', 'user'])]
        public string $dimensionType,
        public \DateTimeInterface $startDate,
        public \DateTimeInterface $endDate,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalCount,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 1)]
        public int $limit,
        public array $summary = [],
    ) {
    }

    /**
     * 获取结果数量
     */
    public function getResultCount(): int
    {
        return count($this->items);
    }

    /**
     * 检查是否有更多结果
     */
    public function hasMoreResults(): bool
    {
        return $this->totalCount > $this->getResultCount();
    }

    /**
     * 获取Top消费者的总Token数
     */
    public function getTopConsumersTotalTokens(): int
    {
        return array_sum(array_map(fn ($item) => $item->getTotalTokens(), $this->items));
    }

    /**
     * 获取Top消费者的总请求数
     */
    public function getTopConsumersTotalRequests(): int
    {
        return array_sum(array_map(fn ($item) => $item->totalRequests, $this->items));
    }

    /**
     * 计算平均Token使用量
     */
    public function getAverageTokensPerConsumer(): float
    {
        $count = $this->getResultCount();

        return $count > 0 ? $this->getTopConsumersTotalTokens() / $count : 0.0;
    }

    /**
     * 计算平均请求数量
     */
    public function getAverageRequestsPerConsumer(): float
    {
        $count = $this->getResultCount();

        return $count > 0 ? $this->getTopConsumersTotalRequests() / $count : 0.0;
    }

    /**
     * 按Token使用量排序（降序）
     *
     * @return array<TopConsumerItem>
     */
    public function getSortedByTokens(): array
    {
        $sorted = $this->items;
        usort($sorted, fn ($a, $b) => $b->getTotalTokens() <=> $a->getTotalTokens());

        return $sorted;
    }

    /**
     * 按请求数量排序（降序）
     *
     * @return array<TopConsumerItem>
     */
    public function getSortedByRequests(): array
    {
        $sorted = $this->items;
        usort($sorted, fn ($a, $b) => $b->totalRequests <=> $a->totalRequests);

        return $sorted;
    }

    /**
     * 获取前N名消费者
     *
     * @return array<TopConsumerItem>
     */
    public function getTop(int $n): array
    {
        return array_slice($this->items, 0, $n);
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn ($item) => $item->toArray(), $this->items),
            'dimension_type' => $this->dimensionType,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate->format('Y-m-d H:i:s'),
            'total_count' => $this->totalCount,
            'result_count' => $this->getResultCount(),
            'limit' => $this->limit,
            'has_more_results' => $this->hasMoreResults(),
            'calculated_metrics' => [
                'top_consumers_total_tokens' => $this->getTopConsumersTotalTokens(),
                'top_consumers_total_requests' => $this->getTopConsumersTotalRequests(),
                'average_tokens_per_consumer' => $this->getAverageTokensPerConsumer(),
                'average_requests_per_consumer' => $this->getAverageRequestsPerConsumer(),
            ],
            'summary' => $this->summary,
        ];
    }
}
