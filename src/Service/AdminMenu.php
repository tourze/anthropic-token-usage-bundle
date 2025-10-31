<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\AnthropicTokenUsageBundle\Entity\UserUsage;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * Anthropic Token使用情况菜单服务
 */
#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private ?LinkGeneratorInterface $linkGenerator = null,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('Token使用管理')) {
            $item->addChild('Token使用管理');
        }

        $tokenMenu = $item->getChild('Token使用管理');

        if (null !== $tokenMenu) {
            // 如果 linkGenerator 不可用，使用默认路径
            $accessKeyUri = $this->linkGenerator?->getCurdListPage(AccessKeyUsage::class) ?? '/admin/access-key-usage';
            $userUri = $this->linkGenerator?->getCurdListPage(UserUsage::class) ?? '/admin/user-usage';
            $statisticsUri = $this->linkGenerator?->getCurdListPage(UsageStatistics::class) ?? '/admin/usage-statistics';

            // AccessKey使用记录菜单
            $tokenMenu->addChild('AccessKey使用记录')
                ->setUri($accessKeyUri)
                ->setAttribute('icon', 'fas fa-key')
                ->setAttribute('description', '查看AccessKey维度的Token使用记录')
            ;

            // 用户使用记录菜单
            $tokenMenu->addChild('用户使用记录')
                ->setUri($userUri)
                ->setAttribute('icon', 'fas fa-user')
                ->setAttribute('description', '查看用户维度的Token使用记录')
            ;

            // 统计数据菜单
            $tokenMenu->addChild('统计数据')
                ->setUri($statisticsUri)
                ->setAttribute('icon', 'fas fa-chart-bar')
                ->setAttribute('description', '查看预聚合的Token使用统计数据')
            ;
        }
    }
}
