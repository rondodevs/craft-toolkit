<script setup lang="ts">
const explorer = useKvCacheExplorer()

const sections = computed(() => {
  const details = explorer.details.value

  if (!details)
    return []

  const keys = Object.keys(details).filter(key => key !== 'status' && key !== 'total')

  return keys.length ? keys : Object.keys(details)
})

const showRaw = ref(false)
const { copy, copied } = useClipboard({ copiedDuring: 1500 })
</script>

<template>
  <div class="field">
    <p><strong>Cache Explorer</strong></p>
    <p class="light">
      Browses the entries currently reported by the frontend stats endpoint. Refreshed automatically when this page loads, even if invalidation is disabled.
    </p>

    <div class=":uno: mb-2.5 flex flex-wrap gap-2 items-center">
      <input
        v-model="explorer.filter.value"
        type="text"
        class=":uno: text flex-[1_1_auto] max-w-80"
        placeholder="Filter keys, titles, urls, tags..."
      >
      <Btn small :disabled="!explorer.details.value" @click="showRaw = true">
        View raw JSON
      </Btn>
      <Btn small :disabled="!explorer.details.value" @click="copy(JSON.stringify(explorer.details.value, null, 2))">
        {{ copied ? 'Copied!' : 'Copy JSON' }}
      </Btn>
    </div>

    <div class=":uno: mb-2.5">
      <Disclosure v-if="explorer.loading.value" :open="true" label="rows" badge="loading…">
        <Skeleton />
      </Disclosure>
      <Note v-else-if="!explorer.details.value" :error="explorer.status.value.error">
        {{ explorer.status.value.error ? explorer.status.value.message : 'No cache data returned by the endpoint.' }}
      </Note>
      <Note v-else-if="!sections.length">
        No cache data returned by the endpoint.
      </Note>
      <template v-else>
        <KvCacheSection
          v-for="key in sections"
          :key
          :name="key"
          :value="explorer.details.value[key]!"
          :siblings="sections.length"
        />
      </template>
    </div>

    <Btn :loading="explorer.loading.value" @click="explorer.refresh()">
      Refresh
    </Btn>
  </div>

  <JsonModal v-model="showRaw" title="Raw JSON" :value="explorer.details.value" />
</template>
