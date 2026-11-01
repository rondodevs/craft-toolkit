/** A screen's Save: posts the form, shows Craft's toast and hands the stored props back to the screen */
export function useScreenSave<P>(actionName: string, onSaved: (props: P) => void) {
  const saving = ref(false)

  async function save(data: unknown) {
    saving.value = true
    const result = await action<{ props?: P }>(actionName, data)
    saving.value = false
    notify(result)

    if (result.ok && result.data.props)
      onSaved(result.data.props)

    return result
  }

  return { save, saving }
}
