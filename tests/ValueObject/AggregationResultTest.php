<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AggregationResult;

/**
 * AggregationResult 值对象单元测试
 * @internal
 */
#[CoversClass(AggregationResult::class)]
final class AggregationResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new AggregationResult(
            success: true,
            processedRecords: 100,
            updatedStatistics: 50,
            errors: []
        );

        $this->assertTrue($result->success);
        $this->assertSame(100, $result->processedRecords);
        $this->assertSame(50, $result->updatedStatistics);
        $this->assertSame([], $result->errors);
    }

    public function testConstructorWithErrors(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $result = new AggregationResult(
            success: false,
            processedRecords: 0,
            updatedStatistics: 0,
            errors: $errors
        );

        $this->assertFalse($result->success);
        $this->assertSame($errors, $result->errors);
    }
}
