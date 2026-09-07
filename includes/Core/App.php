<?php
declare(strict_types=1);

namespace KaiMail\Core;

require_once __DIR__ . '/bootstrap.php';

use PDO;
use Throwable;
use KaiMail\Core\Auth\Authenticator;
use KaiMail\Core\Http\ApiException;
use KaiMail\Core\Http\Request;
use KaiMail\Core\Http\Response;
use KaiMail\Core\Services\EmailService;
use KaiMail\Core\Services\MessageService;
use KaiMail\Core\Services\DomainService;
use KaiMail\Core\Services\StatsService;
use KaiMail\Core\Services\CheckerService;
use KaiMail\Core\Services\TokenService;

/**
 * Micro Application Kernel & Dependency Container.
 */
final class App
{
    private static ?PDO $db = null;
    private static array $services = [];

    public static function boot(): void
    {
        if (!defined('BASE_URL')) {
            require_once dirname(__DIR__, 2) . '/config/config.php';
        }
        if (!function_exists('getDB')) {
            require_once dirname(__DIR__, 2) . '/config/database.php';
        }

        if (self::$db === null) {
            self::$db = \getDB();
            Database\DatabaseOptimizer::ensureCoreIndexes(self::$db);
        }
    }

    public static function setDb(PDO $db): void
    {
        self::$db = $db;
        self::$services = [];
    }

    public static function getDb(): PDO
    {
        self::boot();
        return self::$db;
    }

    public static function getService(string $class): object
    {
        self::boot();

        if (isset(self::$services[$class])) {
            return self::$services[$class];
        }

        $service = match ($class) {
            EmailService::class => new EmailService(self::$db),
            MessageService::class => new MessageService(self::$db),
            DomainService::class => new DomainService(self::$db),
            StatsService::class => new StatsService(self::$db),
            CheckerService::class => new CheckerService(self::$db),
            TokenService::class => new TokenService(self::$db),
            default => throw new \InvalidArgumentException("Unknown service: {$class}"),
        };

        self::$services[$class] = $service;
        return $service;
    }

    public static function makeController(string $controllerClass): object
    {
        self::boot();

        return match ($controllerClass) {
            Controllers\EmailController::class => new Controllers\EmailController(
                self::getService(EmailService::class)
            ),
            Controllers\MessageController::class => new Controllers\MessageController(
                self::getService(MessageService::class),
                self::getService(EmailService::class)
            ),
            Controllers\DomainController::class => new Controllers\DomainController(
                self::getService(DomainService::class)
            ),
            Controllers\StatsController::class => new Controllers\StatsController(
                self::getService(StatsService::class)
            ),
            Controllers\CheckerController::class => new Controllers\CheckerController(
                self::getService(CheckerService::class)
            ),
            Controllers\TokenController::class => new Controllers\TokenController(
                self::getService(TokenService::class)
            ),
            Controllers\LongPollController::class => new Controllers\LongPollController(
                self::getDb()
            ),
            Controllers\AuthController::class => new Controllers\AuthController(),
            Controllers\WebhookController::class => new Controllers\WebhookController(
                self::getService(EmailService::class),
                self::getService(MessageService::class)
            ),
            default => throw new \InvalidArgumentException("Unknown controller: {$controllerClass}"),
        };
    }

    /**
     * Dispatch HTTP request to a controller action with automatic auth, error handling, and response emission.
     */
    public static function run(
        string $controllerClass,
        string $action = 'handle',
        ?string $requiredPermission = null
    ): void {
        self::boot();

        $request = Request::capture();

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $preflightResponse = Response::noContent();
            $preflightResponse->setCors($request, BASE_URL);
            $preflightResponse->send();
            return;
        }

        try {
            // Authenticate and authorize request
            $authContext = Authenticator::authenticate($request, $requiredPermission);

            // Instantiate controller and execute action
            $controller = self::makeController($controllerClass);
            $response = $controller->$action($request, $authContext);

            if ($response instanceof Response) {
                $response->setNoCache();
                $response->setCors($request, BASE_URL);
                $response->send();
            }
        } catch (ApiException $e) {
            $errorResponse = Response::error(
                $e->getMessage(),
                $e->getStatusCode(),
                $e->getErrorType(),
                $e->getDetails()
            );
            $errorResponse->setNoCache();
            $errorResponse->setCors($request, BASE_URL);
            $errorResponse->send();
        } catch (Throwable $e) {
            error_log("Unhandled Application Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

            $message = 'Đã xảy ra lỗi hệ thống';
            $details = [];
            if (defined('EXPOSE_ERROR_DETAILS') && EXPOSE_ERROR_DETAILS) {
                $message = $e->getMessage();
                $details = [
                    'exception' => get_class($e),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ];
            }

            $errorResponse = Response::error($message, 500, 'InternalServerError', $details);
            $errorResponse->setNoCache();
            $errorResponse->setCors($request, BASE_URL);
            $errorResponse->send();
        }
    }
}
