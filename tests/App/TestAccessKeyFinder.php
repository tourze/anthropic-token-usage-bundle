<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\App;

use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\AccessKeyBundle\Interface\AccessKeyFinderInterface;

/**
 * 测试用的 AccessKeyFinder 实现
 */
final class TestAccessKeyFinder implements AccessKeyFinderInterface
{
    public function findById(string|int $accessKeyId): ?AccessKey
    {
        return null;
    }

    public function findRequiredById(string|int $accessKeyId): AccessKey
    {
        throw new \RuntimeException('AccessKey not found in test environment');
    }

    public function findByAppId(string $appId): null
    {
        return null;
    }
}
