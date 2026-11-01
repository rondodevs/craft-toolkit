<script setup lang="ts">
import type { FrameMarker } from '~/components/molecules/FrameTimeline.vue'

// Picks the poster frame: the timeline, the arrows, the timestamp and the suggested moments only
// move the preview; "Use this frame" applies it
interface FramePickerProps {
  url: string | null
  time: number
  // Suggested moments, found with the first poster; else a quarter, half and three quarters in
  candidates: number[] | null
  // The frame the poster shows now: no "Use this frame" on it
  currentTime: number | null
  canUse: boolean
}

const props = defineProps<FramePickerProps>()

const emit = defineEmits<{
  use: [time: number, video: HTMLVideoElement | null]
  invalid: []
}>()

const video = useTemplateRef<HTMLVideoElement>('video')
const duration = ref(0)
const position = ref(props.time)
const timestamp = ref(formatTime(props.time))

const typed = computed(() => parseTime(timestamp.value))
const isCurrent = computed(() => props.currentTime !== null && Math.abs(position.value - props.currentTime) < 0.05)

const markers = computed<FrameMarker[]>(() => {
  const suggested = props.candidates ?? [0.25, 0.5, 0.75].map(fraction => Math.round(duration.value * fraction * 10) / 10)
  const list: FrameMarker[] = suggested.map((time, index) => ({ time, label: String(index + 1) }))

  if (props.currentTime !== null)
    list.push({ time: props.currentTime, label: 'Poster', current: true })

  return list
})

// Every way of moving ends here: the video, the timestamp and the timeline follow
watch(position, (time) => {
  timestamp.value = formatTime(time)

  if (video.value)
    video.value.currentTime = time
})

function applyTyped() {
  if (typed.value !== null)
    position.value = duration.value ? Math.min(typed.value, duration.value) : typed.value
}

function step(seconds: number) {
  position.value = Math.round(Math.min(duration.value, Math.max(0, position.value + seconds)) * 10) / 10
}

function use() {
  applyTyped()

  if (typed.value === null)
    emit('invalid')
  else
    emit('use', position.value, video.value)
}

function onMetadata() {
  duration.value = Math.max(0, Math.floor((video.value!.duration - 0.1) * 10) / 10)
  position.value = Math.min(props.time, duration.value)
  video.value!.currentTime = position.value
}
</script>

<template>
  <div class=":uno: flex flex-col gap-3 max-w-160">
    <span class=":uno: font-600">Pick a frame</span>

    <template v-if="url">
      <div class=":uno: rounded-2 bg-black aspect-video relative overflow-hidden">
        <video
          ref="video"
          :src="`${url}#t=${time}`"
          class=":uno: size-full block object-contain"
          muted
          playsinline
          preload="auto"
          @loadedmetadata.once="onMetadata"
        />
        <span class=":uno: text-3 text-white px-2 py-1 rounded-md bg-black/65 left-3 top-3 absolute tabular-nums">
          {{ formatTime(position) }}<span class=":uno: opacity-60"> / {{ formatTime(duration) }}</span>
        </span>
        <span v-if="isCurrent" class=":uno: text-3 text-white font-600 px-2 py-1 rounded-md bg-[#1f9d55] right-3 top-3 absolute">
          Current poster
        </span>
      </div>

      <FrameTimeline v-if="duration" v-model="position" :src="url" :duration :markers />
    </template>

    <div class=":uno: flex flex-wrap gap-2 items-center">
      <div class="btngroup">
        <Btn title="Back 1 second" @click="step(-1)">
          −1s
        </Btn>
        <Btn title="Back 0.1 seconds" @click="step(-0.1)">
          ‹
        </Btn>
        <Btn title="Forward 0.1 seconds" @click="step(0.1)">
          ›
        </Btn>
        <Btn title="Forward 1 second" @click="step(1)">
          +1s
        </Btn>
      </div>
      <!-- Enter would submit the whole asset form -->
      <input
        v-model="timestamp"
        type="text"
        class=":uno: text w-6em tabular-nums"
        inputmode="decimal"
        aria-label="Timestamp, as 83.4 or 1:23.4"
        placeholder="0:00.0"
        @keydown.enter.prevent="applyTyped"
        @change="applyTyped"
      >
      <Btn v-if="canUse && !isCurrent" variant="submit" @click="use">
        Use this frame
      </Btn>
    </div>
  </div>
</template>
