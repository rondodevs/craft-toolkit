<script setup lang="ts">
interface BadgeProps {
  tone?: 'neutral' | 'ok' | 'missing' | 'warning'
}

withDefaults(defineProps<BadgeProps>(), {
  tone: 'neutral',
})

const tones = {
  neutral: ':uno: bg-gray-100',
  ok: ':uno: text-[#10633f] font-600 bg-[#d7f5e4]',
  missing: ':uno: text-[#9c2626] font-600 bg-[#fde3e3]',
  warning: ':uno: bg-[#fef9c3]',
}
</script>

<template>
  <span class=":uno: text-2.75 px-2 py-0.5 rounded-pill inline-block whitespace-nowrap" :class="tones[tone]">
    <slot />
  </span>
</template>
