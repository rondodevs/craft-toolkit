<script setup lang="ts">
const explorer = useKvCacheExplorer()

const options = computed(() => explorer.tags.value.map(tag => ({ id: tag })))
const { run, pending, status } = useAsyncAction('toolkit/kv-cache/purge-tags', { pending: 'purging…' })

async function purge() {
  const tags = [...explorer.selectedTags.value]

  if (!tags.length) {
    status.value = { message: 'Pick or type at least one tag.', error: true }
    return
  }

  const result = await run({ tags })

  if (result?.ok) {
    status.value = { message: 'purged', error: false }
    explorer.removeRows(item => [...collectTags(item)].some(tag => tags.includes(tag)))
    explorer.selectedTags.value = []
  }
}
</script>

<template>
  <div class="field">
    <p><strong>Purge by tags</strong></p>
    <p class="light">
      Search cache tags discovered by the last connection check, or type a custom tag and press Enter.
    </p>

    <div v-if="explorer.selectedTags.value.length" class=":uno: mb-2 flex flex-wrap gap-1.5">
      <Chip
        v-for="tag in explorer.selectedTags.value"
        :key="tag"
        :label="tag"
        removable
        @remove="explorer.toggleTag(tag)"
      />
    </div>

    <Combobox
      v-model="explorer.selectedTags.value"
      :options
      :text="option => option.id"
      placeholder="Search cache tags..."
      allow-custom
      @custom="explorer.addTag"
    >
      <template #empty="{ query }">
        <span class=":uno: italic">
          {{ query.trim()
            ? `No matching tags. Press Enter to use "${query}" as a custom tag.`
            : 'No cache tags discovered yet. Run a connection check, or type a tag and press Enter.' }}
        </span>
      </template>
      <template #option="{ option }">
        <span class=":uno: text-3">{{ option.id }}</span>
        <span v-if="explorer.selectedTags.value.includes(option.id)">✓</span>
      </template>
    </Combobox>

    <div class=":uno: my-2.5">
      <StatusLine :message="status.message" :error="status.error" />
    </div>
    <Btn variant="submit" :loading="pending" @click="purge">
      Purge tags
    </Btn>
  </div>
</template>
