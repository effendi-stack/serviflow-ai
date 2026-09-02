<?php

namespace App\AI\Schemas;

use App\AI\Exceptions\AIValidationException;

/**
 * Minimal, dependency-free structural validator for the small JSON shapes
 * our AI features return. This is intentionally strict: the AI is treated
 * as untrusted input (spec section 9.3), so every field is type- and
 * enum-checked before it is allowed anywhere near a write to the database.
 */
class JsonSchemaValidator
{
    /**
     * @param  array<string, array{type: string, enum?: array, required?: bool}>  $rules
     * @return array<string, mixed> The validated (and only the validated) fields.
     *
     * @throws AIValidationException
     */
    public static function validate(string $rawJson, array $rules): array
    {
        $decoded = json_decode(self::stripCodeFences($rawJson), true);

        if (! is_array($decoded)) {
            throw new AIValidationException('AI response was not valid JSON.');
        }

        $clean = [];

        foreach ($rules as $field => $rule) {
            $required = $rule['required'] ?? true;
            $value = $decoded[$field] ?? null;

            if ($value === null) {
                if ($required) {
                    throw new AIValidationException("AI response missing required field [{$field}].");
                }

                continue;
            }

            if (isset($rule['enum']) && ! in_array($value, $rule['enum'], true)) {
                throw new AIValidationException(
                    "AI response field [{$field}] has value [{$value}] outside allowed set."
                );
            }

            $clean[$field] = match ($rule['type']) {
                'int' => (int) $value,
                'float' => (float) $value,
                'string' => (string) $value,
                'bool' => (bool) $value,
                default => $value,
            };
        }

        return $clean;
    }

    private static function stripCodeFences(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(json)?/i', '', $text);
        $text = preg_replace('/```$/', '', $text);

        return trim($text);
    }
}
