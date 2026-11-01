<script setup lang="ts" generic="T extends { id: string }">
// A search field over a list, toggling options in and out of a selection
interface ComboboxProps {
  options: T[]
  placeholder: string
  // What the search matches against
  text: (option: T) => string
  // Enter adds what's typed as a new option
  allowCustom?: boolean
  emptyText?: string
  max?: number
}

const props = withDefaults(defineProps<ComboboxProps>(), {
  max: 100,
  emptyText: 'No matches.',
})

const emit = defineEmits<{
  custom: [value: string]
}>()

const selected = defineModel<string[]>({ required: true })

const query = ref('')
const open = ref(false)
const listboxId = useId()

const matches = computed(() => {
  const term = query.value.trim().toLowerCase()

  return props.options
    .filter(option => term === '' || props.text(option).toLowerCase().includes(term))
    .slice(0, props.max)
})

function toggle(id: string) {
  selected.value = selected.value.includes(id)
    ? selected.value.filter(other => other !== id)
    : [...selected.value, id]
}

function onEnter() {
  const value = query.value.trim()

  if (!props.allowCustom || value === '')
    return

  emit('custom', value)
  query.value = ''
}
</script>

<template>
  <div class=":uno: relative">
    <input
      v-model="query"
      type="text"
      class="text fullwidth"
      :placeholder
      autocomplete="off"
      role="combobox"
      :aria-expanded="open"
      :aria-controls="listboxId"
      @focus="open = true"
      @input="open = true"
      @blur="open = false"
      @keydown.enter.prevent="onEnter"
      @keydown.esc="open = false"
    >
    <div
      v-if="open"
      :id="listboxId"
      class=":uno: mt-1 border border-hairline rounded-md bg-white max-h-70 shadow-[0_4px_16px_rgb(0_0_0/12%)] inset-x-0 top-full absolute z-10 overflow-auto"
      role="listbox"
    >
      <div v-if="!matches.length" class=":uno: text-light px-3 py-2">
        <slot name="empty" :query>
          {{ emptyText }}
        </slot>
      </div>
      <!-- mousedown, so the input's blur doesn't close the list first -->
      <div
        v-for="option in matches"
        :key="option.id"
        class=":uno: px-3 py-1.5 flex gap-3 cursor-pointer items-center justify-between hover:bg-gray-050"
        :class="{ ':uno: font-600 bg-gray-100': selected.includes(option.id) }"
        role="option"
        :aria-selected="selected.includes(option.id)"
        @mousedown.prevent="toggle(option.id)"
      >
        <slot name="option" :option>
          {{ text(option) }}
        </slot>
      </div>
    </div>
  </div>
</template>
