<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\DataFixtures\AccessKeyFixtures;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\UserServiceContracts\UserManagerInterface;

/**
 * UserUsage实体数据加载器
 */
final class UserUsageFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 获取参考数据
        $accessKey = $this->getReference(AccessKeyFixtures::DEFAULT_CALLER_REFERENCE, AccessKey::class);

        // 获取或创建测试用户
        $testUser = $this->getOrCreateTestUser();

        // 创建测试数据
        for ($i = 0; $i < 15; ++$i) {
            $usage = new UserUsage();
            $usage->setUser($testUser);
            $usage->setAccessKey($accessKey);
            $usage->setInputTokens(rand(50, 500));
            $usage->setCacheCreationInputTokens(rand(0, 50));
            $usage->setCacheReadInputTokens(rand(0, 25));
            $usage->setOutputTokens(rand(100, 1000));
            $usage->setModel('claude-3-haiku-20240307');
            $usage->setRequestId(sprintf('user_req_%s_%d', bin2hex(random_bytes(4)), $i));
            $usage->setStopReason('end_turn');
            $usage->setEndpoint('/v1/messages');
            $usage->setFeature('chat');

            // 设置不同的发生时间
            $occurTime = new \DateTimeImmutable(sprintf('-%d hours', rand(1, 72)));
            $usage->setOccurTime($occurTime);

            $manager->persist($usage);
        }

        $manager->flush();
    }

    private function getOrCreateTestUser(): UserInterface
    {
        // 尝试加载已存在的用户
        $user = $this->userManager->loadUserByIdentifier('test-user');

        // 如果用户不存在，创建一个新的测试用户
        if (null === $user) {
            $user = $this->userManager->createUser('test-user', '测试用户');
            $this->userManager->saveUser($user);
        }

        return $user;
    }

    public function getDependencies(): array
    {
        return [
            AccessKeyFixtures::class,
        ];
    }
}
