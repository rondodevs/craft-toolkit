<script setup lang="ts">
import type { OrgSchemaAddressValue } from './OrgSchemaAddress.vue'
import type { PickedAsset } from '~/composables/useElementSelector'

export interface OrgSchemaEntry {
  type: string
  name: string
  legalName: string
  url: string
  logo: PickedAsset | null
  description: string
  email: string
  telephone: string
  sameAs: string[]
  addresses: OrgSchemaAddressValue[]
}

interface OrgSchemaIdentityProps {
  types: string[]
  conditionalFields: Record<string, string[]>
}

const props = defineProps<OrgSchemaIdentityProps>()

const entry = defineModel<OrgSchemaEntry>({ required: true })
const id = useId()

// In script: vue-tsc misreads a `//` in a static attribute
const placeholders = { url: 'https://example.com', profile: 'https://www.linkedin.com/company/...' }

const typeOptions = computed(() => props.types.map(type => ({ label: type, value: type })))

// Which fields schema.org defines for the picked type
function shows(field: string) {
  return props.conditionalFields[field]?.includes(entry.value.type) ?? false
}

function emptyAddress(): OrgSchemaAddressValue {
  return { streetAddress: '', addressLocality: '', addressRegion: '', postalCode: '', addressCountry: '', priceRange: '', openingHours: [] }
}

// Price range and opening hours live on the addresses: a place type with none would have nowhere
// to set them, so it gets a blank one
watch(() => entry.value.type, () => {
  if (shows('openingHours') && !entry.value.addresses.length)
    entry.value.addresses.push(emptyAddress())
}, { immediate: true })

async function resolveLogo(assetId: number) {
  const result = await action<PickedAsset>('toolkit/org-schema/logo', { id: assetId })

  return result.ok ? result.data : null
}
</script>

<template>
  <Field :id="`${id}-type`" label="Identity type">
    <SelectInput :id="`${id}-type`" v-model="entry.type" :options="typeOptions" />
  </Field>

  <div class="flex-fields">
    <Field :id="`${id}-name`" label="Name" class="width-50">
      <TextInput :id="`${id}-name`" v-model="entry.name" placeholder="Acme Inc." />
    </Field>
    <Field v-if="shows('legalName')" :id="`${id}-legal`" label="Legal name" class="width-50">
      <TextInput :id="`${id}-legal`" v-model="entry.legalName" placeholder="Acme Incorporated" />
    </Field>
    <Field :id="`${id}-url`" label="URL" class="width-50">
      <TextInput :id="`${id}-url`" v-model="entry.url" type="url" :placeholder="placeholders.url" />
    </Field>
    <Field :id="`${id}-email`" label="Email" class="width-50">
      <TextInput :id="`${id}-email`" v-model="entry.email" type="email" placeholder="hello@example.com" />
    </Field>
    <Field :id="`${id}-phone`" label="Telephone" class="width-50">
      <TextInput :id="`${id}-phone`" v-model="entry.telephone" placeholder="+1 555 0100" />
    </Field>
    <Field v-if="shows('logoUrl')" label="Logo" class="width-50">
      <AssetPicker v-model="entry.logo" :resolve="resolveLogo" />
    </Field>
  </div>

  <Field :id="`${id}-description`" label="Description">
    <TextArea :id="`${id}-description`" v-model="entry.description" :rows="2" />
  </Field>

  <Field
    label="Addresses"
    instructions="Add one address per physical location. For a single-location business, add just one. Price range and opening hours are set per address below, since those apply to a specific place, not to the identity as a whole."
  >
    <div class=":uno: mb-2 flex flex-col gap-3">
      <OrgSchemaAddress
        v-for="(_, index) in entry.addresses"
        :key="index"
        v-model="entry.addresses[index]!"
        :show-price-range="shows('priceRange')"
        :show-opening-hours="shows('openingHours')"
        @remove="entry.addresses.splice(index, 1)"
      />
    </div>
    <div class="buttons">
      <Btn add @click="entry.addresses.push(emptyAddress())">
        Add address
      </Btn>
    </div>
  </Field>

  <Field label="Same as (social profile URLs)">
    <div class=":uno: mb-2 flex flex-col gap-2">
      <div v-for="(url, index) in entry.sameAs" :key="`${index}-${url}`" class=":uno: flex gap-2 items-center">
        <TextInput v-model="entry.sameAs[index]" type="url" :placeholder="placeholders.profile" aria-label="Profile URL" />
        <Btn @click="entry.sameAs.splice(index, 1)">
          Remove
        </Btn>
      </div>
    </div>
    <div class="buttons">
      <Btn add @click="entry.sameAs.push('')">
        Add URL
      </Btn>
    </div>
  </Field>
</template>
