<script setup lang="ts">
import type { SelectOption } from '~/components/atoms/SelectInput.vue'

interface UnusedAsset {
  id: number
  title: string
  filename: string
  cpEditUrl: string
  thumbUrl: string | null
  size: number
  dateCreated: string
}

interface UnusedAssetsResult {
  volumeName: string
  total: number
  assets: UnusedAsset[]
}

interface UnusedAssetsScreenProps {
  volumes: SelectOption[]
  volumeId: number | null
  usedWhen: string[]
  limit: number
  result: UnusedAssetsResult | null
}

const props = defineProps<UnusedAssetsScreenProps>()

const volumeId = ref(props.volumeId)
const result = ref(props.result)
const checked = ref<number[]>(props.result?.assets.map(asset => asset.id) ?? [])

function show(next: UnusedAssetsResult) {
  result.value = next
  checked.value = next.assets.map(asset => asset.id)
}

const find = useAsyncAction<UnusedAssetsResult>('toolkit/unused-assets/find')

async function findUnused() {
  const response = await find.run({ volumeId: volumeId.value })

  if (response?.ok)
    show(response.data)
  else if (response)
    notify(response)
}

const trash = useAsyncAction<{ result: UnusedAssetsResult }>('toolkit/unused-assets/trash', {
  confirm: () => `Move ${checked.value.length} assets to the trash?`,
})

async function trashChecked() {
  if (!checked.value.length)
    return

  const response = await trash.run({ volumeId: volumeId.value, assetIds: checked.value })

  if (response) {
    notify(response)
    if (response.ok)
      show(response.data.result)
  }
}

const allChecked = computed({
  get: () => !!result.value?.assets.length && checked.value.length === result.value.assets.length,
  set: on => checked.value = on ? result.value!.assets.map(asset => asset.id) : [],
})

function toggle(id: number, on: boolean) {
  checked.value = on ? [...checked.value, id] : checked.value.filter(other => other !== id)
}

const totalSize = computed(() => filesize(result.value?.assets.reduce((sum, asset) => sum + asset.size, 0) ?? 0))
</script>

<template>
  <p class="light">
    Assets of a volume that no content uses, for volumes like <code>media</code> where every file belongs to something: standalone files go in a separate volume. They are moved to the trash, so they can be restored until Craft's garbage collection deletes them.
  </p>

  <form class="field" @submit.prevent="findUnused">
    <div class="flex">
      <SelectInput v-model="volumeId" :options="volumes" />
      <Btn type="submit" :loading="find.pending.value">
        Find unused assets
      </Btn>
    </div>
  </form>

  <details class="field">
    <summary class="light">
      An asset counts as used, and is never listed, when…
    </summary>
    <ul class="bullets light">
      <!-- Written by the service, with <code> in it -->
      <li v-for="reason in usedWhen" :key="reason" v-html="reason" />
    </ul>
    <p class="light">
      Plugins that store asset IDs in their own tables or JSON (e.g. some SEO plugins) aren't seen: check the list before trashing.
    </p>
  </details>

  <template v-if="result">
    <p v-if="!result.assets.length">
      <StatusDot color="green" /> Every asset in <strong>{{ result.volumeName }}</strong> is in use.
    </p>

    <form v-else @submit.prevent="trashChecked">
      <p>
        <strong>{{ result.total }}</strong> unused {{ result.total === 1 ? 'asset' : 'assets' }} in <strong>{{ result.volumeName }}</strong>,
        {{ totalSize }}{{ result.total > limit ? ` in the first ${limit} shown` : '' }}.
        <span v-if="result.total > limit" class="light">Trash these, then find again for the next {{ limit }}.</span>
      </p>

      <table class="data fullwidth">
        <thead>
          <tr>
            <th class="checkbox-cell">
              <Checkbox v-model="allChecked" label="Select all" hidden-label />
            </th>
            <th>Asset</th>
            <th>Size</th>
            <th>Uploaded</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="asset in result.assets" :key="asset.id">
            <td class="checkbox-cell">
              <Checkbox :model-value="checked.includes(asset.id)" :label="asset.filename" hidden-label @update:model-value="toggle(asset.id, $event)" />
            </td>
            <td>
              <a :href="asset.cpEditUrl" class=":uno: inline-flex gap-2 items-center" target="_blank">
                <img v-if="asset.thumbUrl" :src="asset.thumbUrl" alt="" class=":uno: rounded-sm size-7.5 object-cover">
                {{ asset.title }}
              </a>
            </td>
            <td class="nowrap">
              {{ filesize(asset.size) }}
            </td>
            <td class="nowrap">
              {{ asset.dateCreated }}
            </td>
          </tr>
        </tbody>
      </table>

      <div class="buttons">
        <Btn type="submit" variant="submit" :loading="trash.pending.value" :disabled="!checked.length">
          Move selected to trash
        </Btn>
      </div>
    </form>
  </template>
</template>
