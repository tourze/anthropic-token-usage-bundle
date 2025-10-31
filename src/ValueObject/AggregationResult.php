<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * 聚合结果
 */
final readonly class AggregationResult
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        public bool $success,
        public int $processedRecords,
        public int $updatedStatistics,
        public array $errors = [],
    ) {
    }
}
