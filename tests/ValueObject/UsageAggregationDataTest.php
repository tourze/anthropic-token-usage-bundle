<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageAggregationData;

/**
 * @internal
 */
#[CoversClass(UsageAggregationData::class)]
final class UsageAggregationDataTest extends TestCase
{
    public function testConstructor(): void
    {
        $data = new UsageAggregationData(
            dimensionId: 'test-dimension',
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 20,
            totalCacheReadInputTokens: 30,
            totalOutputTokens: 50,
            totalRequests: 5,
        );

        self::assertSame('test-dimension', $data->dimensionId);
        self::assertSame(100, $data->totalInputTokens);
        self::assertSame(20, $data->totalCacheCreationInputTokens);
        self::assertSame(30, $data->totalCacheReadInputTokens);
        self::assertSame(50, $data->totalOutputTokens);
        self::assertSame(5, $data->totalRequests);
    }

    public function testFromQueryResultWithAccessKeyId(): void
    {
        $result = [
            'accessKeyId' => 'access-key-123',
            'totalInputTokens' => '100',
            'totalCacheCreationInputTokens' => '20',
            'totalCacheReadInputTokens' => '30',
            'totalOutputTokens' => '50',
            'totalRequests' => '5',
        ];

        $data = UsageAggregationData::fromQueryResult($result);

        self::assertSame('access-key-123', $data->dimensionId);
        self::assertSame(100, $data->totalInputTokens);
        self::assertSame(20, $data->totalCacheCreationInputTokens);
        self::assertSame(30, $data->totalCacheReadInputTokens);
        self::assertSame(50, $data->totalOutputTokens);
        self::assertSame(5, $data->totalRequests);
    }

    public function testFromQueryResultWithUserId(): void
    {
        $result = [
            'userId' => 'user-456',
            'totalInputTokens' => 200,
            'totalCacheCreationInputTokens' => 40,
            'totalCacheReadInputTokens' => 60,
            'totalOutputTokens' => 100,
            'totalRequests' => 10,
        ];

        $data = UsageAggregationData::fromQueryResult($result);

        self::assertSame('user-456', $data->dimensionId);
        self::assertSame(200, $data->totalInputTokens);
        self::assertSame(40, $data->totalCacheCreationInputTokens);
        self::assertSame(60, $data->totalCacheReadInputTokens);
        self::assertSame(100, $data->totalOutputTokens);
        self::assertSame(10, $data->totalRequests);
    }

    public function testFromQueryResultWithMissingValues(): void
    {
        $result = [
            'accessKeyId' => 'access-key-789',
            // Missing some numeric fields to test default values
        ];

        $data = UsageAggregationData::fromQueryResult($result);

        self::assertSame('access-key-789', $data->dimensionId);
        self::assertSame(0, $data->totalInputTokens);
        self::assertSame(0, $data->totalCacheCreationInputTokens);
        self::assertSame(0, $data->totalCacheReadInputTokens);
        self::assertSame(0, $data->totalOutputTokens);
        self::assertSame(0, $data->totalRequests);
    }

    public function testFromQueryResultWithNonNumericValues(): void
    {
        $result = [
            'userId' => 'user-999',
            'totalInputTokens' => 'invalid',
            'totalCacheCreationInputTokens' => 'not-a-number',
            'totalCacheReadInputTokens' => null,
            'totalOutputTokens' => '',
            'totalRequests' => 'zero',
        ];

        $data = UsageAggregationData::fromQueryResult($result);

        self::assertSame('user-999', $data->dimensionId);
        self::assertSame(0, $data->totalInputTokens);
        self::assertSame(0, $data->totalCacheCreationInputTokens);
        self::assertSame(0, $data->totalCacheReadInputTokens);
        self::assertSame(0, $data->totalOutputTokens);
        self::assertSame(0, $data->totalRequests);
    }

    public function testGetTotalTokens(): void
    {
        $data = new UsageAggregationData(
            dimensionId: 'test',
            totalInputTokens: 100,
            totalCacheCreationInputTokens: 20,
            totalCacheReadInputTokens: 30,
            totalOutputTokens: 50,
            totalRequests: 5,
        );

        self::assertSame(200, $data->getTotalTokens()); // 100 + 20 + 30 + 50
    }

    public function testGetTotalTokensWithZeroValues(): void
    {
        $data = new UsageAggregationData(
            dimensionId: 'test',
            totalInputTokens: 0,
            totalCacheCreationInputTokens: 0,
            totalCacheReadInputTokens: 0,
            totalOutputTokens: 0,
            totalRequests: 0,
        );

        self::assertSame(0, $data->getTotalTokens());
    }
}