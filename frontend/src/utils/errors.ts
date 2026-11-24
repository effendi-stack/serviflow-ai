import { isAxiosError } from 'axios'
import { StreamError } from '../services/stream'

/**
 * A user-facing message for a failed API call. Prefers the validation
 * error for `field`, then the API's message, then `fallback`. Handles
 * both axios errors and errors from streaming endpoints.
 */
export function apiErrorMessage(err: unknown, field: string | null, fallback: string): string {
  if (isAxiosError(err)) {
    const data = err.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    return (field ? data?.errors?.[field]?.[0] : undefined) ?? data?.message ?? fallback
  }

  if (err instanceof StreamError) {
    return err.message
  }

  return fallback
}
