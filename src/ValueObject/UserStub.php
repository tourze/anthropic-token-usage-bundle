<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\ValueObject;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 用户桩对象，用于表示仅有标识符的用户
 *
 * @internal 仅用于Repository内部返回查询结果
 */
final class UserStub implements UserInterface
{
    /**
     * @param non-empty-string $identifier
     */
    public function __construct(private string $identifier)
    {
        if ('' === $this->identifier) {
            throw new \InvalidArgumentException('User identifier cannot be empty');
        }
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials(): void
    {
    }
}
