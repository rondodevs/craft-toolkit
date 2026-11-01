<script setup lang="ts">
const widths = ['92%', '78%', '85%', '65%', '88%', '72%']
</script>

<template>
  <div class=":uno: flex flex-col gap-2" aria-hidden="true">
    <div v-for="width in widths" :key="width" class=":uno: skeletonRow rounded-sm h-3.5" :style="{ width }" />
  </div>
</template>

<style scoped>
.skeletonRow {
  background: linear-gradient(90deg, var(--gray-100) 25%, var(--gray-200) 37%, var(--gray-100) 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
}

@keyframes shimmer {
  from {
    background-position: 100% 0;
  }

  to {
    background-position: 0 0;
  }
}
</style>
