<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Missing dependency configuration (env / service not wired) for request handling.
 */
final class ConfigurationMissingException extends Exception {}
