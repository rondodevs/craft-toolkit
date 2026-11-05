<script setup lang="ts">
import { kvCacheExplorerKey } from '~/composables/useKvCacheExplorer'

interface KvCacheSettings {
  enabled: boolean
  frontendUrl: string
  authToken: string
  authHeaderName: string
  requestTimeout: string
  connectTimeout: string
}

interface KvCacheScreenProps {
  isDevMode: boolean
  settings: KvCacheSettings
  defaults: Omit<KvCacheSettings, 'enabled'>
}

const props = defineProps<KvCacheScreenProps>()

const emit = defineEmits<{
  saved: [props: KvCacheScreenProps]
}>()

const tabs = [
  { id: 'options', label: 'Settings' },
  { id: 'check', label: 'Cache Explorer' },
  { id: 'purge', label: 'Purge Cache' },
]
const tab = ref('options')

const form = useForm(() => props.settings)
const { save, saving } = useScreenSave<KvCacheScreenProps>('toolkit/kv-cache/save', p => emit('saved', p))

const explorer = createKvCacheExplorer()
provide(kvCacheExplorerKey, explorer)
onMounted(explorer.refresh)

const fields = [
  { key: 'frontendUrl', label: 'Frontend URL', type: 'url', instructions: 'Absolute base URL of the frontend receiving purge requests.' },
  { key: 'authToken', label: 'Auth token', type: 'text', instructions: 'Usually not needed: Craft signs its requests and the frontend (layer-craft 1.2+) checks them with Craft\'s public key. Only for a frontend that needs a token: empty uses $CACHE_API_TOKEN, a value saved here overrides it.' },
  { key: 'authHeaderName', label: 'Auth header name', type: 'text', instructions: 'Header used to authenticate purge requests.' },
  { key: 'requestTimeout', label: 'Request timeout (seconds)', type: 'number', instructions: 'Overall HTTP timeout for purge requests.' },
  { key: 'connectTimeout', label: 'Connect timeout (seconds)', type: 'number', instructions: 'Maximum time allowed to establish the connection.' },
] as const
</script>

<template>
  <p class="light">
    Inspect, purge and configure the frontend KV cache endpoint used by automatic on-save invalidation, manual flush, tag purges, and connectivity checks.
  </p>

  <div v-if="isDevMode" class="warning with-icon">
    Craft is running in dev mode (<code>devMode</code> is enabled), so automatic on-save invalidation is disabled by default here. Toggle "Enable automatic on-save invalidation" below and save to turn it on manually.
  </div>

  <div class="field">
    <div class=":uno: px-3 py-2 border border-hairline rounded-md bg-gray-050">
      Status: <strong :class="{ error: explorer.status.value.error }">{{ explorer.status.value.message }}</strong>
    </div>
  </div>

  <div class="field">
    <TabGroup v-model="tab" :tabs label="KV Cache sections" />
  </div>

  <form v-show="tab === 'options'" @submit.prevent="save(form)">
    <Field
      id="tk-kv-enabled"
      label="Enable automatic on-save invalidation"
      instructions="Controls only automatic purge requests triggered on save. Manual check, manual flush, and tag purge remain available."
    >
      <Lightswitch id="tk-kv-enabled" v-model="form.enabled" />
    </Field>

    <Field
      v-for="field in fields"
      :id="`tk-kv-${field.key}`"
      :key="field.key"
      :label="field.label"
      :instructions="field.instructions"
      :tip="`Default: ${defaults[field.key]}`"
    >
      <TextInput
        :id="`tk-kv-${field.key}`"
        v-model="form[field.key]"
        :type="field.type"
        :min="field.type === 'number' ? 1 : undefined"
        :placeholder="String(defaults[field.key])"
      />
    </Field>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save settings
      </Btn>
    </div>
  </form>

  <!-- Wrapped: v-show needs one root element -->
  <div v-show="tab === 'check'" role="tabpanel">
    <KvCacheExplorer />
  </div>
  <div v-show="tab === 'purge'" role="tabpanel">
    <KvCacheTagPurge />
  </div>
</template>
