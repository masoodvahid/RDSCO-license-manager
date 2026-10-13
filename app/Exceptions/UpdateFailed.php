<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An expected, user-facing failure of the in-app updater.
 *
 * The message is shown to the administrator as-is, so it must never contain
 * secrets (tokens, paths outside the project, raw upstream responses).
 */
class UpdateFailed extends RuntimeException {}
