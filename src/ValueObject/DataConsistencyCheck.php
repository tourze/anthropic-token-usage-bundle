<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 数据一致性检查项
 */
final readonly class DataConsistencyCheck
{
    public function __construct(
        public string $checkName,
        public string $description,
        #[Assert\Type(type: 'bool')]
        public bool $passing,
        public ?string $errorMessage = null,
        public string $severity = 'medium',
    ) {
    }

    public function isPassing(): bool
    {
        return $this->passing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check_name' => $this->checkName,
            'description' => $this->description,
            'passing' => $this->passing,
            'error_message' => $this->errorMessage,
            'severity' => $this->severity,
        ];
    }
}
