<script setup lang="ts">
import type { OrgSchemaEntry } from '~/components/organisms/OrgSchemaIdentity.vue'

interface OrgSchemaSite {
  siteHandle: string
  siteName: string
  latestOrgSchemaUpdate: string | null
  entry: OrgSchemaEntry
}

interface OrgSchemaScreenProps {
  types: string[]
  conditionalFields: Record<string, string[]>
  sites: OrgSchemaSite[]
}

const props = defineProps<OrgSchemaScreenProps>()

const emit = defineEmits<{
  saved: [props: OrgSchemaScreenProps]
}>()

const form = useForm(() => Object.fromEntries(props.sites.map(site => [site.siteHandle, site.entry])) as Record<string, OrgSchemaEntry>)

const tabs = computed(() => props.sites.map(site => ({ id: site.siteHandle, label: site.siteName })))
const active = ref(props.sites[0]?.siteHandle ?? '')
const site = computed(() => props.sites.find(s => s.siteHandle === active.value))

const { save, saving } = useScreenSave<OrgSchemaScreenProps>('toolkit/org-schema/save', p => emit('saved', p))

// The logo travels as its id, as the service stores it
function submit() {
  save({
    orgSchema: Object.fromEntries(Object.entries(form.value).map(([handle, { logo, ...entry }]) => [
      handle,
      { ...entry, logoAssetId: logo?.id ?? null },
    ])),
  })
}
</script>

<template>
  <p class="light">
    Define the default schema.org identity for each enabled site (Organization, LocalBusiness, MedicalClinic, EducationalOrganization, Person, ...). Available fields adjust to the selected type, matching what schema.org actually defines for it: legal name and logo are hidden for Person (Organization-only properties), price range only shows for LocalBusiness and its subtypes, and opening hours also show for EducationalOrganization (it inherits opening hours from CivicStructure, but not price range). This feeds Nuxt SEO's schema-org default identity, see <a href="https://nuxtseo.com/docs/schema-org/guides/default-schema-org" target="_blank" rel="noopener">nuxtseo.com/docs/schema-org/guides/default-schema-org</a>.
  </p>
  <p class="light">
    Price range and opening hours are properties of a physical place in schema.org, not of the identity as a whole: where they apply, they live on each address below rather than on the identity, and each location can have its own schedule (e.g. a multi-clinic business with different hours per clinic). If a location also needs its own dedicated page (description, images, its own full JSON-LD), model it as its own entry instead and use the "Org Schema" field there with the matching type (e.g. MedicalClinic).
  </p>

  <form v-if="site" @submit.prevent="submit">
    <template v-if="sites.length > 1">
      <div class="field">
        <TabGroup v-model="active" :tabs label="Org schema sites" />
      </div>
      <h3>{{ site.siteName }} <span class="light">({{ site.siteHandle }})</span></h3>
      <p class="light">
        Latest update: {{ site.latestOrgSchemaUpdate ?? 'never' }}
      </p>
    </template>

    <div v-for="handle in Object.keys(form)" v-show="handle === active" :key="handle" role="tabpanel">
      <OrgSchemaIdentity v-model="form[handle]!" :types :conditional-fields />
    </div>

    <div class=":uno: buttons mt-4.5 justify-end">
      <Btn type="submit" variant="submit" :loading="saving">
        Save
      </Btn>
    </div>
  </form>
</template>
