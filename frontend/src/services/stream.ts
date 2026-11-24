import { api } from './api'

/**
 * POSTs to a Server-Sent Events endpoint and reports text as the model
 * generates it. EventSource can't send a POST body or a bearer token, so
 * this reads the response stream with fetch instead.
 *
 * Events: "token" (a text fragment), then "done" (final payload, resolved)
 * or "error" (rejected).
 */
export class StreamError extends Error {
  constructor(
    message: string,
    public status?: number,
  ) {
    super(message)
  }
}

export async function postStream<TDone>(
  path: string,
  body: unknown,
  onToken: (text: string) => void,
  signal?: AbortSignal,
): Promise<TDone> {
  const token = localStorage.getItem('serviflow_token')
  const response = await fetch(`${api.defaults.baseURL}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'text/event-stream',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body ?? {}),
    signal,
  })

  if (response.status === 401) {
    localStorage.removeItem('serviflow_token')
    window.location.href = '/login'
    throw new StreamError('Unauthenticated', 401)
  }

  if (!response.ok || !response.body) {
    // Validation and authorization errors arrive as normal JSON before
    // the stream starts.
    const data = await response.json().catch(() => null)
    throw new StreamError(data?.message ?? `Request failed (${response.status})`, response.status)
  }

  const reader = response.body.pipeThrough(new TextDecoderStream()).getReader()
  let buffer = ''

  for (;;) {
    const { value, done } = await reader.read()
    if (done) break
    buffer += value

    let boundary: number
    while ((boundary = buffer.indexOf('\n\n')) !== -1) {
      const raw = buffer.slice(0, boundary)
      buffer = buffer.slice(boundary + 2)

      const event = /^event: (.*)$/m.exec(raw)?.[1]
      const dataLine = /^data: (.*)$/m.exec(raw)?.[1]
      if (!event || dataLine === undefined) continue
      const data = JSON.parse(dataLine)

      if (event === 'token') onToken(data as string)
      else if (event === 'done') return data as TDone
      else if (event === 'error') throw new StreamError(data?.message ?? 'Generation failed.')
    }
  }

  throw new StreamError('The connection closed before the answer finished.')
}
