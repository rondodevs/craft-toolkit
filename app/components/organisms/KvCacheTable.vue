<script setup lang="ts">
import type { Json } from '~/utils/kvCache'

interface KvCacheTableProps {
  items: Json[]
}

const props = defineProps<KvCacheTableProps>()

const MAX_ROWS = 500
const TAGS_INLINE = 3

const explorer = useKvCacheExplorer()

const rows = computed(() => props.items.map(item => ({
  item,
  key: entryKey(item),
  info: entryInfo(item),
  tags: [...collectTags(item)],
  search: JSON.stringify(item).toLowerCase(),
})))

const visible = computed(() => {
  const term = explorer.filter.value.trim().toLowerCase()

  return rows.value.filter(row => term === '' || row.search.includes(term)).slice(0, MAX_ROWS)
})

const purgeable = computed(() => rows.value.some(row => row.key))
const purging = ref<string | null>(null)
const failed = ref<string | null>(null)

async function purge(key: string, item: Json) {
  purging.value = key
  const result = await action('toolkit/kv-cache/purge-keys', { cacheType: 'data', keys: [key] })
  purging.value = null

  if (result.ok)
    explorer.removeRows(other => other === item)
  else
    failed.value = key
}

const raw = ref<{ title: string, value: Json } | null>(null)
const tagsModal = ref<string[] | null>(null)
</script>

<template>
  <div class=":uno: border border-hairline rounded-sm max-h-90 overflow-auto">
    <table class=":uno: text-3 w-full border-collapse [&_td]:px-2 [&_td]:py-1 [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_td]:align-top [&_td]:border-b [&_th]:border-b [&_td]:border-hairline [&_th]:border-hairline [&_th]:bg-gray-050 [&_th]:top-0 [&_th]:sticky">
      <thead>
        <tr>
          <th>Key</th>
          <th>Title</th>
          <th>Entry ID</th>
          <th>Cache tags</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in visible" :key="row.key ?? row.search">
          <td class=":uno: text-2.75 font-mono max-w-80 truncate">
            {{ row.key ?? 'none' }}
          </td>
          <td class=":uno: max-w-90 break-words">
            <template v-if="row.info">
              {{ row.info.title }}
              <span v-if="row.info.extraCount" class=":uno: text-2.75 text-light">(+{{ row.info.extraCount }} more)</span>
            </template>
            <span v-else class="light">none</span>
          </td>
          <td>{{ row.info?.id ?? '' }}</td>
          <td class=":uno: max-w-90">
            <div v-if="row.tags.length" class=":uno: flex flex-wrap gap-1">
              <KvCacheTagChip v-for="tag in row.tags.slice(0, TAGS_INLINE)" :key="tag" :tag />
              <Chip v-if="row.tags.length > TAGS_INLINE" :label="`+${row.tags.length - TAGS_INLINE} more`" @click="tagsModal = row.tags" />
            </div>
            <span v-else class="light">none</span>
          </td>
          <td class=":uno: whitespace-nowrap">
            <div class=":uno: flex gap-1">
              <Btn small @click="raw = { title: `Raw JSON: ${row.key ?? row.info?.title ?? ''}`, value: row.item }">
                Raw JSON
              </Btn>
              <Btn
                v-if="row.key"
                small
                :loading="purging === row.key"
                :title="`Purge cache entry: ${row.key}`"
                @click="purge(row.key, row.item)"
              >
                {{ failed === row.key ? 'Failed' : 'Purge' }}
              </Btn>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <Note v-if="items.length > MAX_ROWS">
      Showing first {{ MAX_ROWS }} of {{ items.length }} rows. Use the filter above to narrow down results.
    </Note>
    <Note v-if="!purgeable">
      No "key" field found on these entries, so per-row purge is not available here. Use "Purge by tags" instead.
    </Note>
  </div>

  <JsonModal v-if="raw" :model-value="true" :title="raw.title" :value="raw.value" @update:model-value="raw = null" />

  <Modal :model-value="!!tagsModal" :title="`Cache tags (${tagsModal?.length})`" @update:model-value="tagsModal = null">
    <p class="light">
      Click a tag to add or remove it from the picker on the "Purge Cache" tab.
    </p>
    <div class=":uno: flex flex-wrap gap-1">
      <KvCacheTagChip v-for="tag in tagsModal" :key="tag" :tag />
    </div>
  </Modal>
</template>
