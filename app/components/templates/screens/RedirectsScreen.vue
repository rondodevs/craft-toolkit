<script setup lang="ts">
interface Redirect {
  id: number
  siteId: number | null
  sourceUri: string
  destination: string
  statusCode: number
  sortOrder: number
  origin: 'manual' | 'auto' | 'ether' | 'import'
  hits: number
  lastHit: string | null
  dateCreated: string
  pattern: boolean
}

interface RedirectsScreenProps {
  redirects: Redirect[]
  sites: { id: number, name: string, handle: string }[]
  auto: boolean
  canChangeSettings: boolean
  exportUrl: string
}

const props = defineProps<RedirectsScreenProps>()

const emit = defineEmits<{
  saved: [props: RedirectsScreenProps]
}>()

const origins = {
  manual: { label: 'Manual', tone: 'neutral' },
  auto: { label: 'Automatic', tone: 'ok' },
  ether: { label: 'From Ether', tone: 'warning' },
  import: { label: 'CSV', tone: 'neutral' },
} as const

const statusOptions = [
  { label: '301 permanent', value: 301 },
  { label: '302 temporary', value: 302 },
  { label: '307 temporary, keeps the method', value: 307 },
  { label: '308 permanent, keeps the method', value: 308 },
]
const siteOptions = computed(() => [{ label: 'All sites', value: '' }, ...props.sites.map(site => ({ label: site.name, value: site.id }))])
const siteName = (id: number | null) => id === null ? 'All' : props.sites.find(site => site.id === id)?.name ?? `#${id}`

const search = ref('')
const visible = computed(() => {
  const term = search.value.trim().toLowerCase()

  return term ? props.redirects.filter(r => `${r.sourceUri} ${r.destination}`.toLowerCase().includes(term)) : props.redirects
})

// Add and edit, in a modal
const editing = ref<{ id: number | null, sourceUri: string, destination: string, statusCode: number, siteId: number | '' } | null>(null)

function edit(redirect?: Redirect) {
  editing.value = redirect
    ? { id: redirect.id, sourceUri: redirect.sourceUri, destination: redirect.destination, statusCode: redirect.statusCode, siteId: redirect.siteId ?? '' }
    : { id: null, sourceUri: '', destination: '', statusCode: 301, siteId: '' }
}

const { save, saving } = useScreenSave<RedirectsScreenProps>('toolkit/redirects/save', (p) => {
  editing.value = null
  emit('saved', p)
})

function submit() {
  const { id, ...redirect } = editing.value!
  save({ id, redirect })
}

const { save: deleteRedirects } = useScreenSave<RedirectsScreenProps>('toolkit/redirects/delete', p => emit('saved', p))

function confirmRemove(redirect: Redirect) {
  // The browser's own dialog, as Craft uses for its destructive actions
  // eslint-disable-next-line no-alert
  if (window.confirm(`Delete the redirect from "${redirect.sourceUri}"?`))
    deleteRedirects({ ids: [redirect.id] })
}

// Order matters: the first matching redirect wins
const { save: saveOrder } = useScreenSave<RedirectsScreenProps>('toolkit/redirects/reorder', p => emit('saved', p))

function move(index: number, by: -1 | 1) {
  const ids = props.redirects.map(r => r.id)
  const [id] = ids.splice(index, 1)
  ids.splice(index + by, 0, id!)
  saveOrder({ ids })
}

// Which redirect a URL gets, as the frontend would ask
const testUrl = ref('')
const testSiteId = ref<number | ''>('')
const { run: runTest, pending: testing, result: tested } = useAsyncAction<{ result: { to: string, type: number } | null, url: string }>('toolkit/redirects/test')
const testResult = computed(() => tested.value?.ok ? tested.value.data : null)

const { save: saveSettings } = useScreenSave<RedirectsScreenProps>('toolkit/redirects/settings', p => emit('saved', p))
const auto = ref(props.auto)

watch(auto, value => saveSettings({ auto: value }))

const importOpen = ref(false)
const csv = ref('')
const { save: importCsv, saving: importing } = useScreenSave<RedirectsScreenProps>('toolkit/redirects/import', (p) => {
  importOpen.value = false
  csv.value = ''
  emit('saved', p)
})

async function readFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (file)
    csv.value = await file.text()
}
</script>

<template>
  <p class="light">
    The frontend asks Craft before rendering a page whether its URL is redirected, and caches the answer until a redirect changes here. The first matching redirect, in this order, wins.
  </p>

  <div class=":uno: field flex flex-wrap gap-2 items-center">
    <Btn variant="submit" add @click="edit()">
      New redirect
    </Btn>
    <Btn @click="importOpen = true">
      Import CSV
    </Btn>
    <Btn :href="exportUrl">
      Export CSV
    </Btn>
    <input v-model="search" type="search" class=":uno: text flex-1 min-w-50" placeholder="Search sources and destinations" aria-label="Search redirects">
  </div>

  <table v-if="visible.length" class="data fullwidth">
    <thead>
      <tr>
        <th>Source</th>
        <th>Destination</th>
        <th>Status</th>
        <th>Site</th>
        <th>Hits</th>
        <th />
      </tr>
    </thead>
    <tbody>
      <tr v-for="redirect in visible" :key="redirect.id">
        <td>
          <code class=":uno: break-all">{{ redirect.sourceUri }}</code>
          <div class=":uno: mt-1 flex flex-wrap gap-1">
            <Badge v-if="redirect.pattern">
              Pattern
            </Badge>
            <Badge :tone="origins[redirect.origin].tone">
              {{ origins[redirect.origin].label }}
            </Badge>
          </div>
        </td>
        <td><code class=":uno: break-all">{{ redirect.destination }}</code></td>
        <td>{{ redirect.statusCode }}</td>
        <td>{{ siteName(redirect.siteId) }}</td>
        <td class="nowrap">
          {{ redirect.hits }}
          <div v-if="redirect.lastHit" class=":uno: light text-2.75">
            last {{ redirect.lastHit }}
          </div>
        </td>
        <td class="nowrap">
          <div class=":uno: flex gap-1 justify-end">
            <template v-if="!search">
              <Btn small :disabled="redirect === redirects[0]" @click="move(redirects.indexOf(redirect), -1)">
                ↑
              </Btn>
              <Btn small :disabled="redirect === redirects[redirects.length - 1]" @click="move(redirects.indexOf(redirect), 1)">
                ↓
              </Btn>
            </template>
            <Btn small @click="edit(redirect)">
              Edit
            </Btn>
            <Btn small @click="confirmRemove(redirect)">
              Delete
            </Btn>
          </div>
        </td>
      </tr>
    </tbody>
  </table>
  <p v-else class="light">
    {{ redirects.length ? 'No redirect matches the search.' : 'No redirects yet.' }}
  </p>

  <hr>
  <h3>Test a URL</h3>
  <form class=":uno: flex flex-wrap gap-2 items-center" @submit.prevent="runTest({ url: testUrl, siteId: testSiteId })">
    <TextInput v-model="testUrl" class=":uno: flex-1 min-w-60" placeholder="/old-page or https://site.com/old-page" />
    <SelectInput v-if="sites.length > 1" v-model="testSiteId" :options="[{ label: 'Primary site', value: '' }, ...sites.map(site => ({ label: site.name, value: site.id }))]" />
    <Btn type="submit" :loading="testing">
      Test
    </Btn>
  </form>
  <p v-if="testResult" class=":uno: mt-2">
    <template v-if="testResult.result">
      <code>{{ testResult.url }}</code> → <code>{{ testResult.result.to }}</code> ({{ testResult.result.type }})
    </template>
    <template v-else>
      <code>{{ testResult.url }}</code> isn't redirected.
    </template>
  </p>

  <hr>
  <h3>Automatic redirects</h3>
  <Field id="tk-redirects-auto" instructions="When a live entry's URI changes (new slug, moved in the structure, new URI format), its old URI redirects to the new one, and redirects to the old URI are updated, so there are no chains. Saved in the project config.">
    <Lightswitch id="tk-redirects-auto" v-model="auto" :disabled="!canChangeSettings" />
  </Field>

  <hr>
  <h3>How sources match</h3>
  <ul class="bullets">
    <li>A path, such as <code>old-page</code> or <code>/blog/old-post</code>: that exact path, with or without slashes around it. Add <code>?query</code> to match a query string too.</li>
    <li>A pattern, such as <code>blog/(.*)</code> with destination <code>news/$1</code>: matched whole and case-insensitively; <code>$1</code>, <code>$2</code>... are the groups. Or a full regex, <code>/^blog\/(\d+)$/i</code>.</li>
    <li>A full URL, such as <code>https://old-domain.com/page</code>: for frontends serving several domains.</li>
    <li>Relative destinations are on the redirect's site; full URLs go anywhere. Ether SEO's redirects are imported when it's uninstalled and match the same way.</li>
  </ul>

  <Modal :model-value="!!editing" :title="editing?.id ? 'Edit redirect' : 'New redirect'" @update:model-value="editing = null">
    <form v-if="editing" @submit.prevent="submit">
      <Field id="tk-redirect-source" label="Source" instructions="A path, a pattern or a full URL (see How sources match).">
        <TextInput id="tk-redirect-source" v-model="editing.sourceUri" code placeholder="old-page" />
      </Field>
      <Field id="tk-redirect-destination" label="Destination" instructions="A path on the site, or a full URL. With a pattern, $1, $2... are its groups.">
        <TextInput id="tk-redirect-destination" v-model="editing.destination" code placeholder="new-page" />
      </Field>
      <div class=":uno: gap-x-3 grid grid-cols-2">
        <Field label="Status">
          <SelectInput v-model="editing.statusCode" :options="statusOptions" fullwidth />
        </Field>
        <Field v-if="sites.length > 1" label="Site">
          <SelectInput v-model="editing.siteId" :options="siteOptions" fullwidth />
        </Field>
      </div>
      <div class="buttons">
        <Btn type="submit" variant="submit" :loading="saving">
          Save
        </Btn>
      </div>
    </form>
  </Modal>

  <Modal v-model="importOpen" title="Import redirects from CSV">
    <p class="light">
      One redirect per row: source, destination, status code (301 when empty), site handle (all sites when empty). A header row is skipped; sources already here are too.
    </p>
    <div class="field">
      <input type="file" accept=".csv,text/csv" @change="readFile">
    </div>
    <TextArea v-model="csv" :rows="8" code placeholder="old-page,new-page,301,en" />
    <div class="buttons">
      <Btn variant="submit" :loading="importing" :disabled="!csv.trim()" @click="importCsv({ csv })">
        Import
      </Btn>
    </div>
  </Modal>
</template>
