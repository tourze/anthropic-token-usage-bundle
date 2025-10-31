<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessKeyBundle\Entity\AccessKey;

/**
 * 单个Usage收集项
 */
final readonly class UsageCollectionItem
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public AnthropicUsageData $usageData,
        public ?AccessKey $accessKey = null,
        public ?UserInterface $user = null,
        public array $metadata = [],
    ) {
    }
}
