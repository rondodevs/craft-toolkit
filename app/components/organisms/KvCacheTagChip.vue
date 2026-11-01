<script setup lang="ts">
interface KvCacheTagChipProps {
  tag: string
}

defineProps<KvCacheTagChipProps>()

const explorer = useKvCacheExplorer()
</script>

<template>
  <Chip
    :label="tag"
    :selected="explorer.selectedTags.value.includes(tag)"
    :title="explorer.selectedTags.value.includes(tag) ? 'Remove from the purge picker' : 'Add to the purge picker on the &quot;Purge Cache&quot; tab'"
    @click="explorer.toggleTag(tag)"
  />
</template>
