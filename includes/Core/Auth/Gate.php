<?php
declare(strict_types=1);

namespace KaiMail\Core\Auth;

use KaiMail\Core\Http\ApiException;

/**
 * Authorization Gate for evaluating granular permissions and resources.
 */
final class Gate
{
    /**
     * Determine whether the given permission is granted to the auth context.
     */
    public static function allows(AuthContext $context, string $permission, mixed $resource = null): bool
    {
        // Admin has superuser authority across all resources
        if ($context->isAdmin()) {
            return true;
        }

        if (!$context->can($permission)) {
            return false;
        }

        // Custom resource ownership checks (ABAC logic)
        if ($resource !== null) {
            return self::checkResourceOwnership($context, $permission, $resource);
        }

        return true;
    }

    public static function denies(AuthContext $context, string $permission, mixed $resource = null): bool
    {
        return !self::allows($context, $permission, $resource);
    }

    /**
     * Authorize action or throw standardized ApiException.
     */
    public static function authorize(
        AuthContext $context,
        string $permission,
        mixed $resource = null,
        string $message = 'Không có quyền thực hiện hành động này'
    ): void {
        if (!$context->isAuthenticated()) {
            throw ApiException::unauthorized('Yêu cầu xác thực trước khi truy cập');
        }

        if (self::denies($context, $permission, $resource)) {
            throw ApiException::forbidden($message);
        }
    }

    private static function checkResourceOwnership(AuthContext $context, string $permission, mixed $resource): bool
    {
        // Example: message detail read - ensure message recipient matches if specified
        if ($permission === Permission::MESSAGE_VIEW_DETAIL || $permission === Permission::MESSAGE_DELETE) {
            if (is_array($resource) && isset($resource['recipient'], $resource['requested_email'])) {
                return strtolower(trim((string) $resource['recipient'])) === strtolower(trim((string) $resource['requested_email']));
            }
        }

        return true;
    }
}
