<?php

declare(strict_types=1);

namespace App\Logging\Processors;

use Illuminate\Http\Request;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Puts the current HTTP {@code X-Request-Id} into the log record {@code extra} for {@see LineFormatter} {@code %extra.x_request_id%}.
 */
final class XRequestIdLogProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $id = '';
        if (app()->bound('request') && app('request') instanceof Request) {
            $header = app('request')->header('X-Request-Id');
            $id = is_string($header) ? $header : '';
        }
        $record->extra['x_request_id'] = $id;

        return $record;
    }
}
