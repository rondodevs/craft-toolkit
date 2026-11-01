<script setup lang="ts">
import type { StaticLabel } from '~/components/organisms/StaticLabelRow.vue'

interface StaticLabelsSite {
  siteHandle: string
  siteName: string
  latestStaticLabelsUpdate: string | null
  labels: StaticLabel[]
}

interface StaticLabelsScreenProps {
  sites: StaticLabelsSite[]
}

const props = defineProps<StaticLabelsScreenProps>()

const emit = defineEmits<{
  saved: [props: StaticLabelsScreenProps]
}>()

type Row = StaticLabel & { uid: number }

let uid = 0

function emptyRow(): Row {
  return { uid: ++uid, key: '', mode: 'single', singleValue: '', zeroValue: '', manyValue: '' }
}

// Every site starts with at least one row to type into
const form = useForm(() => Object.fromEntries(props.sites.map(site => [
  site.siteHandle,
  site.labels.length ? site.labels.map(label => ({ ...label, uid: ++uid })) : [emptyRow()],
])) as Record<string, Row[]>)

const tabs = computed(() => props.sites.map(site => ({ id: site.siteHandle, label: site.siteName })))
const active = ref(props.sites[0]?.siteHandle ?? '')
const site = computed(() => props.sites.find(s => s.siteHandle === active.value))
const search = reactive<Record<string, string>>({})

function matches(row: Row, term = '') {
  const query = term.trim().toLowerCase()

  return query === '' || [row.key, row.singleValue, row.zeroValue, row.manyValue].some(text => text.toLowerCase().includes(query))
}

// The last row is emptied instead of removed
function remove(handle: string, row: Row) {
  const rows = form.value[handle]!
  form.value[handle] = rows.length > 1 ? rows.filter(other => other !== row) : [emptyRow()]
}

const { save, saving } = useScreenSave<StaticLabelsScreenProps>('toolkit/static-labels/save', p => emit('saved', p))

function submit() {
  save({ labels: form.value })
}
</script>

<template>
  <p class="light">
    Define static translation labels as key/value pairs for each enabled site. Keys are synchronized across all sites. Each row supports single or plural values (0, 1, many).
  </p>

  <form v-if="site" @submit.prevent="submit">
    <div class="field">
      <TabGroup v-model="active" :tabs label="Static label sites" />
    </div>

    <div class="pane">
      <h3>{{ site.siteName }} <span class="light">({{ site.siteHandle }})</span></h3>
      <p class="light">
        Latest update: {{ site.latestStaticLabelsUpdate ?? 'never' }}
      </p>

      <div v-for="handle in Object.keys(form)" v-show="handle === active" :key="handle" class="field" role="tabpanel">
        <Field instructions="Keys cannot contain spaces.">
          <input v-model="search[handle]" type="text" class="text fullwidth" placeholder="Search keys or values" autocomplete="off">
        </Field>

        <div class=":uno: border border-hairline rounded-md bg-white overflow-hidden">
          <div class=":uno: headings text-2.75 text-light tracking-[0.04em] font-600 px-3 py-2 border-b border-hairline bg-gray-050 uppercase top-0 sticky z-1 lt-xl:hidden" aria-hidden="true">
            <div>Key</div>
            <div>Mode</div>
            <div>Value</div>
            <div class=":uno: text-right">
              Actions
            </div>
          </div>
          <div class=":uno: [&>*:not(:last-child)]:border-b [&>*]:border-hairline">
            <template v-for="(row, index) in form[handle]" :key="row.uid">
              <StaticLabelRow v-show="matches(row, search[handle])" v-model="form[handle]![index]!" @remove="remove(handle, row)" />
            </template>
          </div>
        </div>

        <div class=":uno: buttons mt-2.5">
          <Btn add @click="form[handle]!.push(emptyRow())">
            Add label
          </Btn>
        </div>
      </div>

      <div class=":uno: buttons mt-4.5 justify-end">
        <Btn type="submit" variant="submit" :loading="saving">
          Save labels
        </Btn>
      </div>
    </div>
  </form>
</template>

<style scoped>
.headings {
  display: grid;
  grid-template-columns: minmax(220px, 1.2fr) 75px minmax(360px, 2fr) auto;
  gap: 12px;
}
</style>
