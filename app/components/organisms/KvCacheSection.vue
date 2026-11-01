<script setup lang="ts">
import type { Json } from '~/utils/kvCache'

interface KvCacheSectionProps {
  name: string
  value: Json
  siblings: number
}

const props = defineProps<KvCacheSectionProps>()

const explorer = useKvCacheExplorer()
const open = ref(props.siblings <= 3)

// The filter opens the sections where it matches
watch(explorer.filter, (term) => {
  const normalized = term.trim().toLowerCase()

  if (normalized && JSON.stringify(props.value).toLowerCase().includes(normalized))
    open.value = true
})
</script>

<template>
  <Disclosure v-model:open="open" :label="name" :badge="countLabel(value)">
    <KvCacheNode :value />
  </Disclosure>
</template>
