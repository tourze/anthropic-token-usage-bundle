<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\ExportJobResult;

/**
 * @internal
 */
#[CoversClass(ExportJobResult::class)]
final class ExportJobResultTest extends TestCase
{
    public function testConstructor(): void
    {
        $result = new ExportJobResult(
            jobId: 'job-123',
            status: 'completed',
            format: 'csv',
            filename: 'export.csv',
            totalRecords: 1000,
            processedRecords: 1000,
            downloadUrl: 'https://example.com/download',
            filePath: '/tmp/export.csv'
        );

        $this->assertSame('job-123', $result->jobId);
        $this->assertSame('completed', $result->status);
        $this->assertSame('csv', $result->format);
        $this->assertSame('export.csv', $result->filename);
        $this->assertSame(1000, $result->totalRecords);
        $this->assertSame(1000, $result->processedRecords);
        $this->assertSame('https://example.com/download', $result->downloadUrl);
        $this->assertSame('/tmp/export.csv', $result->filePath);
    }
}
