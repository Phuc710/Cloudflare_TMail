<?php
declare(strict_types=1);

namespace KaiMail\Core\Auth;

/**
 * System permissions catalogue and default role mappings.
 */
final class Permission
{
    public const EMAIL_CREATE_SINGLE = 'email:create_single';
    public const EMAIL_CREATE_BATCH = 'email:create_batch';
    public const EMAIL_CHECK_EXISTS = 'email:check_exists';
    public const EMAIL_LIST_ALL = 'email:list_all';
    public const EMAIL_TOGGLE_DONE = 'email:toggle_done';
    public const EMAIL_UPDATE_NOTE = 'email:update_note';
    public const EMAIL_DELETE = 'email:delete';

    public const MESSAGE_LIST_BY_EMAIL = 'message:list_by_email';
    public const MESSAGE_VIEW_DETAIL = 'message:view_detail';
    public const MESSAGE_DELETE = 'message:delete';

    public const LONG_POLL_MAILBOX = 'long_poll:mailbox';
    public const LONG_POLL_SYSTEM = 'long_poll:system';

    public const DOMAIN_MANAGE = 'domain:manage';
    public const DOMAIN_LIST_ACTIVE = 'domain:list_active';
    public const TOKEN_MANAGE = 'token:manage';
    public const STATS_VIEW = 'stats:view';
    public const CHECKER_RUN = 'checker:run';

    public const WEBHOOK_INGEST = 'webhook:ingest';

    /**
     * Return default permissions for a given role.
     *
     * @return string[]
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::ADMIN => [
                self::EMAIL_CREATE_SINGLE,
                self::EMAIL_CREATE_BATCH,
                self::EMAIL_CHECK_EXISTS,
                self::EMAIL_LIST_ALL,
                self::EMAIL_TOGGLE_DONE,
                self::EMAIL_UPDATE_NOTE,
                self::EMAIL_DELETE,
                self::MESSAGE_LIST_BY_EMAIL,
                self::MESSAGE_VIEW_DETAIL,
                self::MESSAGE_DELETE,
                self::LONG_POLL_MAILBOX,
                self::LONG_POLL_SYSTEM,
                self::DOMAIN_MANAGE,
                self::DOMAIN_LIST_ACTIVE,
                self::TOKEN_MANAGE,
                self::STATS_VIEW,
                self::CHECKER_RUN,
            ],
            Role::API_USER => [
                self::EMAIL_CREATE_SINGLE,
                self::EMAIL_CHECK_EXISTS,
                self::EMAIL_DELETE,
                self::MESSAGE_LIST_BY_EMAIL,
                self::MESSAGE_VIEW_DETAIL,
                self::MESSAGE_DELETE,
                self::LONG_POLL_MAILBOX,
                self::DOMAIN_LIST_ACTIVE,
            ],
            Role::PUBLIC_USER => [
                self::EMAIL_CREATE_SINGLE,
                self::EMAIL_DELETE,
                self::MESSAGE_LIST_BY_EMAIL,
                self::MESSAGE_VIEW_DETAIL,
                self::LONG_POLL_MAILBOX,
                self::DOMAIN_LIST_ACTIVE,
            ],
            Role::WEBHOOK => [
                self::WEBHOOK_INGEST,
            ],
            Role::ANONYMOUS => [
                self::DOMAIN_LIST_ACTIVE,
            ],
        };
    }
}
