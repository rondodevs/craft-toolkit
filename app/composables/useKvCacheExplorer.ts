import type { InjectionKey } from 'vue'
import type { Json, JsonObject } from '~/utils/kvCache'

/** The explorer's state, shared by its tabs: the last stats, the filter and the tags picked to purge */
export function createKvCacheExplorer() {
  const details = ref<JsonObject | null>(null)
  const status = ref({ message: 'checking…', error: false })
  const loading = ref(false)
  const filter = ref('')
  const selectedTags = ref<string[]>([])
  const tags = computed(() => allTags(details.value))

  async function refresh() {
    loading.value = true
    status.value = { message: 'checking…', error: false }

    const result = await action<{ details?: JsonObject | null }>('toolkit/kv-cache/check')

    status.value = { message: result.message || 'check failed', error: !result.ok }
    details.value = isObject(result.data.details) ? result.data.details : null
    filter.value = ''
    loading.value = false
  }

  function toggleTag(tag: string) {
    selectedTags.value = selectedTags.value.includes(tag)
      ? selectedTags.value.filter(other => other !== tag)
      : [...selectedTags.value, tag]
  }

  function addTag(tag: string) {
    if (!selectedTags.value.includes(tag))
      selectedTags.value = [...selectedTags.value, tag]
  }

  // Purged entries leave the list without a new check
  function removeRows(match: (item: Json) => boolean) {
    const rows = details.value?.rows

    if (Array.isArray(rows))
      details.value = { ...details.value, rows: rows.filter(item => !match(item)) }
  }

  return { details, status, loading, filter, selectedTags, tags, refresh, toggleTag, addTag, removeRows }
}

export type KvCacheExplorer = ReturnType<typeof createKvCacheExplorer>

export const kvCacheExplorerKey: InjectionKey<KvCacheExplorer> = Symbol('kvCacheExplorer')

export function useKvCacheExplorer(): KvCacheExplorer {
  return inject(kvCacheExplorerKey)!
}
