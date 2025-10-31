<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\App;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 简单的用户实体，用于测试
 */
class SimpleUser implements UserInterface, \Serializable
{
    private string $id;

    private string $email;

    public function __construct(string $email)
    {
        $this->id = uniqid('user_');
        $this->email = '' !== $email ? $email : 'unknown@example.com';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        // 确保返回 non-empty-string，如果 email 为空则返回默认值
        return '' !== $this->email ? $this->email : 'unknown@example.com';
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // 无操作
    }

    public function serialize(): string
    {
        return serialize([
            'id' => $this->id,
            'email' => $this->email,
        ]);
    }

    public function unserialize(string $data): void
    {
        $data = unserialize($data);
        if (is_array($data)) {
            $this->id = is_string($data['id'] ?? null) ? $data['id'] : '';
            $this->email = is_string($data['email'] ?? null) ? $data['email'] : '';
        }
    }

    /**
     * @return array{id: string, email: string}
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }

    /**
     * @param array{id: string, email: string} $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
    }
}
