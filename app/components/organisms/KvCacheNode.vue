<script setup lang="ts">
import type { Json } from '~/utils/kvCache'

// One value of the stats, shown by its shape: a table of entries, a list, fields, or sections
interface KvCacheNodeProps {
  value: Json
}

const props = defineProps<KvCacheNodeProps>()

const MAX_ITEMS = 500

// `{ total, rows }` alone is shown as its list
const unwrapped = computed<Json>(() => {
  const counted = countedList(props.value)

  return counted && isObject(props.value) && Object.keys(props.value).length === 2 ? props.value[counted.key]! : props.value
})

const kind = computed(() => {
  const value = unwrapped.value

  if (Array.isArray(value)) {
    if (!value.length)
      return 'empty'

    return isObject(value[0]) ? 'table' : 'list'
  }

  if (isObject(value))
    return Object.values(value).every(v => !v || typeof v !== 'object') ? 'fields' : 'sections'

  return 'scalar'
})

const sections = computed(() => {
  const value = unwrapped.value as Record<string, Json>
  const counted = countedList(value)

  return Object.keys(value).filter(key => !(counted && key === 'total'))
})
</script>

<template>
  <Note v-if="kind === 'empty'">
    (empty list)
  </Note>
  <KvCacheTable v-else-if="kind === 'table'" :items="unwrapped as Json[]" />
  <div v-else-if="kind === 'list'">
    {{ (unwrapped as Json[]).slice(0, MAX_ITEMS).map(formatScalar).join(', ') || '(empty)' }}
    <Note v-if="(unwrapped as Json[]).length > MAX_ITEMS">
      Showing first {{ MAX_ITEMS }} of {{ (unwrapped as Json[]).length }} items.
    </Note>
  </div>
  <dl v-else-if="kind === 'fields'" class=":uno: text-3 m-0 gap-x-3 gap-y-1 grid grid-cols-[max-content_1fr]">
    <template v-for="(item, key) in (unwrapped as Record<string, Json>)" :key>
      <dt class=":uno: font-600 whitespace-nowrap">
        {{ key }}
      </dt>
      <dd class=":uno: m-0 break-words">
        {{ item === null || item === undefined ? '' : formatScalar(item) }}
      </dd>
    </template>
  </dl>
  <div v-else-if="kind === 'sections'">
    <KvCacheSection
      v-for="key in sections"
      :key
      :name="key"
      :value="(unwrapped as Record<string, Json>)[key]!"
      :siblings="sections.length"
    />
  </div>
  <div v-else>
    {{ formatScalar(unwrapped) }}
  </div>
</template>
