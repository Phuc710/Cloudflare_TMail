<?php
declare(strict_types=1);

namespace KaiMail\Core\Auth;

/**
 * System roles enumeration.
 */
enum Role: string
{
    case ADMIN = 'admin';
    case API_USER = 'api_user';
    case PUBLIC_USER = 'public_user';
    case WEBHOOK = 'webhook';
    case ANONYMOUS = 'anonymous';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::API_USER => 'External API Client',
            self::PUBLIC_USER => 'Public Web User',
            self::WEBHOOK => 'Cloudflare Webhook Ingestion',
            self::ANONYMOUS => 'Anonymous Guest',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isApiUser(): bool
    {
        return $this === self::API_USER;
    }

    public function isPublicUser(): bool
    {
        return $this === self::PUBLIC_USER;
    }

    public function isWebhook(): bool
    {
        return $this === self::WEBHOOK;
    }
}
