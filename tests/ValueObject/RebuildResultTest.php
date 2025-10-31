<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\RebuildResult;

/**
 * RebuildResult 值对象单元测试
 * @internal
 */
#[CoversClass(RebuildResult::class)]
final class RebuildResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new RebuildResult(
            success: true,
            rebuiltRecords: 100,
            deletedRecords: 10,
            dimensionType: 'user',
            dimensionId: 'user123',
            errors: []
        );

        $this->assertTrue($result->success);
        $this->assertSame(100, $result->rebuiltRecords);
        $this->assertSame(10, $result->deletedRecords);
        $this->assertSame('user', $result->dimensionType);
        $this->assertSame('user123', $result->dimensionId);
        $this->assertSame([], $result->errors);
    }

    public function testConstructorWithErrors(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $result = new RebuildResult(
            success: false,
            rebuiltRecords: 0,
            deletedRecords: 0,
            dimensionType: 'access_key',
            dimensionId: 'key123',
            errors: $errors
        );

        $this->assertFalse($result->success);
        $this->assertSame($errors, $result->errors);
    }
}
