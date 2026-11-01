<script setup lang="ts">
// One frame of a video, drawn from a hidden copy of it
interface VideoFrameProps {
  src: string
  time: number
}

const props = defineProps<VideoFrameProps>()

const canvas = useTemplateRef<HTMLCanvasElement>('canvas')
const { draw } = useFrameSampler()

watch(() => [props.src, props.time] as const, ([src, time]) => {
  if (canvas.value)
    draw(src, [{ time, canvas: canvas.value }])
}, { flush: 'post' })

onMounted(() => draw(props.src, [{ time: props.time, canvas: canvas.value! }]))
</script>

<template>
  <canvas ref="canvas" width="640" height="360" />
</template>
