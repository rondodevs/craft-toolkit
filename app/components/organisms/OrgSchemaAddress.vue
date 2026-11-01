<script setup lang="ts">
import type { OpeningHours } from './OpeningHoursRow.vue'

export interface OrgSchemaAddressValue {
  streetAddress: string
  addressLocality: string
  addressRegion: string
  postalCode: string
  addressCountry: string
  priceRange: string
  openingHours: OpeningHours[]
}

interface OrgSchemaAddressProps {
  showPriceRange: boolean
  showOpeningHours: boolean
}

defineProps<OrgSchemaAddressProps>()

const emit = defineEmits<{
  remove: []
}>()

const address = defineModel<OrgSchemaAddressValue>({ required: true })
const id = useId()

const fields = [
  { key: 'streetAddress', label: 'Street address', placeholder: 'Street address', width: 'width-50' },
  { key: 'addressLocality', label: 'City', placeholder: 'City', width: 'width-50' },
  { key: 'addressRegion', label: 'Region / State', placeholder: 'Region / State', width: 'width-50' },
  { key: 'postalCode', label: 'Postal code', placeholder: 'Postal code', width: 'width-25' },
  { key: 'addressCountry', label: 'Country code', placeholder: 'e.g. US', width: 'width-25' },
] as const
</script>

<template>
  <div class=":uno: p-3 border border-hairline rounded-md bg-white">
    <div class="flex-fields">
      <Field v-for="field in fields" :id="`${id}-${field.key}`" :key="field.key" :class="field.width" :label="field.label">
        <TextInput :id="`${id}-${field.key}`" v-model="address[field.key]" :placeholder="field.placeholder" />
      </Field>
    </div>

    <Field
      v-if="showPriceRange"
      :id="`${id}-price`"
      label="Price range (this location)"
      class=":uno: mt-3 pt-3 border-t border-hairline"
    >
      <TextInput :id="`${id}-price`" v-model="address.priceRange" placeholder="$$" />
    </Field>

    <Field
      v-if="showOpeningHours"
      label="Opening hours (this location)"
      instructions="One row per schedule, e.g. Mo–Fr 09:00–18:00, then a separate row for Sa 10:00–14:00."
      class=":uno: mt-3 pt-3 border-t border-hairline"
    >
      <div class=":uno: mb-2 flex flex-col gap-3">
        <OpeningHoursRow
          v-for="(_, index) in address.openingHours"
          :key="index"
          v-model="address.openingHours[index]!"
          @remove="address.openingHours.splice(index, 1)"
        />
      </div>
      <div class="buttons">
        <Btn add @click="address.openingHours.push({ days: [], opens: '', closes: '' })">
          Add hours
        </Btn>
      </div>
    </Field>

    <div class=":uno: buttons mt-3 justify-end">
      <Btn @click="emit('remove')">
        Remove address
      </Btn>
    </div>
  </div>
</template>
