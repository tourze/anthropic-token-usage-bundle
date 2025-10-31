<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

/**
 * Usage趋势查询结果
 */
final readonly class UsageTrendResult
{
    /**
     * @param array<UsageTrendDataPoint> $dataPoints
     * @param array<string, mixed> $summary
     */
    public function __construct(
        /** @var array<UsageTrendDataPoint> */
        public array $dataPoints,
        public \DateTimeInterface $startDate,
        public \DateTimeInterface $endDate,
        public string $periodType,
        /** @var array<string, mixed> */
        public array $summary = [],
    ) {
    }

    /**
     * 获取数据点数量
     */
    public function getDataPointCount(): int
    {
        return count($this->dataPoints);
    }

    /**
     * 检查是否有数据
     */
    public function hasData(): bool
    {
        return [] !== $this->dataPoints;
    }

    /**
     * 获取总token使用量
     */
    public function getTotalTokens(): int
    {
        return array_sum(array_map(
            fn (UsageTrendDataPoint $point) => $point->getTotalTokens(),
            $this->dataPoints
        ));
    }

    /**
     * 获取总请求数
     */
    public function getTotalRequests(): int
    {
        return array_sum(array_map(
            fn (UsageTrendDataPoint $point) => $point->totalRequests,
            $this->dataPoints
        ));
    }

    /**
     * 获取平均每日token使用量
     */
    public function getAverageDailyTokens(): float
    {
        if ([] === $this->dataPoints) {
            return 0.0;
        }

        return $this->getTotalTokens() / count($this->dataPoints);
    }

    /**
     * 获取峰值数据点
     */
    public function getPeakDataPoint(): ?UsageTrendDataPoint
    {
        if ([] === $this->dataPoints) {
            return null;
        }

        return array_reduce(
            $this->dataPoints,
            fn (?UsageTrendDataPoint $carry, UsageTrendDataPoint $point) => null === $carry || $point->getTotalTokens() > $carry->getTotalTokens() ? $point : $carry
        );
    }

    /**
     * 转换为图表友好的格式
     *
     * @return array<string, mixed>
     */
    public function toChartData(): array
    {
        $labels = [];
        $tokenData = [];
        $requestData = [];

        foreach ($this->dataPoints as $point) {
            $labels[] = $point->periodStart->format('Y-m-d H:i');
            $tokenData[] = $point->getTotalTokens();
            $requestData[] = $point->totalRequests;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Total Tokens',
                    'data' => $tokenData,
                    'type' => 'line',
                ],
                [
                    'label' => 'Total Requests',
                    'data' => $requestData,
                    'type' => 'bar',
                    'yAxisID' => 'requests',
                ],
            ],
            'summary' => [
                'total_tokens' => $this->getTotalTokens(),
                'total_requests' => $this->getTotalRequests(),
                'average_daily_tokens' => $this->getAverageDailyTokens(),
                'peak_usage' => $this->getPeakDataPoint()?->toArray(),
                'period_type' => $this->periodType,
                'data_points' => $this->getDataPointCount(),
            ],
        ];
    }
}
