<script setup lang="ts">
interface ModalProps {
  title: string
}

defineProps<ModalProps>()

const open = defineModel<boolean>({ default: false })

onKeyStroke('Escape', () => {
  open.value = false
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class=":uno: bg-black/50 flex items-center inset-0 justify-center fixed z-1000"
      @click.self="open = false"
    >
      <div
        class=":uno: text-text rounded-2 bg-white flex flex-col max-h-85vh w-[min(900px,90vw)] shadow-[0_10px_40px_rgb(0_0_0/30%)]"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <div class=":uno: px-4 py-3 border-b border-hairline flex items-center justify-between">
          <h3 class=":uno: m-0">
            {{ title }}
          </h3>
          <Btn @click="open = false">
            Close
          </Btn>
        </div>
        <div class=":uno: p-4 overflow-auto">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>
