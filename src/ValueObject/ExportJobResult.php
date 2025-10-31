<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 导出作业结果
 *
 * 表示导出任务的执行结果和状态
 */
final readonly class ExportJobResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $jobId,
        #[Assert\Choice(choices: ['pending', 'processing', 'completed', 'failed', 'cancelled'])]
        public string $status,
        #[Assert\Choice(choices: ['csv', 'json', 'xlsx'])]
        public string $format,
        public string $filename,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $totalRecords,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $processedRecords,
        public ?string $downloadUrl = null,
        public ?string $filePath = null,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $fileSize = 0,
        public ?\DateTimeInterface $startTime = null,
        public ?\DateTimeInterface $completeTime = null,
        public ?\DateTimeInterface $expireTime = null,
        public ?string $errorMessage = null,
        #[Assert\Type(type: 'int')]
        #[Assert\Range(min: 0, max: 100)]
        public int $progressPercentage = 0,
        public array $metadata = [],
    ) {
    }

    /**
     * 检查作业是否正在进行中
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    /**
     * 检查作业是否已完成
     */
    public function isCompleted(): bool
    {
        return 'completed' === $this->status;
    }

    /**
     * 检查作业是否失败
     */
    public function isFailed(): bool
    {
        return 'failed' === $this->status;
    }

    /**
     * 检查作业是否被取消
     */
    public function isCancelled(): bool
    {
        return 'cancelled' === $this->status;
    }

    /**
     * 检查文件是否可供下载
     */
    public function isDownloadable(): bool
    {
        return $this->isCompleted()
            && null !== $this->downloadUrl
            && (null === $this->expireTime || $this->expireTime > new \DateTimeImmutable());
    }

    /**
     * 检查文件是否已过期
     */
    public function isExpired(): bool
    {
        return null !== $this->expireTime && $this->expireTime <= new \DateTimeImmutable();
    }

    /**
     * 获取执行时长（秒）
     */
    public function getExecutionDuration(): ?int
    {
        if (null === $this->startTime) {
            return null;
        }

        $endTime = $this->completeTime ?? new \DateTimeImmutable();

        return $endTime->getTimestamp() - $this->startTime->getTimestamp();
    }

    /**
     * 获取剩余有效时间（秒）
     */
    public function getRemainingTtl(): ?int
    {
        if (null === $this->expireTime) {
            return null;
        }

        $now = new \DateTimeImmutable();

        return max(0, $this->expireTime->getTimestamp() - $now->getTimestamp());
    }

    /**
     * 获取格式化的文件大小
     */
    public function getFormattedFileSize(): string
    {
        if (0 === $this->fileSize) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            ++$unitIndex;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * 获取处理速度（记录/秒）
     */
    public function getProcessingRate(): ?float
    {
        $duration = $this->getExecutionDuration();
        if (null === $duration || 0 === $duration || 0 === $this->processedRecords) {
            return null;
        }

        return $this->processedRecords / $duration;
    }

    /**
     * 获取预估剩余时间（秒）
     */
    public function getEstimatedRemainingTime(): ?int
    {
        if (!$this->isInProgress() || $this->progressPercentage >= 100) {
            return null;
        }

        $rate = $this->getProcessingRate();
        if (null === $rate) {
            return null;
        }

        $remainingRecords = $this->totalRecords - $this->processedRecords;

        return intval($remainingRecords / $rate);
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'status' => $this->status,
            'format' => $this->format,
            'filename' => $this->filename,
            'total_records' => $this->totalRecords,
            'processed_records' => $this->processedRecords,
            'progress_percentage' => $this->progressPercentage,
            'download_url' => $this->downloadUrl,
            'file_path' => $this->filePath,
            'file_size' => $this->fileSize,
            'formatted_file_size' => $this->getFormattedFileSize(),
            'started_at' => $this->startTime?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completeTime?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expireTime?->format('Y-m-d H:i:s'),
            'error_message' => $this->errorMessage,
            'flags' => [
                'is_in_progress' => $this->isInProgress(),
                'is_completed' => $this->isCompleted(),
                'is_failed' => $this->isFailed(),
                'is_cancelled' => $this->isCancelled(),
                'is_downloadable' => $this->isDownloadable(),
                'is_expired' => $this->isExpired(),
            ],
            'calculated_metrics' => [
                'execution_duration' => $this->getExecutionDuration(),
                'remaining_ttl' => $this->getRemainingTtl(),
                'processing_rate' => $this->getProcessingRate(),
                'estimated_remaining_time' => $this->getEstimatedRemainingTime(),
            ],
            'metadata' => $this->metadata,
        ];
    }
}
