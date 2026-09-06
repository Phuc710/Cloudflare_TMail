<?php
declare(strict_types=1);

namespace KaiMail\Core\Auth;

/**
 * Authentication and authorization context of the current request.
 */
final class AuthContext
{
    private Role $role;
    private string $identifier;
    private string $authType;
    private array $permissions;
    private array $metadata;

    public function __construct(
        Role $role,
        string $identifier,
        string $authType,
        array $permissions = [],
        array $metadata = []
    ) {
        $this->role = $role;
        $this->identifier = $identifier;
        $this->authType = $authType;
        $this->permissions = $permissions ?: Permission::forRole($role);
        $this->metadata = $metadata;
    }

    public static function anonymous(): self
    {
        return new self(Role::ANONYMOUS, 'guest', 'anonymous', []);
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getAuthType(): string
    {
        return $this->authType;
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isApiUser(): bool
    {
        return $this->role === Role::API_USER;
    }

    public function isApiBot(): bool
    {
        return $this->role === Role::API_USER;
    }

    public function isPublicUser(): bool
    {
        return $this->role === Role::PUBLIC_USER;
    }

    public function isWebhook(): bool
    {
        return $this->role === Role::WEBHOOK;
    }

    public function isAuthenticated(): bool
    {
        return $this->role !== Role::ANONYMOUS;
    }
}
