<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\AccessKeyBundle\DataFixtures\AccessKeyFixtures;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;

/**
 * AccessKeyUsage实体数据加载器
 */
final class AccessKeyUsageFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // 获取参考数据
        $accessKey = $this->getReference(AccessKeyFixtures::DEFAULT_CALLER_REFERENCE, AccessKey::class);

        // 创建测试数据
        for ($i = 0; $i < 20; ++$i) {
            $usage = new AccessKeyUsage();
            $usage->setAccessKey($accessKey);
            // User字段可以为null，所以不设置具体的user
            $usage->setInputTokens(rand(100, 1000));
            $usage->setCacheCreationInputTokens(rand(0, 100));
            $usage->setCacheReadInputTokens(rand(0, 50));
            $usage->setOutputTokens(rand(200, 2000));
            $usage->setModel('claude-3-sonnet-20240229');
            $usage->setRequestId(sprintf('req_%s_%d', bin2hex(random_bytes(4)), $i));
            $usage->setStopReason('end_turn');
            $usage->setEndpoint('/v1/messages');
            $usage->setFeature('chat');

            // 设置不同的发生时间
            $occurTime = new \DateTimeImmutable(sprintf('-%d hours', rand(1, 168)));
            $usage->setOccurTime($occurTime);

            $manager->persist($usage);
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
