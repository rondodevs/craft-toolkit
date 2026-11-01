<script setup lang="ts">
interface CheckboxProps {
  label?: string
  name?: string
  value?: string | number
  hiddenLabel?: boolean
}

defineProps<CheckboxProps>()

const checked = defineModel<boolean>({ default: false })
const id = useId()
</script>

<template>
  <input :id v-model="checked" type="checkbox" class="checkbox" :name :value>
  <label :for="id">
    <span :class="{ 'visually-hidden': hiddenLabel }">{{ label }}</span>
  </label>
</template>
