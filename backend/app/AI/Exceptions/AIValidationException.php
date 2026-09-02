<?php

namespace App\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when an AI response cannot be trusted: malformed JSON, missing
 * required fields, or values outside the allowed enum. Per the "AI is
 * untrusted input" principle, callers must catch this and fall back to
 * a safe default / manual review rather than let bad AI output reach
 * the database.
 */
class AIValidationException extends RuntimeException {}
