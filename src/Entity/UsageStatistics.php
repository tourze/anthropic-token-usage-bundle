<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AnthropicTokenUsageBundle\Repository\UsageStatisticsRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;

/**
 * 预聚合的Usage统计数据
 */
#[ORM\Entity(repositoryClass: UsageStatisticsRepository::class)]
#[ORM\Table(name: 'usage_statistics', options: ['comment' => '预聚合Usage统计数据'])]
#[ORM\UniqueConstraint(
    name: 'unique_dimension_period',
    columns: ['dimension_type', 'dimension_id', 'period_type', 'period_start']
)]
#[ORM\Index(columns: ['dimension_type', 'dimension_id', 'period_start'], name: 'usage_statistics_idx_dimension_period')]
class UsageStatistics implements \Stringable
{
    use SnowflakeKeyAware;

    public const DIMENSION_ACCESS_KEY = 'access_key';
    public const DIMENSION_USER = 'user';

    public const PERIOD_HOUR = 'hour';
    public const PERIOD_DAY = 'day';
    public const PERIOD_MONTH = 'month';

    // 维度字段
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::DIMENSION_ACCESS_KEY, self::DIMENSION_USER])]
    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, options: ['comment' => '维度类型'])]
    private string $dimensionType;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, options: ['comment' => '维度ID（AccessKey.id或User.id）'])]
    private string $dimensionId;

    // 时间维度
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::PERIOD_HOUR, self::PERIOD_DAY, self::PERIOD_MONTH])]
    #[ORM\Column(type: Types::STRING, length: 10, nullable: false, options: ['comment' => '时间粒度'])]
    private string $periodType;

    #[Assert\NotNull]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['comment' => '统计周期开始时间'])]
    private \DateTimeImmutable $periodStart;

    #[Assert\NotNull]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['comment' => '统计周期结束时间'])]
    private \DateTimeImmutable $periodEnd;

    // 聚合数据
    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['default' => 0, 'comment' => '总输入Token数量'])]
    private int $totalInputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['default' => 0, 'comment' => '总缓存创建输入Token数量'])]
    private int $totalCacheCreationInputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['default' => 0, 'comment' => '总缓存读取输入Token数量'])]
    private int $totalCacheReadInputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['default' => 0, 'comment' => '总输出Token数量'])]
    private int $totalOutputTokens = 0;

    #[Assert\Type(type: 'int')]
    #[Assert\PositiveOrZero()]
    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['default' => 0, 'comment' => '总请求数量'])]
    private int $totalRequests = 0;

    // 元数据
    #[Assert\NotNull]
    #[IndexColumn]
    #[ORM\Column(name: 'lastUpdateTime', type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['comment' => '最后更新时间'])]
    private \DateTimeImmutable $lastUpdateTime;

    public function __construct()
    {
        $this->lastUpdateTime = new \DateTimeImmutable();
    }

    public function getDimensionType(): string
    {
        return $this->dimensionType;
    }

    public function setDimensionType(string $dimensionType): void
    {
        $this->dimensionType = $dimensionType;
    }

    public function getDimensionId(): string
    {
        return $this->dimensionId;
    }

    public function setDimensionId(string $dimensionId): void
    {
        $this->dimensionId = $dimensionId;
    }

    public function getPeriodType(): string
    {
        return $this->periodType;
    }

    public function setPeriodType(string $periodType): void
    {
        $this->periodType = $periodType;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): void
    {
        $this->periodStart = $periodStart;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): void
    {
        $this->periodEnd = $periodEnd;
    }

    public function getTotalInputTokens(): int
    {
        return $this->totalInputTokens;
    }

    public function setTotalInputTokens(int $totalInputTokens): void
    {
        $this->totalInputTokens = $totalInputTokens;
    }

    public function getTotalCacheCreationInputTokens(): int
    {
        return $this->totalCacheCreationInputTokens;
    }

    public function setTotalCacheCreationInputTokens(int $totalCacheCreationInputTokens): void
    {
        $this->totalCacheCreationInputTokens = $totalCacheCreationInputTokens;
    }

    public function getTotalCacheReadInputTokens(): int
    {
        return $this->totalCacheReadInputTokens;
    }

    public function setTotalCacheReadInputTokens(int $totalCacheReadInputTokens): void
    {
        $this->totalCacheReadInputTokens = $totalCacheReadInputTokens;
    }

    public function getTotalOutputTokens(): int
    {
        return $this->totalOutputTokens;
    }

    public function setTotalOutputTokens(int $totalOutputTokens): void
    {
        $this->totalOutputTokens = $totalOutputTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalInputTokens + $this->totalCacheCreationInputTokens
             + $this->totalCacheReadInputTokens + $this->totalOutputTokens;
    }

    public function getAvgTokensPerRequest(): float
    {
        if (0 === $this->totalRequests) {
            return 0.0;
        }

        return $this->getTotalTokens() / $this->totalRequests;
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    public function setTotalRequests(int $totalRequests): void
    {
        $this->totalRequests = $totalRequests;
    }

    public function getLastUpdateTime(): \DateTimeImmutable
    {
        return $this->lastUpdateTime;
    }

    public function setLastUpdateTime(\DateTimeImmutable $lastUpdateTime): void
    {
        $this->lastUpdateTime = $lastUpdateTime;
    }

    /**
     * 增加统计数据
     */
    public function addUsageData(
        int $inputTokens,
        int $cacheCreationInputTokens,
        int $cacheReadInputTokens,
        int $outputTokens,
        int $requests = 1,
    ): void {
        $this->totalInputTokens += $inputTokens;
        $this->totalCacheCreationInputTokens += $cacheCreationInputTokens;
        $this->totalCacheReadInputTokens += $cacheReadInputTokens;
        $this->totalOutputTokens += $outputTokens;
        $this->totalRequests += $requests;
        $this->lastUpdateTime = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        $id = $this->getId();
        $idStr = $id ?? 'unknown';

        return sprintf(
            'UsageStatistics[%s] - %s:%s %s %s tokens (%d requests)',
            $idStr,
            $this->dimensionType,
            $this->dimensionId,
            $this->periodType,
            number_format($this->getTotalTokens()),
            $this->totalRequests
        );
    }
}
