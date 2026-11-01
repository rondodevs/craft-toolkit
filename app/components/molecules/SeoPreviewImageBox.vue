<script setup lang="ts">
import type { SeoPreviewImage } from '~/components/organisms/SeoSocialPreviews.vue'

interface SeoPreviewImageBoxProps {
  image: SeoPreviewImage | null
}

defineProps<SeoPreviewImageBoxProps>()
</script>

<template>
  <!-- Cropped to 1.91:1, as the networks do -->
  <div class=":uno: bg-[#dfe3e8] aspect-[1.91/1] relative overflow-hidden">
    <img v-if="image" :src="image.url" alt="" class=":uno: size-full inset-0 absolute object-cover">
    <span v-else class=":uno: text-[#8a939e] text-3.25 flex items-center inset-0 justify-center absolute">No image</span>
  </div>
</template>
