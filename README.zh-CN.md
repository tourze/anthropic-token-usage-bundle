# Anthropic Token Usage Bundle

[中文](README.zh-CN.md) | [English](README.md)

一个用于跟踪和分析 Anthropic API token 使用情况的 Symfony Bundle，提供全面的统计功能、管理界面和数据聚合能力。

## 概述

`AnthropicTokenUsageBundle` 是一个专为 Symfony 应用程序设计的高级 token 使用监控解决方案。它不仅提供基础的 token 使用跟踪，还包括数据聚合、多维度分析、异步处理和企业级管理界面，帮助您全面了解和优化 Anthropic API 的使用情况。

## 功能特性

### 🎯 核心功能
- **精准 Token 跟踪**：完整记录输入/输出 tokens、缓存创建/读取 tokens、请求数量等关键指标
- **多维度分析**：同时支持 AccessKey 和用户两个维度的使用统计，满足不同层级的管理需求
- **智能数据聚合**：按小时、天、月自动预聚合数据，确保查询性能始终如一
- **企业级管理界面**：基于 EasyAdmin 的现代化后台，支持数据浏览、筛选和导出

### ⚡ 高性能特性
- **异步处理架构**：集成 Symfony Messenger，支持高并发场景下的使用数据收集
- **批量数据收集**：单次操作处理大量使用记录，显著提升性能
- **智能索引策略**：自动创建数据库索引，优化常见查询模式
- **内存优化设计**：使用值对象和延迟加载，最小化内存占用

### 📊 数据质量保障
- **数据完整性检查**：内置数据一致性验证机制
- **质量指标监控**：实时监控数据完整性和异常情况
- **自动错误恢复**：处理收集过程中的异常情况
- **详细审计日志**：完整记录所有数据操作和系统事件

## 安装

### 系统要求

- PHP 8.2 或更高版本
- Symfony 7.3 或更高版本
- Doctrine ORM 3.0+
- MySQL 8.0+ / PostgreSQL 12+ (推荐)

### 安装步骤

1. **安装 Bundle**

```bash
composer require tourze/anthropic-token-usage-bundle
```

2. **启用 Bundle**

在 `config/bundles.php` 中添加：

```php
<?php

return [
    // 其他 bundles...
    Tourze\AnthropicTokenUsageBundle\AnthropicTokenUsageBundle::class => ['all' => true],
];
```

3. **数据库迁移**

Bundle 会自动创建必要的数据库表：
- `access_key_usage` - AccessKey 级别的使用记录
- `user_usage` - 用户级别的使用记录
- `usage_statistics` - 预聚合统计数据

运行迁移命令：
```bash
php bin/console doctrine:migrations:migrate
```

## 快速开始

### 基础配置

```yaml
# config/packages/anthropic_token_usage.yaml
anthropic_token_usage:
    # 启用异步处理（推荐生产环境开启）
    async_processing: true

    # 批量处理大小
    batch_size: 100

    # 数据保留天数
    retention_days: 90

    # 启用数据质量监控
    enable_quality_metrics: true
```

### Messenger 配置（异步处理）

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
            # 或者使用 Redis
            # async: 'redis://redis:6379/messages'

        routing:
            'Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage': async

        # 批量处理配置
        buses:
            messenger.bus.default:
                middleware:
                    - 'doctrine_transaction'
                    - 'doctrine_ping_connection'
```

### 日志配置

```yaml
# config/packages/monolog.yaml
monolog:
    channels: ['anthropic_token_usage']

    handlers:
        anthropic_usage:
            type: stream
            path: '%kernel.logs_dir%/anthropic_usage.log'
            level: info
            channels: ['anthropic_token_usage']
```

## 使用指南

### 基础使用数据收集

```php
<?php

namespace App\Service;

use Tourze\AnthropicTokenUsageBundle\Service\UsageCollector;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;
use Tourze\AccessKeyBundle\Model\AccessKey;
use Symfony\Component\Security\Core\User\UserInterface;

class AnthropicApiService
{
    public function __construct(
        private readonly UsageCollector $usageCollector
    ) {}

    public function processChatCompletion(array $apiResponse, ?AccessKey $accessKey = null, ?UserInterface $user = null): void
    {
        // 从 Anthropic API 响应提取使用数据
        $usage = $apiResponse['usage'] ?? [];

        $usageData = new AnthropicUsageData(
            inputTokens: $usage['input_tokens'] ?? 0,
            outputTokens: $usage['output_tokens'] ?? 0,
            cacheCreationInputTokens: $usage['cache_creation_input_tokens'] ?? 0,
            cacheReadInputTokens: $usage['cache_read_input_tokens'] ?? 0
        );

        // 收集使用数据，包含丰富的元数据
        $this->usageCollector->collectUsage(
            usageData: $usageData,
            accessKey: $accessKey,
            user: $user,
            metadata: [
                'model' => $apiResponse['model'] ?? 'unknown',
                'request_id' => $apiResponse['id'] ?? null,
                'feature' => 'chat_completion',
                'endpoint' => '/api/v1/messages',
                'temperature' => $apiResponse['temperature'] ?? null,
                'max_tokens' => $apiResponse['max_tokens'] ?? null,
                'stop_reason' => $apiResponse['stop_reason'] ?? null,
            ]
        );
    }
}
```

### 批量数据收集（高性能场景）

```php
<?php

use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionItem;

class BatchUsageProcessor
{
    public function __construct(
        private readonly UsageCollector $usageCollector
    ) {}

    public function processBatch(array $apiResponses): void
    {
        $items = [];

        foreach ($apiResponses as $response) {
            $usageData = $this->extractUsageData($response);
            $accessKey = $this->findAccessKey($response['access_key_id'] ?? null);
            $user = $this->findUser($response['user_id'] ?? null);

            $items[] = new UsageCollectionItem(
                usageData: $usageData,
                accessKey: $accessKey,
                user: $user,
                metadata: $this->extractMetadata($response)
            );
        }

        $batch = new UsageCollectionBatch($items);
        $result = $this->usageCollector->collectBatchUsage($batch);

        // 处理结果
        if ($result->hasFailures()) {
            $this->logger->warning('部分使用数据收集失败', [
                'total' => $result->totalItems,
                'success' => $result->successCount,
                'failures' => $result->failureCount,
                'errors' => $result->getErrors()
            ]);
        }
    }
}
```

### 高级统计查询

```php
<?php

use Tourze\AnthropicTokenUsageBundle\Service\UsageQueryService;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;

class UsageAnalyticsService
{
    public function __construct(
        private readonly UsageQueryService $queryService
    ) {}

    /**
     * 获取指定 AccessKey 的月度使用统计
     */
    public function getAccessKeyMonthlyStats(string $accessKeyId, int $months = 6): array
    {
        $filter = new UsageQueryFilter(
            dimensionType: UsageStatistics::DIMENSION_ACCESS_KEY,
            dimensionId: $accessKeyId,
            periodType: UsageStatistics::PERIOD_MONTH,
            startDate: new DateTimeImmutable("-{$months} months"),
            endDate: new DateTimeImmutable()
        );

        $result = $this->queryService->getUsageStatistics($filter);

        return array_map(fn($stat) => [
            'period' => $stat->periodStart->format('Y-m'),
            'input_tokens' => $stat->inputTokens,
            'output_tokens' => $stat->outputTokens,
            'cache_creation_tokens' => $stat->cacheCreationInputTokens,
            'cache_read_tokens' => $stat->cacheReadInputTokens,
            'total_tokens' => $stat->getTotalTokens(),
            'requests' => $stat->totalRequests,
            'avg_tokens_per_request' => $stat->getTotalTokens() / max($stat->totalRequests, 1)
        ], $result->items);
    }

    /**
     * 获取 Top 消费者排行榜
     */
    public function getTopConsumersRanking(
        string $dimensionType = UsageStatistics::DIMENSION_ACCESS_KEY,
        int $limit = 20,
        int $days = 30
    ): array {
        return $this->queryService->getTopConsumers(
            dimensionType: $dimensionType,
            periodType: UsageStatistics::PERIOD_DAY,
            startDate: new DateTimeImmutable("-{$days} days"),
            endDate: new DateTimeImmutable(),
            limit: $limit
        );
    }

    /**
     * 获取使用趋势分析
     */
    public function getUsageTrends(
        string $dimensionId,
        string $dimensionType,
        int $days = 30
    ): array {
        $filter = new UsageQueryFilter(
            dimensionType: $dimensionType,
            dimensionId: $dimensionId,
            periodType: UsageStatistics::PERIOD_DAY,
            startDate: new DateTimeImmutable("-{$days} days"),
            endDate: new DateTimeImmutable()
        );

        return $this->queryService->getUsageTrends($filter);
    }
}
```

### 数据聚合管理

```php
<?php

use Tourze\AnthropicTokenUsageBundle\Service\UsageAggregateService;

class DataAggregationManager
{
    public function __construct(
        private readonly UsageAggregateService $aggregateService
    ) {}

    /**
     * 执行每日数据聚合（建议通过定时任务调用）
     */
    public function performDailyAggregation(): void
    {
        $yesterday = new DateTimeImmutable('-1 day');
        $today = new DateTimeImmutable();

        // 聚合 AccessKey 使用数据
        $accessKeyResult = $this->aggregateService->aggregateAccessKeyUsage($yesterday, $today);

        // 聚合用户使用数据
        $userResult = $this->aggregateService->aggregateUserUsage($yesterday, $today);

        $this->logger->info('数据聚合完成', [
            'access_key_records' => $accessKeyResult->processedRecords,
            'user_records' => $userResult->processedRecords,
            'execution_time' => $accessKeyResult->getExecutionTime() + $userResult->getExecutionTime()
        ]);
    }

    /**
     * 重建历史统计数据
     */
    public function rebuildHistoricalStats(DateTimeImmutable $startDate, DateTimeImmutable $endDate): void
    {
        $result = $this->aggregateService->rebuildStatistics($startDate, $endDate);

        if ($result->hasErrors()) {
            throw new RuntimeException('统计数据重建失败: ' . implode(', ', $result->getErrors()));
        }

        $this->logger->info('历史统计数据重建完成', [
            'processed_records' => $result->processedRecords,
            'period' => $startDate->format('Y-m-d') . ' 至 ' . $endDate->format('Y-m-d')
        ]);
    }
}
```

## 管理界面使用

### 访问管理后台

1. 确保已安装并配置 EasyAdminBundle
2. 访问 `/admin` 路径（或您配置的 EasyAdmin 路径）
3. 您将看到以下管理模块：

#### 📊 使用数据浏览
- **AccessKey 使用记录**：按 AccessKey 查看详细的使用数据
- **用户使用记录**：按用户查看详细的使用数据
- **使用统计概览**：查看聚合后的统计数据

#### 🔍 高级搜索和筛选
- 按时间范围筛选
- 按 AccessKey 或用户筛选
- 按使用量范围筛选
- 按模型或功能类型筛选

#### 📈 统计图表和导出
- 使用趋势图表
- Top 消费者排行
- CSV/Excel 数据导出
- 自定义报表生成

## 值对象详解

### AnthropicUsageData - 核心数据结构

```php
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

// 创建使用数据
$usageData = new AnthropicUsageData(
    inputTokens: 1000,              // 输入 tokens
    outputTokens: 500,              // 输出 tokens
    cacheCreationInputTokens: 100,  // 缓存创建 tokens
    cacheReadInputTokens: 50        // 缓存读取 tokens
);

// 获取总 token 数（包含缓存）
$totalTokens = $usageData->getTotalTokens(); // 1650

// 获取有效 token 数（不包含缓存）
$effectiveTokens = $usageData->getEffectiveTokens(); // 1500

// 转换为数组格式
$array = $usageData->toArray();
```

### UsageQueryFilter - 查询过滤器

```php
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageQueryFilter;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;

$filter = new UsageQueryFilter(
    dimensionType: UsageStatistics::DIMENSION_ACCESS_KEY, // 或 DIMENSION_USER
    dimensionId: 'access_key_123',
    periodType: UsageStatistics::PERIOD_DAY,             // 或 PERIOD_HOUR, PERIOD_MONTH
    startDate: new DateTimeImmutable('-30 days'),
    endDate: new DateTimeImmutable(),
    models: ['claude-3-sonnet', 'claude-3-haiku'],      // 可选：模型筛选
    features: ['chat_completion'],                       // 可选：功能筛选
    limit: 100,                                         // 可选：结果限制
    offset: 0                                           // 可选：偏移量
);
```

### UsageCollectionBatch - 批量收集

```php
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionBatch;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageCollectionItem;

$batch = new UsageCollectionBatch([
    new UsageCollectionItem(
        usageData: $usageData1,
        accessKey: $accessKey1,
        user: $user1,
        metadata: ['request_id' => 'req_123', 'model' => 'claude-3-sonnet']
    ),
    new UsageCollectionItem(
        usageData: $usageData2,
        accessKey: $accessKey2,
        user: $user2,
        metadata: ['request_id' => 'req_124', 'model' => 'claude-3-haiku']
    )
]);

// 批量收集会自动处理验证、去重和错误处理
$result = $this->usageCollector->collectBatchUsage($batch);

// 检查结果
if ($result->hasFailures()) {
    foreach ($result->getFailureItems() as $failure) {
        $this->logger->error('使用数据收集失败', [
            'error' => $failure->error,
            'metadata' => $failure->metadata
        ]);
    }
}
```

## 高级配置

### 完整配置示例

```yaml
# config/packages/anthropic_token_usage.yaml
anthropic_token_usage:
    # 性能配置
    async_processing: true
    batch_size: 50

    # 数据管理
    retention_days: 90
    enable_quality_metrics: true

    # 聚合配置
    auto_aggregation:
        enabled: true
        schedule: '0 2 * * *'  # 每天凌晨2点执行
        batch_size: 1000

    # 缓存配置
    cache:
        enabled: true
        ttl: 3600  # 1小时
        prefix: 'anthropic_usage_'

    # 监控配置
    monitoring:
        enable_health_check: true
        alert_thresholds:
            daily_tokens: 1000000
            error_rate: 0.05  # 5%
            response_time: 5000  # 5秒
```

### Messenger 高级配置

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: messenger.bus.default

        transports:
            # 使用 Redis 作为消息队列
            anthropic_usage_async:
                dsn: 'redis://redis:6379/messages'
                options:
                    stream_max_entries: 10000
                    sleep: 1000000  # 1秒

        routing:
            'Tourze\AnthropicTokenUsageBundle\Message\UsageCollectionMessage': anthropic_usage_async
            'Tourze\AnthropicTokenUsageBundle\Message\UsageAggregationMessage': anthropic_usage_async

        buses:
            messenger.bus.default:
                middleware:
                    - 'doctrine_transaction'
                    - 'doctrine_ping_connection'
                    - 'retry'
                    - 'logging'
```

### 自定义事件监听器

```php
<?php

namespace App\EventListener;

use Tourze\AnthropicTokenUsageBundle\Event\UsageCollectedEvent;
use Tourze\AnthropicTokenUsageBundle\Event\UsageAggregationCompletedEvent;
use Psr\Log\LoggerInterface;

class UsageEventListener
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function onUsageCollected(UsageCollectedEvent $event): void
    {
        $this->logger->info('使用数据收集完成', [
            'access_key' => $event->getAccessKey()?->getId(),
            'user' => $event->getUser()?->getId(),
            'tokens' => $event->getUsageData()->getTotalTokens(),
            'model' => $event->getMetadata()['model'] ?? 'unknown'
        ]);
    }

    public function onUsageAggregationCompleted(UsageAggregationCompletedEvent $event): void
    {
        $this->logger->info('数据聚合完成', [
            'period' => $event->getPeriodStart()->format('Y-m-d') . ' 至 ' . $event->getPeriodEnd()->format('Y-m-d'),
            'records_processed' => $event->getProcessedRecords(),
            'execution_time' => $event->getExecutionTime() . 'ms'
        ]);
    }
}
```

## 性能优化建议

### 数据库优化

```sql
-- 为常用查询添加索引（Bundle 会自动创建，但可供参考）
CREATE INDEX idx_access_key_usage_created_at ON access_key_usage (created_at);
CREATE INDEX idx_access_key_usage_access_key_id ON access_key_usage (access_key_id);
CREATE INDEX idx_user_usage_user_id ON user_usage (user_id);
CREATE INDEX idx_usage_statistics_composite ON usage_statistics (dimension_type, dimension_id, period_type, period_start);
```

### 缓存策略

```php
// 在高频查询场景中使用 Redis 缓存
use Symfony\Contracts\Cache\CacheInterface;

class CachedUsageService
{
    public function __construct(
        private readonly UsageQueryService $queryService,
        private readonly CacheInterface $cache
    ) {}

    public function getCachedUsageStats(string $accessKeyId, int $days = 30): array
    {
        $cacheKey = "usage_stats_{$accessKeyId}_{$days}";

        return $this->cache->get($cacheKey, function($item) use ($accessKeyId, $days) {
            $item->expiresAfter(3600); // 1小时缓存
            return $this->getUsageStats($accessKeyId, $days);
        });
    }
}
```

### 异步处理最佳实践

```php
// 对于高流量应用，建议使用队列优先级
use Symfony\Component\Messenger\MessageBusInterface;

class HighPerformanceUsageCollector
{
    public function __construct(
        private readonly MessageBusInterface $bus
    ) {}

    public function collectUsageAsync(UsageCollectionItem $item, int $priority = 0): void
    {
        $message = new UsageCollectionMessage($item);
        $message->setPriority($priority);

        $this->bus->dispatch($message);
    }
}
```

## 监控和告警

### 健康检查

```php
// 自定义健康检查
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tourze\AnthropicTokenUsageBundle\Service\UsageHealthChecker;

class UsageHealthCheck
{
    public function __construct(
        private readonly UsageHealthChecker $healthChecker
    ) {}

    public function checkHealth(): array
    {
        $metrics = $this->healthChecker->getHealthMetrics();

        return [
            'status' => $metrics->isHealthy() ? 'healthy' : 'unhealthy',
            'metrics' => [
                'data_freshness' => $metrics->getDataFreshnessMinutes(),
                'error_rate' => $metrics->getErrorRate(),
                'collection_lag' => $metrics->getCollectionLagSeconds(),
                'aggregation_status' => $metrics->getAggregationStatus()
            ],
            'alerts' => $metrics->getActiveAlerts()
        ];
    }
}
```

### 日志监控

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        anthropic_usage_alerts:
            type: fingers_crossed
            action_level: error
            handler: anthropic_usage_errors
            excluded_http_codes: [404, 403]

        anthropic_usage_errors:
            type: stream
            path: '%kernel.logs_dir%/anthropic_usage_errors.log'
            level: error

        anthropic_usage_metrics:
            type: rotating_file
            path: '%kernel.logs_dir%/anthropic_usage_metrics.log'
            max_files: 30
            level: info
            channels: ['anthropic_token_usage']
```

## 测试指南

### 单元测试示例

```php
<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tourze\AnthropicTokenUsageBundle\Service\UsageCollector;
use Tourze\AnthropicTokenUsageBundle\ValueObject\AnthropicUsageData;

class UsageCollectorTest extends TestCase
{
    public function testUsageCollection(): void
    {
        $usageData = new AnthropicUsageData(
            inputTokens: 100,
            outputTokens: 50,
            cacheCreationInputTokens: 10,
            cacheReadInputTokens: 5
        );

        $this->assertEquals(165, $usageData->getTotalTokens());
        $this->assertEquals(150, $usageData->getEffectiveTokens());
    }

    public function testBatchCollection(): void
    {
        // 测试批量收集逻辑
        $batch = new UsageCollectionBatch([
            new UsageCollectionItem($usageData1, $accessKey1, $user1),
            new UsageCollectionItem($usageData2, $accessKey2, $user2)
        ]);

        $this->assertCount(2, $batch->getItems());
    }
}
```

### 集成测试

```php
<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\AnthropicTokenUsageBundle\Service\UsageQueryService;

class UsageQueryIntegrationTest extends KernelTestCase
{
    public function testUsageStatisticsQuery(): void
    {
        self::bootKernel();

        $queryService = self::getContainer()->get(UsageQueryService::class);

        $filter = new UsageQueryFilter(
            dimensionType: UsageStatistics::DIMENSION_ACCESS_KEY,
            dimensionId: 'test-key',
            periodType: UsageStatistics::PERIOD_DAY,
            startDate: new DateTimeImmutable('-7 days'),
            endDate: new DateTimeImmutable()
        );

        $result = $queryService->getUsageStatistics($filter);

        $this->assertInstanceOf(UsageStatisticsResult::class, $result);
    }
}
```

## 故障排除

### 常见问题

#### 1. 异步消息处理失败
```bash
# 检查 Messenger 队列状态
php bin/console messenger:failed:show

# 重试失败的消息
php bin/console messenger:failed:retry

# 清理失败的消息
php bin/console messenger:failed:remove
```

#### 2. 数据聚合延迟
```bash
# 手动触发聚合
php bin/console anthropic:aggregate-usage --date=yesterday

# 检查聚合状态
php bin/console anthropic:aggregation-status
```

#### 3. 性能问题诊断
```bash
# 分析数据库查询
php bin/console doctrine:query:sql "EXPLAIN SELECT * FROM usage_statistics WHERE dimension_type = 'access_key'"

# 检查缓存命中率
php bin/console debug:cache --pool=anthropic_usage_cache
```

### 调试工具

```php
// 启用详细日志
// config/packages/dev/monolog.yaml
monolog:
    handlers:
        anthropic_usage_debug:
            type: stream
            path: '%kernel.logs_dir%/anthropic_usage_debug.log'
            level: debug
            channels: ['anthropic_token_usage']
```

## 版本兼容性

| Bundle 版本 | Symfony 版本 | PHP 版本 | 状态 |
|-------------|--------------|----------|------|
| 1.0.x | 7.0+ | 8.2+ | 稳定版 |
| 1.1.x | 7.1+ | 8.2+ | 当前版本 |

## 贡献指南

欢迎提交 Issue 和 Pull Request！

### 开发环境设置

```bash
# 克隆项目
git clone https://github.com/tourze/php-monorepo.git

# 安装依赖
cd packages/anthropic-token-usage-bundle
composer install

# 运行测试
composer test

# 代码风格检查
composer cs-check
composer cs-fix

# 静态分析
composer stan
```

## 许可证

本项目采用 MIT 许可证。详见 [LICENSE](LICENSE) 文件。

## 支持和社区

- 📖 **完整文档**：[docs/](docs/) 目录
- 🐛 **Bug 报告**：[GitHub Issues](https://github.com/tourze/php-monorepo/issues)
- 💬 **讨论交流**：[GitHub Discussions](https://github.com/tourze/php-monorepo/discussions)
- 📧 **商务合作**：联系开发团队

## 更新日志

### v1.1.0 (2024-11-14)
- ✨ 新增数据质量监控功能
- ⚡ 优化异步处理性能
- 🐛 修复批量收集的内存泄漏问题
- 📚 完善文档和示例

### v1.0.0 (2024-09-01)
- 🎉 初始版本发布
- ✅ 基础使用数据收集功能
- 📊 EasyAdmin 管理界面
- 🔄 数据聚合和统计

---

**Made with ❤️ by Tourze Team**