<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\DataConsistencyCheck;

/**
 * @internal
 */
#[CoversClass(DataConsistencyCheck::class)]
final class DataConsistencyCheckTest extends TestCase
{
    public function testConstructor(): void
    {
        $check = new DataConsistencyCheck(
            checkName: 'orphan_check',
            description: 'Check for orphaned records',
            passing: true,
            errorMessage: null,
            severity: 'medium'
        );

        $this->assertSame('orphan_check', $check->checkName);
        $this->assertSame('Check for orphaned records', $check->description);
        $this->assertTrue($check->passing);
        $this->assertNull($check->errorMessage);
        $this->assertSame('medium', $check->severity);
    }
}
