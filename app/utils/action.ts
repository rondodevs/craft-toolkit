export interface ActionResult<T = Record<string, unknown>> {
  ok: boolean
  message: string
  data: T & { message?: string }
}

/** Craft's action requests, as a result instead of a throw: screens show the message either way */
export async function action<T = Record<string, unknown>>(
  name: string,
  data?: unknown,
  method: 'GET' | 'POST' = 'POST',
): Promise<ActionResult<T>> {
  try {
    const response = await Craft.sendActionRequest<T & { message?: string }>(
      method,
      name,
      method === 'GET' ? { params: data as Record<string, unknown> } : { data },
    )

    return { ok: true, message: response.data?.message ?? '', data: response.data }
  }
  catch (error: any) {
    const body = error?.response?.data ?? {}

    return { ok: false, message: body.message || 'Request failed.', data: body }
  }
}

/** The toast Craft shows after a save */
export function notify(result: Pick<ActionResult, 'ok' | 'message'>) {
  if (result.ok)
    Craft.cp?.displaySuccess(result.message)
  else
    Craft.cp?.displayError(result.message)
}

export function runQueue() {
  Craft.cp?.runQueue?.()
}
