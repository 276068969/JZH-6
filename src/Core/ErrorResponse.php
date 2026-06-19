<?php

declare(strict_types=1);

namespace App\Core;

final class ErrorResponse
{
    public const CODE_INVALID_PARAMS = 'INVALID_PARAMS';
    public const CODE_MISSING_PARAMS = 'MISSING_PARAMS';
    public const CODE_UNAUTHORIZED = 'UNAUTHORIZED';
    public const CODE_FORBIDDEN = 'FORBIDDEN';
    public const CODE_NOT_FOUND = 'NOT_FOUND';
    public const CODE_VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const CODE_DATABASE_ERROR = 'DATABASE_ERROR';
    public const CODE_BUSINESS_ERROR = 'BUSINESS_ERROR';
    public const CODE_SYSTEM_ERROR = 'SYSTEM_ERROR';

    public static function sendJsonError(
        int $httpStatusCode,
        string $errorCode,
        string $message,
        array $errors = []
    ): void {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($httpStatusCode);
        echo json_encode([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function invalidParams(array $errors = [], string $message = '请求参数无效'): void
    {
        self::sendJsonError(400, self::CODE_INVALID_PARAMS, $message, $errors);
    }

    public static function missingParams(array $errors = [], string $message = '缺少必要参数'): void
    {
        self::sendJsonError(400, self::CODE_MISSING_PARAMS, $message, $errors);
    }

    public static function unauthorized(string $message = '请先登录'): void
    {
        self::sendJsonError(401, self::CODE_UNAUTHORIZED, $message, [$message]);
    }

    public static function forbidden(string $message = '无权限执行此操作'): void
    {
        self::sendJsonError(403, self::CODE_FORBIDDEN, $message, [$message]);
    }

    public static function notFound(string $message = '资源不存在'): void
    {
        self::sendJsonError(404, self::CODE_NOT_FOUND, $message, [$message]);
    }

    public static function validationError(array $errors, string $message = '数据校验失败'): void
    {
        self::sendJsonError(422, self::CODE_VALIDATION_ERROR, $message, $errors);
    }

    public static function databaseError(string $message = '数据操作失败，请稍后重试'): void
    {
        self::sendJsonError(500, self::CODE_DATABASE_ERROR, $message, [$message]);
    }

    public static function businessError(string $message, array $errors = []): void
    {
        self::sendJsonError(422, self::CODE_BUSINESS_ERROR, $message, $errors);
    }

    public static function systemError(string $message = '系统暂时不可用，请稍后重试'): void
    {
        self::sendJsonError(500, self::CODE_SYSTEM_ERROR, $message, [$message]);
    }

    public static function isApiRequest(string $path): bool
    {
        return str_starts_with($path, '/api/');
    }

    public static function handleException(\Throwable $e, string $path): void
    {
        error_log('未捕获异常: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

        if (self::isApiRequest($path)) {
            self::systemError();
        } else {
            http_response_code(500);
            echo '系统暂时不可用，请稍后重试。';
        }
    }
}
