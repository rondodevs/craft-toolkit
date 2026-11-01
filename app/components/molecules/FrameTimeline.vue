<script setup lang="ts">
// A scrubber over a filmstrip of the video: drag or click anywhere, arrows for fine steps.
// Markers show the suggested moments and the frame the poster shows now.
export interface FrameMarker {
  time: number
  label: string
  current?: boolean
}

interface FrameTimelineProps {
  src: string
  duration: number
  markers: FrameMarker[]
  frames?: number
}

const props = withDefaults(defineProps<FrameTimelineProps>(), {
  frames: 12,
})

const time = defineModel<number>({ required: true })

const STEP = 0.1

const track = useTemplateRef<HTMLElement>('track')
const canvases = useTemplateRef<HTMLCanvasElement[]>('canvases')
const { draw } = useFrameSampler()

// One frame from the middle of each slice of the strip
watch(() => [props.src, props.duration] as const, async ([src, duration]) => {
  if (!duration)
    return

  await nextTick()
  draw(src, (canvases.value ?? []).map((canvas, index) => ({
    time: (index + 0.5) * duration / props.frames,
    canvas,
  })))
}, { immediate: true })

function percent(at: number) {
  return `${props.duration ? Math.min(100, at / props.duration * 100) : 0}%`
}

function clamp(at: number) {
  return Math.round(Math.min(props.duration, Math.max(0, at)) * 10) / 10
}

function timeAt(clientX: number) {
  const rect = track.value!.getBoundingClientRect()

  return clamp((clientX - rect.left) / rect.width * props.duration)
}

const dragging = ref(false)
const hover = ref<number | null>(null)

function onPointerDown(event: PointerEvent) {
  dragging.value = true
  track.value!.setPointerCapture(event.pointerId)
  time.value = timeAt(event.clientX)
}

function onPointerMove(event: PointerEvent) {
  hover.value = timeAt(event.clientX)

  if (dragging.value)
    time.value = hover.value
}

function onKeydown(event: KeyboardEvent) {
  const step = event.shiftKey ? 1 : STEP
  const moves: Record<string, number> = {
    ArrowLeft: time.value - step,
    ArrowRight: time.value + step,
    Home: 0,
    End: props.duration,
  }

  if (event.key in moves) {
    event.preventDefault()
    time.value = clamp(moves[event.key]!)
  }
}
</script>

<template>
  <div class=":uno: select-none">
    <!-- Markers above the strip: click one to jump there -->
    <div class=":uno: h-5 relative">
      <button
        v-for="marker in markers"
        :key="`${marker.time}-${marker.current}`"
        type="button"
        class=":uno: text-2.5 leading-none font-600 px-1.5 py-0.75 rounded-pill whitespace-nowrap bottom-0 absolute -translate-x-1/2"
        :class="marker.current ? ':uno: text-white bg-[#1f9d55]' : Math.abs(marker.time - time) < 0.05 ? ':uno: text-white bg-primary' : ':uno: text-text bg-gray-100 hover:bg-gray-200'"
        :style="{ left: percent(marker.time) }"
        :title="marker.current ? 'The current poster' : `Suggested moment, ${formatTime(marker.time)}`"
        @click="time = marker.time"
      >
        {{ marker.label }}
      </button>
    </div>

    <div
      ref="track"
      class=":uno: mt-1.5 rounded-md bg-black h-14 cursor-ew-resize relative overflow-hidden focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2 focus-visible:outline"
      role="slider"
      tabindex="0"
      aria-label="Frame, seconds into the video"
      :aria-valuemin="0"
      :aria-valuemax="duration"
      :aria-valuenow="time"
      :aria-valuetext="formatTime(time)"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="dragging = false"
      @pointercancel="dragging = false"
      @pointerleave="hover = null"
      @keydown="onKeydown"
    >
      <div class=":uno: flex inset-0 absolute">
        <canvas
          v-for="index in frames"
          ref="canvases"
          :key="index"
          width="160"
          height="90"
          class=":uno: flex-1 h-full min-w-0 object-cover"
        />
      </div>

      <!-- Dims what's before the playhead, so the position reads at a glance -->
      <div class=":uno: bg-black/35 h-full pointer-events-none left-0 top-0 absolute" :style="{ width: percent(time) }" />

      <div
        v-for="marker in markers"
        :key="`tick-${marker.time}-${marker.current}`"
        class=":uno: h-full w-px pointer-events-none top-0 absolute"
        :class="marker.current ? ':uno: bg-[#1f9d55]' : ':uno: bg-white/60'"
        :style="{ left: percent(marker.time) }"
      />

      <div v-if="hover !== null && !dragging" class=":uno: bg-white/50 h-full w-px pointer-events-none top-0 absolute" :style="{ left: percent(hover) }">
        <span class=":uno: text-2.5 text-white leading-none px-1 py-0.5 rounded-sm bg-black/75 left-1 top-1 absolute tabular-nums">{{ formatTime(hover) }}</span>
      </div>

      <div class=":uno: h-full pointer-events-none top-0 absolute -translate-x-1/2" :style="{ left: percent(time) }">
        <div class=":uno: bg-primary h-full w-0.75 shadow-[0_0_0_1px_rgb(255_255_255/80%)]" />
      </div>
    </div>
  </div>
</template>
