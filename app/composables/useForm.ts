// Props come from JSON: a JSON copy also drops the reactive proxies structuredClone can't take
function copy<T>(value: T): T {
  return JSON.parse(JSON.stringify(value))
}

/** A screen's editable copy of its props: reset to them when a save sends them back */
export function useForm<T>(source: () => T) {
  const form = ref(copy(source())) as Ref<T>

  watch(source, (value) => {
    form.value = copy(value)
  })

  return form
}
