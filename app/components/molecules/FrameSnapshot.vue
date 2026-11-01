<script setup lang="ts">
// The frame a video shows now, drawn once: the poster until the real file is extracted
interface FrameSnapshotProps {
  video: HTMLVideoElement
}

const props = defineProps<FrameSnapshotProps>()

const canvas = useTemplateRef<HTMLCanvasElement>('canvas')

onMounted(() => {
  const { video } = props

  if (!canvas.value || !video.videoWidth)
    return

  canvas.value.width = 640
  canvas.value.height = Math.round(640 * video.videoHeight / video.videoWidth)
  canvas.value.getContext('2d')?.drawImage(video, 0, 0, canvas.value.width, canvas.value.height)
})
</script>

<template>
  <canvas ref="canvas" />
</template>
