<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * 批量处理结果
 */
final readonly class BatchProcessResult
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        public int $totalItems,
        public int $successCount,
        public int $failureCount,
        public array $errors = [],
        public ?string $batchId = null,
    ) {
    }

    /**
     * 检查批处理是否成功（别名方法）
     */
    public function isSuccess(): bool
    {
        return $this->isFullySuccessful();
    }

    /**
     * 检查批处理是否完全成功
     */
    public function isFullySuccessful(): bool
    {
        return 0 === $this->failureCount && $this->successCount === $this->totalItems;
    }

    /**
     * 检查批处理是否部分成功
     */
    public function isPartiallySuccessful(): bool
    {
        return $this->successCount > 0 && $this->failureCount > 0;
    }

    /**
     * 检查批处理是否完全失败
     */
    public function isCompletelyFailed(): bool
    {
        return 0 === $this->successCount && $this->failureCount === $this->totalItems;
    }

    /**
     * 获取成功率
     */
    public function getSuccessRate(): float
    {
        return $this->totalItems > 0 ? $this->successCount / $this->totalItems : 0.0;
    }

    /**
     * 获取已处理项目数（别名方法）
     */
    public function getProcessedCount(): int
    {
        return $this->successCount;
    }

    /**
     * 获取失败项目数（别名方法）
     */
    public function getFailedCount(): int
    {
        return $this->failureCount;
    }

    /**
     * 获取失败信息（别名方法）
     *
     * @return array<string>
     */
    public function getFailures(): array
    {
        return $this->errors;
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_items' => $this->totalItems,
            'success_count' => $this->successCount,
            'failure_count' => $this->failureCount,
            'success_rate' => $this->getSuccessRate(),
            'errors' => $this->errors,
            'batch_id' => $this->batchId,
            'is_fully_successful' => $this->isFullySuccessful(),
            'is_partially_successful' => $this->isPartiallySuccessful(),
        ];
    }
}
