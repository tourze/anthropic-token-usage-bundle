<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\BatchProcessResult;

/**
 * BatchProcessResult 值对象单元测试
 * @internal
 */
#[CoversClass(BatchProcessResult::class)]
final class BatchProcessResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new BatchProcessResult(
            totalItems: 100,
            successCount: 95,
            failureCount: 5,
            errors: ['error1'],
            batchId: 'batch123'
        );

        $this->assertSame(100, $result->totalItems);
        $this->assertSame(95, $result->successCount);
        $this->assertSame(5, $result->failureCount);
        $this->assertSame(['error1'], $result->errors);
        $this->assertSame('batch123', $result->batchId);
    }

    public function testIsFullySuccessful(): void
    {
        $result = new BatchProcessResult(
            totalItems: 100,
            successCount: 100,
            failureCount: 0
        );

        $this->assertTrue($result->isFullySuccessful());
        $this->assertTrue($result->isSuccess());
    }

    public function testIsPartiallySuccessful(): void
    {
        $result = new BatchProcessResult(
            totalItems: 100,
            successCount: 50,
            failureCount: 50
        );

        $this->assertTrue($result->isPartiallySuccessful());
        $this->assertFalse($result->isFullySuccessful());
    }

    public function testIsCompletelyFailed(): void
    {
        $result = new BatchProcessResult(
            totalItems: 100,
            successCount: 0,
            failureCount: 100
        );

        $this->assertTrue($result->isCompletelyFailed());
    }

    public function testGetSuccessRate(): void
    {
        $result = new BatchProcessResult(
            totalItems: 100,
            successCount: 75,
            failureCount: 25
        );

        $this->assertSame(0.75, $result->getSuccessRate());
    }

    public function testToArray(): void
    {
        $result = new BatchProcessResult(
            totalItems: 100,
            successCount: 100,
            failureCount: 0,
            batchId: 'batch123'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('total_items', $array);
        $this->assertArrayHasKey('success_count', $array);
        $this->assertArrayHasKey('is_fully_successful', $array);
    }
}
