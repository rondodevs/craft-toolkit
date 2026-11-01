<script setup lang="ts">
// A text the editor can leave automatic: prefilled with the automatic value and flagged as such
// until changed, so it keeps following the entry instead of freezing what is shown here
interface SeoTextInputProps {
  id: string
  name: string
  multiline?: boolean
  rows?: number
  limit: number
  // The automatic value: prefilled, and back with Reset. Without, the input is just optional
  autoValue?: string
  placeholder?: string
  resetLabel?: string
}

const props = defineProps<SeoTextInputProps>()

const value = defineModel<string>({ required: true })
const isAuto = defineModel<boolean>('automatic', { default: false })

function onInput(text: string) {
  value.value = text
  if (props.autoValue !== undefined)
    isAuto.value = text.trim() === props.autoValue.trim()
}

function reset() {
  value.value = props.autoValue ?? ''
  isAuto.value = true
}

// What the field stands for: its value, else what it falls back to
const shown = computed(() => value.value.trim() || props.placeholder?.trim() || '')
const automatic = computed(() => props.autoValue !== undefined ? isAuto.value : value.value.trim() === '')
</script>

<template>
  <div class=":uno: flex gap-1.5 items-start">
    <textarea
      v-if="multiline"
      :id
      :value
      :name
      :rows
      :placeholder
      class="text fullwidth"
      @input="onInput(($event.target as HTMLTextAreaElement).value)"
    />
    <input
      v-else
      :id
      :value
      :name
      :placeholder
      type="text"
      class="text fullwidth"
      @input="onInput(($event.target as HTMLInputElement).value)"
    >
    <template v-if="autoValue !== undefined">
      <HiddenInput :name="`${name.replace(/\]$/, '')}Auto]`" :value="isAuto ? '1' : '0'" />
      <Btn v-if="!isAuto" small :title="resetLabel" @click="reset">
        Reset
      </Btn>
    </template>
  </div>
  <p class=":uno: light text-3 mt-1" :class="{ ':uno: text-error': shown.length > limit }">
    {{ shown.length }} / {{ limit }} · {{ automatic ? 'automatic' : 'custom' }}
  </p>
</template>
