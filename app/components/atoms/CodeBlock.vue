<script setup lang="ts">
interface CodeBlockProps {
  code: string
  wrap?: boolean
}

defineProps<CodeBlockProps>()
</script>

<template>
  <pre class="code" :class="{ ':uno: whitespace-pre-wrap break-all': wrap }"><code>{{ code }}</code></pre>
</template>
