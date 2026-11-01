<script setup lang="ts" generic="T extends string | number">
interface CheckboxGroupProps {
  options: { label: string, value: T }[]
  name?: string
}

const props = defineProps<CheckboxGroupProps>()

const values = defineModel<T[]>({ required: true })

// Ticked values stay in the options' order
function toggle(value: T, on: boolean) {
  values.value = props.options
    .map(option => option.value)
    .filter(other => other === value ? on : values.value.includes(other))
}
</script>

<template>
  <fieldset class="checkbox-group">
    <!-- Posts an empty list when nothing is ticked -->
    <input v-if="name" type="hidden" :name value="">
    <div v-for="option in options" :key="option.value">
      <Checkbox
        :model-value="values.includes(option.value)"
        :label="option.label"
        :name="name ? `${name}[]` : undefined"
        :value="option.value"
        @update:model-value="toggle(option.value, $event)"
      />
    </div>
  </fieldset>
</template>
