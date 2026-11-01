<script setup lang="ts">
interface QualityMenuProps {
  // The video the menu drives
  video: HTMLVideoElement | null
  playlistUrl: string
  originalUrl: string
  start?: 'original' | 'auto'
}

const props = withDefaults(defineProps<QualityMenuProps>(), {
  start: 'original',
})

const { options, selected, select } = useHlsQualities(toRef(props, 'video'), props.playlistUrl, props.originalUrl)
const id = useId()

watch(() => props.video, (video) => {
  if (video)
    select(props.start)
}, { immediate: true })
</script>

<template>
  <div class=":uno: flex gap-1.5 items-center">
    <label :for="id" class="light">Quality</label>
    <div class="select small">
      <select :id :value="selected" @change="select(($event.target as HTMLSelectElement).value)">
        <option v-for="option in options" :key="option.key" :value="option.key" :title="option.title">
          {{ option.label }}
        </option>
      </select>
    </div>
  </div>
</template>
