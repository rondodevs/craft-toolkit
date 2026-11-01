<script setup lang="ts">
// An SVG from Craft's icon set (Cp::iconSvg), rendered on the server
interface RawIconProps {
  svg: string | null
}

defineProps<RawIconProps>()
</script>

<template>
  <span
    v-if="svg"
    class=":uno: inline-flex flex-none items-center [&_svg]:h-full [&_svg]:w-full [&_svg]:fill-current"
    aria-hidden="true"
    v-html="svg"
  />
</template>
