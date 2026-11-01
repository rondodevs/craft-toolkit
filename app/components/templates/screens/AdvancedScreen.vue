<script setup lang="ts">
interface AdvancedScreenProps {
  kvCacheUrl: string
  cloudflareUrl: string
}

defineProps<AdvancedScreenProps>()

interface Purge {
  action: string
  label: string
  confirm: string
}

const all: Purge = {
  action: 'toolkit/purge/all',
  label: 'Purge all caches',
  confirm: 'Clear Craft\'s caches, flush the KV cache and purge the whole Cloudflare zone?',
}

const layers: Purge[] = [
  { action: 'toolkit/purge/craft', label: 'Craft caches only', confirm: 'Clear all of Craft\'s caches?' },
  { action: 'toolkit/kv-cache/flush-kv', label: 'KV cache only', confirm: 'Flush the whole KV cache?' },
  { action: 'toolkit/cloudflare/purge-everything', label: 'Cloudflare only', confirm: 'Purge the whole Cloudflare zone?' },
]

const current = ref<Purge>(all)
const { run, pending, status } = useAsyncAction(() => current.value.action, {
  confirm: () => current.value.confirm,
  pending: 'purging…',
})

// One purge at a time: they would race on the same caches
function purge(item: Purge) {
  current.value = item
  run()
}
</script>

<template>
  <p class="light">
    Empties the caches right away, even if automatic invalidation or Cloudflare purging is switched off. "Purge all caches" goes from the origin outwards: Craft first, so the <a :href="kvCacheUrl">KV cache</a> refills from fresh data, then the KV cache, then <a :href="cloudflareUrl">Cloudflare</a>. Purged before the KV cache, Cloudflare would refetch pages built from stale data.
  </p>

  <StatusLine :message="status.message" :error="status.error" />

  <div class="field">
    <Btn variant="submit" :loading="pending && current === all" :disabled="pending" @click="purge(all)">
      {{ all.label }}
    </Btn>
  </div>

  <hr>

  <div class="field">
    <p><strong>Single layers</strong></p>
    <p class="light">
      "Craft caches" clears everything in Craft's Caches utility: data and GraphQL caches, compiled templates, asset indexes, CP resources and temp files.
    </p>
    <div class="buttons">
      <Btn
        v-for="item in layers"
        :key="item.action"
        :loading="pending && current === item"
        :disabled="pending"
        @click="purge(item)"
      >
        {{ item.label }}
      </Btn>
    </div>
  </div>
</template>
