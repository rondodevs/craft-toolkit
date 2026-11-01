<script setup lang="ts">
export interface SelectOption {
  label: string
  value: string | number
}

interface SelectInputProps {
  id?: string
  name?: string
  options: SelectOption[]
  small?: boolean
  fullwidth?: boolean
}

defineProps<SelectInputProps>()

const value = defineModel<string | number | null>()
</script>

<template>
  <div class="select" :class="{ small, fullwidth }">
    <select :id v-model="value" :name :class="{ fullwidth }">
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
  </div>
</template>
