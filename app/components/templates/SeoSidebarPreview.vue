<script setup lang="ts">
interface SeoSidebarPreviewProps {
  previewKey: string
}

const props = defineProps<SeoSidebarPreviewProps>()

const field = useSeoSidebar(props.previewKey)
</script>

<template>
  <!-- The sidebar pulls its own .field rows to the edges (.details .field): not these -->
  <div v-if="field" class=":uno: [&_.field]:!mx-0 [&_.field:last-child]:!mb-0">
    <SeoSocialPreviews :values="field.previews.value" compact />

    <SeoSearchSettings
      v-model:robots="field.search.robots.value"
      v-model:canonical="field.search.canonical.value"
      class=":uno: mt-4 pt-3 border-t border-hairline"
      :name="field.search.name"
      :default-robots="field.search.defaultRobots"
      :robots-options="field.search.robotsOptions"
    />
  </div>
</template>
