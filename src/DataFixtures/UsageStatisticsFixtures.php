<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AccessKeyBundle\DataFixtures\AccessKeyFixtures;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;

/**
 * UsageStatistics实体数据加载器
 */
final class UsageStatisticsFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // 获取参考数据
        $accessKey = $this->getReference(AccessKeyFixtures::DEFAULT_CALLER_REFERENCE, AccessKey::class);

        // 创建按天统计的数据
        for ($day = 0; $day < 30; ++$day) {
            $date = new \DateTimeImmutable(sprintf('-%d days', $day));

            // AccessKey维度统计
            $accessKeyStats = new UsageStatistics();
            $accessKeyStats->setDimensionType(UsageStatistics::DIMENSION_ACCESS_KEY);
            $accessKeyStats->setDimensionId((string) $accessKey->getId());
            $accessKeyStats->setPeriodType(UsageStatistics::PERIOD_DAY);
            $accessKeyStats->setPeriodStart($date->setTime(0, 0, 0));
            $accessKeyStats->setPeriodEnd($date->setTime(23, 59, 59));
            $accessKeyStats->setTotalRequests(rand(10, 100));
            $accessKeyStats->setTotalInputTokens(rand(1000, 10000));
            $accessKeyStats->setTotalCacheCreationInputTokens(rand(100, 1000));
            $accessKeyStats->setTotalCacheReadInputTokens(rand(50, 500));
            $accessKeyStats->setTotalOutputTokens(rand(2000, 20000));
            $accessKeyStats->setLastUpdateTime(new \DateTimeImmutable());

            $manager->persist($accessKeyStats);

            // 用户维度统计（使用模拟用户ID）
            $userStats = new UsageStatistics();
            $userStats->setDimensionType(UsageStatistics::DIMENSION_USER);
            $userStats->setDimensionId('user_' . $day); // 使用模拟的用户ID
            $userStats->setPeriodType(UsageStatistics::PERIOD_DAY);
            $userStats->setPeriodStart($date->setTime(0, 0, 0));
            $userStats->setPeriodEnd($date->setTime(23, 59, 59));
            $userStats->setTotalRequests(rand(5, 50));
            $userStats->setTotalInputTokens(rand(500, 5000));
            $userStats->setTotalCacheCreationInputTokens(rand(50, 500));
            $userStats->setTotalCacheReadInputTokens(rand(25, 250));
            $userStats->setTotalOutputTokens(rand(1000, 10000));
            $userStats->setLastUpdateTime(new \DateTimeImmutable());

            $manager->persist($userStats);
        }

        // 创建一些按小时统计的数据
        for ($hour = 0; $hour < 24; ++$hour) {
            $time = new \DateTimeImmutable(sprintf('-%d hours', $hour));
            $hourInt = (int) $time->format('H');

            $hourlyStats = new UsageStatistics();
            $hourlyStats->setDimensionType(UsageStatistics::DIMENSION_ACCESS_KEY);
            $hourlyStats->setDimensionId((string) $accessKey->getId());
            $hourlyStats->setPeriodType(UsageStatistics::PERIOD_HOUR);
            $hourlyStats->setPeriodStart($time->setTime($hourInt, 0, 0));
            $hourlyStats->setPeriodEnd($time->setTime($hourInt, 59, 59));
            $hourlyStats->setTotalRequests(rand(1, 10));
            $hourlyStats->setTotalInputTokens(rand(100, 1000));
            $hourlyStats->setTotalCacheCreationInputTokens(rand(10, 100));
            $hourlyStats->setTotalCacheReadInputTokens(rand(5, 50));
            $hourlyStats->setTotalOutputTokens(rand(200, 2000));
            $hourlyStats->setLastUpdateTime(new \DateTimeImmutable());

            $manager->persist($hourlyStats);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AccessKeyFixtures::class,
        ];
    }
}
