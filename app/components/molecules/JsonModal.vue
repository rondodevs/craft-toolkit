<script setup lang="ts">
interface JsonModalProps {
  title: string
  value: unknown
}

const props = defineProps<JsonModalProps>()

const open = defineModel<boolean>({ default: false })
const json = computed(() => JSON.stringify(props.value, null, 2))
</script>

<template>
  <Modal v-model="open" :title>
    <pre class=":uno: text-3 m-0 p-2.5 rounded-sm bg-gray-050 whitespace-pre-wrap break-words">{{ json }}</pre>
  </Modal>
</template>
