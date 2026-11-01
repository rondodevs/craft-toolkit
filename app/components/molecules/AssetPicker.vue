<script setup lang="ts">
import type { PickedAsset } from '~/composables/useElementSelector'

interface AssetPickerProps {
  name?: string
  // Returns the asset to show for an id, or null when it can't be used
  resolve: (id: number) => Promise<PickedAsset | null>
  label?: string
}

const props = withDefaults(defineProps<AssetPickerProps>(), {
  label: 'Choose',
})

const asset = defineModel<PickedAsset | null>({ default: null })
const { select } = useElementSelector({ kind: ['image'] })
const loading = ref(false)

async function choose() {
  const id = await select()

  if (id === null)
    return

  loading.value = true
  asset.value = await props.resolve(id) ?? asset.value
  loading.value = false
}
</script>

<template>
  <div class=":uno: flex flex-wrap gap-2 items-center">
    <HiddenInput v-if="name" :name :value="asset?.id" />
    <div v-if="asset" class=":uno: p-1.5 pr-3 border border-hairline rounded-md bg-gray-050 flex gap-2.5 items-center">
      <img v-if="asset.thumbUrl" :src="asset.thumbUrl" alt="" class=":uno: rounded-sm h-12 w-12 object-contain">
      <span class=":uno: max-w-60 truncate">{{ asset.title }}</span>
      <button type="button" class="delete icon" :aria-label="`Remove ${asset.title}`" @click="asset = null" />
    </div>
    <Btn v-if="!asset" variant="dashed" add :loading @click="choose">
      {{ label }}
    </Btn>
  </div>
</template>
