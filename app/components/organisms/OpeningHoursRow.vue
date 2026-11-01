<script setup lang="ts">
export interface OpeningHours {
  days: string[]
  opens: string
  closes: string
}

const emit = defineEmits<{
  remove: []
}>()

const hours = defineModel<OpeningHours>({ required: true })

const DAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']

function toggle(day: string, on: boolean) {
  hours.value.days = on ? DAYS.filter(d => d === day || hours.value.days.includes(d)) : hours.value.days.filter(d => d !== day)
}
</script>

<template>
  <div class=":uno: p-3 border border-hairline rounded-md bg-white flex flex-wrap gap-3 items-center">
    <div class=":uno: flex flex-[1_1_auto] flex-wrap gap-1">
      <label
        v-for="day in DAYS"
        :key="day"
        class=":uno: text-3 px-2 py-1 border border-hairline rounded-sm inline-flex gap-1 cursor-pointer items-center"
      >
        <input type="checkbox" :checked="hours.days.includes(day)" class=":uno: m-0" @change="toggle(day, ($event.target as HTMLInputElement).checked)">
        <span>{{ day }}</span>
      </label>
    </div>
    <div class=":uno: flex flex-none gap-1.5 items-center">
      <input v-model="hours.opens" type="time" aria-label="Opens">
      <span class="light">–</span>
      <input v-model="hours.closes" type="time" aria-label="Closes">
    </div>
    <Btn @click="emit('remove')">
      Remove
    </Btn>
  </div>
</template>
