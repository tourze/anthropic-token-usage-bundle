# Anthropic Token Usage Bundle

[中文](README.zh-CN.md) | [English](README.md)

A Symfony bundle for tracking and analyzing Anthropic API token usage with comprehensive statistics, admin interface, and data aggregation capabilities.

## Features

- **Token Usage Tracking**: Track input/output tokens, cache usage, and request counts
- **Multi-dimensional Analysis**: Support for both AccessKey and User-based usage tracking
- **Data Aggregation**: Pre-aggregated statistics by hour, day, and month
- **Admin Interface**: EasyAdmin integration for browsing usage data
- **Async Processing**: Symfony Messenger integration for high-performance usage collection
- **Batch Processing**: Support for bulk usage data collection
- **Comprehensive Metrics**: Cache creation/read tokens, request details, and more
- **Data Quality Monitoring**: Built-in metrics for data consistency and completeness

## Installation

```bash
composer require tourze/anthropic-token-usage-bundle
```

## Configuration

### 1. Enable the Bundle

Add the bundle to your `bundles.php`:

```php
return [
    // ...
    Tourze\AnthropicTokenUsageBundle\AnthropicTokenUsageBundle::class => ['all' => true],
];
```

### 2. Database Schema

The bundle will automatically create the following database tables:

- `access_key_usage`: Individual AccessKey usage records
- `user_usage`: Individual user usage records
- `usage_statistics`: Pre-aggregated statistics

### 3. Configure Messenger (Optional)

For async usage collection, configure Symfony Messenger:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            'Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage': async
```

## Usage

### Basic Usage Collection

```php
<?php

use Tourze\AnthropicTokenUsageBundle\Service\UsageCollector;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

class AnthropicService
{
    public function __construct(
        private UsageCollector $usageCollector
    ) {}

    public function processAnthropicRequest(array $response, ?AccessKey $accessKey = null, ?UserInterface $user = null)
    {
        // Create usage data from API response
        $usageData = new AnthropicUsageData(
            inputTokens: $response['usage']['input_tokens'] ?? 0,
            outputTokens: $response['usage']['output_tokens'] ?? 0,
            cacheCreationInputTokens: $response['usage']['cache_creation_input_tokens'] ?? 0,
            cacheReadInputTokens: $response['usage']['cache_read_input_tokens'] ?? 0
        );

        // Collect usage with metadata
        $this->usageCollector->collectUsage(
            $usageData,
            $accessKey,
            $user,
            [
                'model' => $response['model'],
                'request_id' => $response['id'],
                'feature' => 'chat_completion',
                'endpoint' => '/api/v1/messages'
            ]
        );
    }
}
```

### Batch Usage Collection

```php
<?php

use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionItem;

$batch = new UsageCollectionBatch([
    new UsageCollectionItem($usageData1, $accessKey1, $user1, $metadata1),
    new UsageCollectionItem($usageData2, $accessKey2, $user2, $metadata2),
    // ... more items
]);

$result = $this->usageCollector->collectBatchUsage($batch);

echo "Processed {$result->totalItems} items\n";
echo "Success: {$result->successCount}, Failures: {$result->failureCount}\n";
```

### Querying Usage Statistics

```php
<?php

use Tourze\AnthropicTokenUsageBundle\Service\UsageQueryService;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageStatisticsPeriod;

class UsageAnalyticsService
{
    public function __construct(
        private UsageQueryService $queryService
    ) {}

    public function getMonthlyUsageStats(string $accessKeyId): array
    {
        $filter = new UsageQueryFilter(
            dimensionType: UsageStatistics::DIMENSION_ACCESS_KEY,
            dimensionId: $accessKeyId,
            periodType: UsageStatistics::PERIOD_MONTH,
            startDate: new DateTimeImmutable('-6 months'),
            endDate: new DateTimeImmutable()
        );

        return $this->queryService->getUsageStatistics($filter);
    }

    public function getTopConsumers(int $limit = 10): array
    {
        return $this->queryService->getTopConsumers(
            dimensionType: UsageStatistics::DIMENSION_ACCESS_KEY,
            periodType: UsageStatistics::PERIOD_DAY,
            limit: $limit
        );
    }
}
```

## Data Aggregation

The bundle includes automatic data aggregation to optimize query performance:

```php
<?php

use Tourze\AnthropicTokenUsageBundle\Service\UsageAggregateService;

// Trigger manual aggregation
$this->aggregateService->aggregateAccessKeyUsage(
    new DateTimeImmutable('-1 day'),
    new DateTimeImmutable()
);

$this->aggregateService->aggregateUserUsage(
    new DateTimeImmutable('-1 day'),
    new DateTimeImmutable()
);
```

## Admin Interface

The bundle provides EasyAdmin controllers for managing usage data:

- **AccessKey Usage**: Browse and filter AccessKey usage records
- **User Usage**: Browse and filter user usage records
- **Usage Statistics**: View aggregated statistics and trends

Access the admin interface at `/admin` (or your configured EasyAdmin path).

## Value Objects

The bundle uses several value objects for type-safe data handling:

### AnthropicUsageData

```php
$usageData = new AnthropicUsageData(
    inputTokens: 1000,
    outputTokens: 500,
    cacheCreationInputTokens: 100,
    cacheReadInputTokens: 50
);

echo $usageData->getTotalTokens(); // 1650
```

### UsageStatisticsResult

```php
$result = $queryService->getUsageStatistics($filter);

foreach ($result->items as $stat) {
    echo sprintf(
        "Period: %s to %s\nTokens: %d\nRequests: %d\n",
        $stat->periodStart->format('Y-m-d H:i'),
        $stat->periodEnd->format('Y-m-d H:i'),
        $stat->getTotalTokens(),
        $stat->totalRequests
    );
}
```

## Configuration Options

The bundle supports various configuration options:

```yaml
# config/packages/anthropic_token_usage.yaml
anthropic_token_usage:
    # Enable/disable async processing
    async_processing: true

    # Batch size for message processing
    batch_size: 100

    # Retention period for raw usage data (days)
    retention_days: 90

    # Enable data quality metrics
    enable_quality_metrics: true
```

## Monitoring and Logging

The bundle includes comprehensive logging for monitoring:

```php
// Log channels
'monolog.channels' => [
    'anthropic_token_usage' => [
        'type' => 'stream',
        'path' => '%kernel.logs_dir%/anthropic_usage.log',
        'level' => 'info'
    ]
]
```

Key log events:
- Usage collection success/failure
- Batch processing results
- Data aggregation status
- Data quality metrics

## Performance Considerations

- **Async Processing**: Use Symfony Messenger for high-volume applications
- **Data Aggregation**: Pre-aggregated statistics provide fast queries for dashboards
- **Batch Collection**: Collect multiple usage records in a single operation
- **Indexing**: Database indexes are automatically created for common query patterns

## Testing

Run the test suite:

```bash
php vendor/bin/phpunit packages/anthropic-token-usage-bundle/tests
```

## Dependencies

This bundle requires:

- PHP 8.2+
- Symfony 7.3+
- Doctrine ORM
- EasyAdminBundle
- Tourze AccessKeyBundle
- Tourze HttpForwardBundle
- Tourze EasyAdminMenuBundle

## License

MIT License - see the LICENSE file for details.

## Support

For issues and questions:
- GitHub Issues: [tourze/php-monorepo](https://github.com/tourze/php-monorepo)
- Documentation: See the `docs/` directory

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.