<script setup lang="ts">
interface CloudflareSettings {
  cloudflareEnabled: boolean
  cloudflareZoneId: string
  cloudflareApiToken: string
}

interface CloudflareScreenProps {
  isDevMode: boolean
  settings: CloudflareSettings
  defaults: Omit<CloudflareSettings, 'cloudflareEnabled'>
  envVars: string[]
  createTokenUrl: string
  kvCacheUrl: string
  advancedUrl: string
  cacheRuleName: string
  cacheRuleExpression: string | null
}

const props = defineProps<CloudflareScreenProps>()

const emit = defineEmits<{
  saved: [props: CloudflareScreenProps]
}>()

const tabs = [
  { id: 'settings', label: 'Settings' },
  { id: 'edge-cache', label: 'Edge Cache' },
  { id: 'purge', label: 'Purge' },
]
const tab = ref('settings')

const form = useForm(() => props.settings)
const { save, saving } = useScreenSave<CloudflareScreenProps>('toolkit/cloudflare/save', p => emit('saved', p))

const verify = useAsyncAction('toolkit/cloudflare/verify', { idle: 'not checked', pending: 'checking…' })
const purge = useAsyncAction('toolkit/cloudflare/purge-everything', {
  pending: 'purging…',
  confirm: 'Purge the whole Cloudflare zone?',
})
</script>

<template>
  <p class="light">
    Purges the Cloudflare edge cache by tag right after every <a :href="kvCacheUrl">KV cache</a> purge (10 seconds after, with a frontend that delays its purges), and everything on a full flush. Needs the site proxied through Cloudflare and the Cache Rules set up by hand on the Cloudflare dashboard.
  </p>

  <div class="field">
    <TabGroup v-model="tab" :tabs label="Cloudflare sections" />
  </div>

  <form v-show="tab === 'settings'" @submit.prevent="save(form)">
    <Field
      id="tk-cf-enabled"
      label="Purge Cloudflare after the KV cache"
      instructions="Off by default in dev mode, so a local install never purges the production zone."
    >
      <Lightswitch id="tk-cf-enabled" v-model="form.cloudflareEnabled" />
    </Field>

    <Field
      id="tk-cf-zone"
      label="Zone ID"
      instructions="Cloudflare → the domain → Overview → Zone ID."
      :tip="`Default: ${defaults.cloudflareZoneId}`"
    >
      <EnvInput id="tk-cf-zone" v-model="form.cloudflareZoneId" :env-vars :placeholder="defaults.cloudflareZoneId" />
    </Field>

    <Field
      id="tk-cf-token"
      label="API token"
      instructions="API token with the &quot;Zone → Cache Purge&quot; and &quot;Zone → Cache Rules → Edit&quot; permissions on this zone only. Cache Purge is enough to purge; Cache Rules lets the edge cache check read the rule and create or fix it. Prefer an environment variable such as `$CLOUDFLARE_API_TOKEN` over pasting the token, which is stored in the database."
      :tip="`Default: ${defaults.cloudflareApiToken}`"
    >
      <EnvInput id="tk-cf-token" v-model="form.cloudflareApiToken" :env-vars :placeholder="defaults.cloudflareApiToken" />
    </Field>

    <div class="field">
      <Btn :href="createTokenUrl">
        Create token on Cloudflare
      </Btn>
      <p class="light">
        Opens the Cloudflare token form with the "Cache Purge" and "Cache Rules → Edit" permissions already filled in. Cloudflare cannot add a permission to an existing token from a link: create a new one to replace it. There, set <em>Zone Resources</em> to this site's zone, create the token and paste it above.
      </p>
    </div>

    <div class="field">
      <StatusLine :message="verify.status.value.message" :error="verify.status.value.error" />
      <Btn :loading="verify.pending.value" @click="verify.run()">
        Verify saved Cloudflare settings
      </Btn>
    </div>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save settings
      </Btn>
    </div>
  </form>

  <div v-show="tab === 'edge-cache'" role="tabpanel">
    <CloudflareEdgeCheck :cache-rule-name :cache-rule-expression />
  </div>

  <div v-show="tab === 'purge'" class="field">
    <p><strong>Emergency purge</strong></p>
    <p class="light">
      Purges the whole Cloudflare zone right away with the saved settings, even if the switch in Settings is off. It leaves the KV cache alone, so Cloudflare may refetch pages built from KV data that is still stale: to empty every layer, use "Purge all caches" in <a :href="advancedUrl">Advanced</a>.
    </p>
    <StatusLine :message="purge.status.value.message" :error="purge.status.value.error" />
    <Btn :loading="purge.pending.value" @click="purge.run()">
      Purge Cloudflare cache
    </Btn>
  </div>
</template>
