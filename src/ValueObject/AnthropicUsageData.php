<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Anthropic API使用数据的值对象
 */
final readonly class AnthropicUsageData
{
    public function __construct(
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $inputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $cacheCreationInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $cacheReadInputTokens,
        #[Assert\Type(type: 'int')]
        #[Assert\PositiveOrZero()]
        public int $outputTokens,
    ) {
    }

    /**
     * 从Anthropic API响应中创建UsageData对象
     *
     * @param array<string, mixed> $apiResponse
     */
    public static function fromApiResponse(array $apiResponse): self
    {
        $usage = $apiResponse['usage'] ?? [];
        assert(is_array($usage));
        /** @var array<string, mixed> $usage */

        return new self(
            self::extractIntValue($usage, 'input_tokens'),
            self::extractIntValue($usage, 'cache_creation_input_tokens'),
            self::extractIntValue($usage, 'cache_read_input_tokens'),
            self::extractIntValue($usage, 'output_tokens')
        );
    }

    /**
     * 从数组中提取整数值，处理 null 和缺失的情况
     *
     * @param array<string, mixed> $data
     */
    private static function extractIntValue(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * 从Anthropic API的usage对象中直接创建
     *
     * @param array<string, mixed> $usage
     */
    public static function fromUsageArray(array $usage): self
    {
        return new self(
            self::extractIntValue($usage, 'input_tokens'),
            self::extractIntValue($usage, 'cache_creation_input_tokens'),
            self::extractIntValue($usage, 'cache_read_input_tokens'),
            self::extractIntValue($usage, 'output_tokens')
        );
    }

    /**
     * 计算总Token数量
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->cacheCreationInputTokens
             + $this->cacheReadInputTokens + $this->outputTokens;
    }

    /**
     * 检查是否为空Usage（所有token都为0）
     */
    public function isEmpty(): bool
    {
        return 0 === $this->getTotalTokens();
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'cache_creation_input_tokens' => $this->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->cacheReadInputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
        ];
    }

    /**
     * 创建一个空的UsageData对象
     */
    public static function empty(): self
    {
        return new self(0, 0, 0, 0);
    }

    public function __toString(): string
    {
        return sprintf(
            'AnthropicUsageData[total: %d, input: %d, cache_creation: %d, cache_read: %d, output: %d]',
            $this->getTotalTokens(),
            $this->inputTokens,
            $this->cacheCreationInputTokens,
            $this->cacheReadInputTokens,
            $this->outputTokens
        );
    }
}
