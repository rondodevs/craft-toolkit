import type { ActionResult } from '~/utils/action'

interface AsyncActionOptions {
  /** Asked with confirm() first; the action doesn't run on Cancel */
  confirm?: string | (() => string)
  idle?: string
  pending?: string
}

/** A button's action: pending while it runs, and its last message as the status line */
export function useAsyncAction<T = Record<string, unknown>>(
  name: MaybeRefOrGetter<string>,
  options: AsyncActionOptions = {},
) {
  const pending = ref(false)
  const status = ref({ message: options.idle ?? 'ready', error: false })
  const result = shallowRef<ActionResult<T>>()

  async function run(data?: unknown): Promise<ActionResult<T> | undefined> {
    const question = typeof options.confirm === 'function' ? options.confirm() : options.confirm

    // The browser's own dialog, as Craft uses for its destructive actions
    // eslint-disable-next-line no-alert
    if (question && !window.confirm(question))
      return

    pending.value = true
    status.value = { message: options.pending ?? 'working…', error: false }
    result.value = await action<T>(toValue(name), data)
    status.value = { message: result.value.message || (result.value.ok ? 'done' : 'failed'), error: !result.value.ok }
    pending.value = false

    return result.value
  }

  return { run, pending, status, result }
}
