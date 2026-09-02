<?php

namespace App\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when the underlying AI provider fails (network error, non-2xx,
 * timeout). Never thrown for "the AI gave a weird answer" -- that's an
 * AIValidationException instead, because it means the AI is untrusted
 * input, not that the transport failed.
 */
class AIProviderException extends RuntimeException {}
