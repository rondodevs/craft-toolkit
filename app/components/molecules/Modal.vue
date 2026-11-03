<script setup lang="ts">
interface ModalProps {
  title: string
  narrow?: boolean
}

defineProps<ModalProps>()

const open = defineModel<boolean>({ default: false })

// Before Garnish's own Escape (on the document): in a slideout, only the modal closes
useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  if (!open.value || event.key !== 'Escape')
    return

  event.stopPropagation()
  open.value = false
}, { capture: true })
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class=":uno: bg-black/50 flex items-center inset-0 justify-center fixed z-1000"
      @click.self="open = false"
    >
      <div
        class=":uno: text-text rounded-2 bg-white flex flex-col max-h-85vh shadow-[0_10px_40px_rgb(0_0_0/30%)]"
        :class="narrow ? ':uno: w-[min(560px,90vw)]' : ':uno: w-[min(900px,90vw)]'"
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
