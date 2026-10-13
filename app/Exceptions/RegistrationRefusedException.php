<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised when a registration cannot be accepted — the event is full, or the alumni is already on the list (docs/project.md F4).
 */
class RegistrationRefusedException extends Exception {}
