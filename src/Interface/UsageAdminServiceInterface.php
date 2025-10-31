<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Interface;

use Tourze\AnthropicTokenUsageBundle\ValueObject\AdminOverviewFilter;
use Tourze\AnthropicTokenUsageBundle\ValueObject\ExportJobResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\SystemUsageOverview;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumersQuery;
use Tourze\AnthropicTokenUsageBundle\ValueObject\TopConsumersResult;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageDataHealthMetrics;
use Tourze\AnthropicTokenUsageBundle\ValueObject\UsageExportQuery;

/**
 * 管理界面服务接口
 */
interface UsageAdminServiceInterface
{
    /**
     * 获取系统整体usage概览
     */
    public function getSystemOverview(AdminOverviewFilter $filter): SystemUsageOverview;

    /**
     * 获取Top消费者列表 (AccessKey或User维度)
     */
    public function getTopConsumers(TopConsumersQuery $query): TopConsumersResult;

    /**
     * 导出usage数据
     */
    public function exportUsageData(UsageExportQuery $query): ExportJobResult;

    /**
     * 获取usage数据健康度指标
     */
    public function getDataHealthMetrics(): UsageDataHealthMetrics;
}
