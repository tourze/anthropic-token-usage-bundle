<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * 聚合数据重建结果
 */
final readonly class RebuildResult
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        public bool $success,
        public int $rebuiltRecords,
        public int $deletedRecords,
        public string $dimensionType,
        public string $dimensionId,
        public array $errors = [],
    ) {
    }
}
