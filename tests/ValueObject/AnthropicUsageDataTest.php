<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Unit\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

/**
 * AnthropicUsageData 值对象单元测试
 * 测试重点：数据封装、不变性、API响应解析
 * @internal
 */
#[CoversClass(AnthropicUsageData::class)]
class AnthropicUsageDataTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $inputTokens = 100;
        $cacheCreationTokens = 50;
        $cacheReadTokens = 25;
        $outputTokens = 75;

        $usageData = new AnthropicUsageData(
            $inputTokens,
            $cacheCreationTokens,
            $cacheReadTokens,
            $outputTokens
        );

        $this->assertSame($inputTokens, $usageData->inputTokens);
        $this->assertSame($cacheCreationTokens, $usageData->cacheCreationInputTokens);
        $this->assertSame($cacheReadTokens, $usageData->cacheReadInputTokens);
        $this->assertSame($outputTokens, $usageData->outputTokens);
    }

    public function testPropertiesAreReadonly(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);

        // 使用反射验证readonly属性
        $reflection = new \ReflectionClass($usageData);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    #[TestWith([
        [
            'id' => 'msg_test_001',
            'usage' => [
                'input_tokens' => 100,
                'cache_creation_input_tokens' => 50,
                'cache_read_input_tokens' => 25,
                'output_tokens' => 75,
            ],
        ],
        [
            'input_tokens' => 100,
            'cache_creation_input_tokens' => 50,
            'cache_read_input_tokens' => 25,
            'output_tokens' => 75,
        ],
    ])]
    #[TestWith([
        [
            'usage' => [
                'input_tokens' => 200,
                'output_tokens' => 150,
            ],
        ],
        [
            'input_tokens' => 200,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'output_tokens' => 150,
        ],
    ])]
    #[TestWith([
        [
            'usage' => [
                'input_tokens' => 300,
                'cache_creation_input_tokens' => 100,
                'cache_read_input_tokens' => 200,
                'output_tokens' => 250,
            ],
        ],
        [
            'input_tokens' => 300,
            'cache_creation_input_tokens' => 100,
            'cache_read_input_tokens' => 200,
            'output_tokens' => 250,
        ],
    ])]
    #[TestWith([
        [
            'usage' => [
                'input_tokens' => 0,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
                'output_tokens' => 0,
            ],
        ],
        [
            'input_tokens' => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'output_tokens' => 0,
        ],
    ])]
    public function testFromApiResponseParsingValidData(mixed $apiResponse, mixed $expectedValues): void
    {
        $this->assertIsArray($apiResponse);
        $this->assertIsArray($expectedValues);

        /** @var array<string, mixed> $apiResponse */
        /** @var array<string, int> $expectedValues */
        $usageData = AnthropicUsageData::fromApiResponse($apiResponse);

        $this->assertSame($expectedValues['input_tokens'], $usageData->inputTokens);
        $this->assertSame($expectedValues['cache_creation_input_tokens'], $usageData->cacheCreationInputTokens);
        $this->assertSame($expectedValues['cache_read_input_tokens'], $usageData->cacheReadInputTokens);
        $this->assertSame($expectedValues['output_tokens'], $usageData->outputTokens);
    }

    public function testFromApiResponseWithMissingUsageSection(): void
    {
        $apiResponse = [
            'id' => 'msg_test',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Hello!']],
        ];

        $usageData = AnthropicUsageData::fromApiResponse($apiResponse);

        $this->assertSame(0, $usageData->inputTokens);
        $this->assertSame(0, $usageData->cacheCreationInputTokens);
        $this->assertSame(0, $usageData->cacheReadInputTokens);
        $this->assertSame(0, $usageData->outputTokens);
    }

    public function testFromApiResponseWithEmptyUsageSection(): void
    {
        $apiResponse = [
            'id' => 'msg_test',
            'usage' => [],
        ];

        $usageData = AnthropicUsageData::fromApiResponse($apiResponse);

        $this->assertSame(0, $usageData->inputTokens);
        $this->assertSame(0, $usageData->cacheCreationInputTokens);
        $this->assertSame(0, $usageData->cacheReadInputTokens);
        $this->assertSame(0, $usageData->outputTokens);
    }

    public function testFromApiResponseWithPartialUsageData(): void
    {
        $apiResponse = [
            'usage' => [
                'input_tokens' => 150,
                'output_tokens' => 75,
                // 缺少 cache_* 字段
            ],
        ];

        $usageData = AnthropicUsageData::fromApiResponse($apiResponse);

        $this->assertSame(150, $usageData->inputTokens);
        $this->assertSame(0, $usageData->cacheCreationInputTokens);
        $this->assertSame(0, $usageData->cacheReadInputTokens);
        $this->assertSame(75, $usageData->outputTokens);
    }

    public function testFromApiResponseWithNullValues(): void
    {
        $apiResponse = [
            'usage' => [
                'input_tokens' => null,
                'cache_creation_input_tokens' => null,
                'cache_read_input_tokens' => null,
                'output_tokens' => null,
            ],
        ];

        $usageData = AnthropicUsageData::fromApiResponse($apiResponse);

        $this->assertSame(0, $usageData->inputTokens);
        $this->assertSame(0, $usageData->cacheCreationInputTokens);
        $this->assertSame(0, $usageData->cacheReadInputTokens);
        $this->assertSame(0, $usageData->outputTokens);
    }

    public function testValueObjectImmutability(): void
    {
        $usageData = new AnthropicUsageData(100, 50, 25, 75);

        // 验证值对象是不可变的 - readonly属性不能被修改
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');
        // 通过反射尝试修改readonly属性
        $reflection = new \ReflectionClass($usageData);
        $property = $reflection->getProperty('inputTokens');
        $property->setAccessible(true);
        $property->setValue($usageData, 200);
    }

    public function testZeroValuesAreValid(): void
    {
        $usageData = new AnthropicUsageData(0, 0, 0, 0);

        $this->assertSame(0, $usageData->inputTokens);
        $this->assertSame(0, $usageData->cacheCreationInputTokens);
        $this->assertSame(0, $usageData->cacheReadInputTokens);
        $this->assertSame(0, $usageData->outputTokens);
    }

    public function testLargeValuesAreHandledCorrectly(): void
    {
        $largeValue = 999999;
        $usageData = new AnthropicUsageData($largeValue, $largeValue, $largeValue, $largeValue);

        $this->assertSame($largeValue, $usageData->inputTokens);
        $this->assertSame($largeValue, $usageData->cacheCreationInputTokens);
        $this->assertSame($largeValue, $usageData->cacheReadInputTokens);
        $this->assertSame($largeValue, $usageData->outputTokens);
    }
}
