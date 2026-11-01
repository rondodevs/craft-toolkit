<script setup lang="ts">
export interface StaticLabel {
  key: string
  mode: 'single' | 'plural'
  singleValue: string
  zeroValue: string
  manyValue: string
}

const emit = defineEmits<{
  remove: []
}>()

const label = defineModel<StaticLabel>({ required: true })

const modes = [
  { label: 'Single', value: 'single' },
  { label: 'Plural', value: 'plural' },
]

// Keys can't contain spaces
function setKey(value: string) {
  label.value.key = value.replace(/\s+/g, '')
}
</script>

<template>
  <div class=":uno: row px-3 py-2.5 items-start hover:bg-gray-050">
    <input
      :value="label.key"
      type="text"
      class="text fullwidth"
      placeholder="label.key"
      spellcheck="false"
      autocomplete="off"
      aria-label="Key"
      @input="setKey(($event.target as HTMLInputElement).value)"
    >
    <SelectInput v-model="label.mode" :options="modes" fullwidth />
    <TextInput v-if="label.mode === 'single'" v-model="label.singleValue" placeholder="Single string" />
    <div v-else class=":uno: gap-2 grid grid-cols-3">
      <TextInput v-model="label.zeroValue" placeholder="0 elements" />
      <TextInput v-model="label.singleValue" placeholder="1 element" />
      <TextInput v-model="label.manyValue" placeholder="N elements" />
    </div>
    <div class=":uno: flex justify-end lt-xl:justify-start">
      <Btn @click="emit('remove')">
        Delete
      </Btn>
    </div>
  </div>
</template>

<style scoped>
.row {
  display: grid;
  grid-template-columns: minmax(220px, 1.2fr) 75px minmax(360px, 2fr) auto;
  gap: 12px;
}

@media (max-width: 1100px) {
  .row {
    grid-template-columns: 1fr;
  }
}
</style>
