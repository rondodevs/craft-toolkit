<script setup lang="ts">
// A text input that suggests `$ENV_VARS`, like Craft's autosuggest fields
interface EnvInputProps {
  id: string
  envVars: string[]
  placeholder?: string
}

defineProps<EnvInputProps>()

const value = defineModel<string>({ default: '' })
</script>

<template>
  <input
    :id
    v-model="value"
    type="text"
    class="text fullwidth code"
    :placeholder
    :list="`${id}-env`"
    autocomplete="off"
  >
  <datalist :id="`${id}-env`">
    <option v-for="name in envVars" :key="name" :value="name" />
  </datalist>
</template>
