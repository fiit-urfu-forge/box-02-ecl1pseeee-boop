<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;

/**
 * Configures every Monolog handler on the passed-in logger with a
 * JSON formatter. Output shape matches §10.1 of SPEC:
 *   { "timestamp": ISO8601, "level": "info", "channel": "...",
 *     "message": "...", "context": { ... } }
 *
 * Used from config/logging.php via the `tap` key on each channel.
 */
class JsonFormatterFactory
{
    public function __invoke(Logger $logger): void
    {
        $formatter = new JsonFormatter(
            batchMode: JsonFormatter::BATCH_MODE_JSON,
            appendNewline: true,
        );
        // Full context + extras, drop internal stacks we do not want to leak.
        $formatter->includeStacktraces(false);
        $formatter->setDateFormat(\DateTimeInterface::ATOM);

        foreach ($logger->getHandlers() as $handler) {
            if (method_exists($handler, 'setFormatter')) {
                $handler->setFormatter($formatter);
            }
        }
    }
}
