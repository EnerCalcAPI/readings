<?php

declare(strict_types=1);

namespace Enercalcapi\Readings\Traits;

trait HttpStatusCodes
{
    public static function isHttpClientError(int $errorCode): bool
    {
        return ($errorCode >= 400 && $errorCode < 500);
    }
 
    public static function isHttpError(int $errorCode): bool
    {
        return !self::isHttpSuccess($errorCode);
    }
 
    public static function isHttpInformational(int $errorCode): bool
    {
        return ($errorCode >= 100 && $errorCode < 200);
    }
 
    public static function isHttpRedirection(int $errorCode): bool
    {
        return ($errorCode >= 300 && $errorCode < 400);
    }
 
    public static function isHttpServerError(int $errorCode): bool
    {
        return ($errorCode >= 500 && $errorCode < 600);
    }
 
    public static function isHttpSuccess(int $errorCode): bool
    {
        return ($errorCode >= 200 && $errorCode < 300);
    }
    
    /**
     * Function getHttpStatus() returns HTTP error code with information describing the code
     *
     * @param object $errorCode
     * @return string
     */
    public static function getHttpStatusCode(array $errorCode): int
    {
        return $errorCode['status'];
    }

    /**
     * Function getHttpStatus() returns HTTP error code with information describing the code
     *
     * @param object $errorCode
     * @return string
     */
    public static function getHttpStatusMessage(object $errorCode): string
    {
        $httpMessages = [
            // 1xx informational response
            100 => 'HTTP_CONTINUE',
            101 => 'HTTP_SWITCHING_PROTOCOLS',
            102 => 'HTTP_PROCESSING',
            103 => 'HTTP_EARLY_HINTS',
            // 2xx success
            200 => 'HTTP_OK',
            201 => 'HTTP_CREATED',
            202 => 'HTTP_ACCEPTED',
            203 => 'HTTP_NON_AUTHORITATIVE_INFORMATION',
            204 => 'HTTP_NO_CONTENT',
            205 => 'HTTP_RESET_CONTENT',
            206 => 'HTTP_PARTIAL_CONTENT',
            207 => 'HTTP_MULTI_STATUS',
            208 => 'HTTP_ALREADY_REPORTED',
            226 => 'HTTP_IM_USED',
            // 3xx redirection
            300 => 'HTTP_MULTIPLE_CHOICES',
            301 => 'HTTP_MOVED_PERMANENTLY',
            302 => 'HTTP_FOUND',
            303 => 'HTTP_SEE_OTHER',
            304 => 'HTTP_NOT_MODIFIED',
            305 => 'HTTP_USE_PROXY',
            306 => 'HTTP_SWITCH_PROXY',
            307 => 'HTTP_TEMPORARY_REDIRECT',
            308 => 'HTTP_PERMANENT_REDIRECT',
            // 4xx client errors
            400 => 'HTTP_BAD_REQUEST',
            401 => 'HTTP_UNAUTHORIZED',
            402 => 'HTTP_PAYMENT_REQUIRED',
            403 => 'HTTP_FORBIDDEN',
            404 => 'HTTP_NOT_FOUND',
            405 => 'HTTP_METHOD_NOT_ALLOWED',
            406 => 'HTTP_NOT_ACCEPTABLE',
            407 => 'HTTP_PROXY_AUTHENTICATION_REQUIRED',
            408 => 'HTTP_REQUEST_TIMEOUT',
            409 => 'HTTP_CONFLICT',
            410 => 'HTTP_GONE',
            411 => 'HTTP_LENGTH_REQUIRED',
            412 => 'HTTP_PRECONDITION_FAILED',
            413 => 'HTTP_PAYLOAD_TOO_LARGE',
            414 => 'HTTP_URI_TOO_LONG',
            415 => 'HTTP_UNSUPPORTED_MEDIA_TYPE',
            416 => 'HTTP_RANGE_NOT_SATISFIABLE',
            417 => 'HTTP_EXPECTATION_FAILED',
            418 => 'HTTP_IM_A_TEAPOT',
            421 => 'HTTP_MISIDRECTED_REQUEST',
            422 => 'HTTP_UNPROCESSABLE_ENTITY',
            423 => 'HTTP_LOCKED',
            424 => 'HTTP_FAILED_DEPENDENCY',
            425 => 'HTTP_TOO_EARLY',
            426 => 'HTTP_UPGRADE_REQUIRED',
            428 => 'HTTP_PRECONDITION_REQUIRED',
            429 => 'HTTP_TOO_MANY_REQUESTS',
            431 => 'HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE',
            451 => 'HTTP_UNAVIALBLE_FOR_LEGAL_REASONS',
            // 5xx server errors
            500 => 'HTTP_INTERNAL_SERVER_ERROR',
            501 => 'HTTP_NOT_IMPLEMENTED',
            502 => 'HTTP_BAD_GATEWAY',
            503 => 'HTTP_SERVICE_UNAVAILABLE',
            504 => 'HTTP_GATEWAY_TIMEOUT',
            505 => 'HTTP_VERSION_NOT_SUPPORTED',
            506 => 'HTTP_VARIANT_ALSO_NEGOTIATES',
            507 => 'HTTP_INSUFFICIENT_STORAGE',
            508 => 'HTTP_LOOP_DETECTED',
            510 => 'HTTP_NOT_EXTENDED',
            511 => 'HTTP_NETWORK_AUTHENTICATION_REQUIRED',
            // 9xx fallback
            999 => 'HTTP_UNKNOWN_STATUS',
        ];

        if (array_key_exists($errorCode->getStatusCode(), $httpMessages)) {
            return $httpMessages[$errorCode->getStatusCode()];
        }

        return '';
    }
}