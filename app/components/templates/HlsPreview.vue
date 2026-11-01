<script setup lang="ts">
// The asset preview modal of an HLS-ready video: plays the stream the frontend gets
interface HlsPreviewProps {
  playlistUrl: string
  url: string
  posterUrl: string | null
  transcript: string | null
}

defineProps<HlsPreviewProps>()

const video = useTemplateRef<HTMLVideoElement>('video')
</script>

<template>
  <!-- Same layout as Craft's own video preview, with a quality menu over the video -->
  <div class=":uno: bg-black relative" :style="{ height: transcript ? '70%' : '100%' }">
    <video ref="video" class=":uno: size-full object-contain" autoplay controls playsinline :poster="posterUrl ?? undefined" />
    <QualityMenu
      :video
      :playlist-url
      :original-url="url"
      start="auto"
      class=":uno: text-white m-0 py-1 pl-2.5 pr-1 rounded-md bg-black/55 opacity-80 transition-opacity right-3 top-3 absolute focus-within:opacity-100 hover:opacity-100"
    />
  </div>
  <div v-if="transcript" class="video-transcript" tabindex="0" style="height: 30%;">
    <div class="video-transcript__inner">
      <h2 class="video-transcript__heading">
        Transcript
      </h2>
      <p class=":uno: video-transcript__text whitespace-pre-line">
        {{ transcript }}
      </p>
    </div>
  </div>
</template>
