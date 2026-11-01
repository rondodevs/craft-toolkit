<script setup lang="ts">
interface LightswitchProps {
  id?: string
  name?: string
  small?: boolean
  label?: string
}

defineProps<LightswitchProps>()

const on = defineModel<boolean>({ default: false })
</script>

<template>
  <!-- Craft's markup without its jQuery plugin: the CSS animates `.on` by itself -->
  <button
    :id
    type="button"
    class="lightswitch"
    :class="{ on, small }"
    role="switch"
    :aria-checked="on"
    :aria-label="label"
    @click="on = !on"
  >
    <div class="lightswitch-container">
      <div class="handle" />
    </div>
    <HiddenInput v-if="name" :name :value="on ? '1' : ''" />
  </button>
</template>
