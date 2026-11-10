<script setup lang="ts">
interface FrontendSettings {
  dataCache: boolean
  routeCache: boolean
  cdnCache: boolean
  routeCacheMaxAge: string
  routeCacheExclude: string
  redirects: boolean
  llms: boolean
  internalUrl: string
}

interface FrontendScreenProps {
  isDevMode: boolean
  settings: FrontendSettings
  defaults: Pick<FrontendSettings, 'routeCacheMaxAge' | 'internalUrl'>
  resolvedInternalUrl: string | null
  kvCacheUrl: string
  healthUrl: string
}

const props = defineProps<FrontendScreenProps>()

const emit = defineEmits<{
  saved: [props: FrontendScreenProps]
}>()

const form = useForm(() => props.settings)
const { save, saving } = useScreenSave<FrontendScreenProps>('toolkit/frontend/save', p => emit('saved', p))

const switches = [
  { key: 'dataCache', label: 'Data cache', instructions: 'Keeps the GraphQL results on the frontend (Redis with NUXT_REDIS_URL, otherwise on disk) and purges them by tag when you save. Off, every page asks Craft again.' },
  { key: 'routeCache', label: 'Page cache', instructions: 'Keeps the pages\' HTML too, purged by tag like the data cache: pages in a few ms. Needs the data cache.' },
  { key: 'cdnCache', label: 'CDN headers', instructions: 'Sends CDN-Cache-Control and Cache-Tag, for Cloudflare\'s Cache Rules (see the Cloudflare screen). Needs the data cache.' },
  { key: 'redirects', label: 'Redirects', instructions: 'Applies Toolkit → Redirects before rendering each page.' },
  { key: 'llms', label: 'llms.txt', instructions: 'Serves /llms.txt from the SEO fields\' AI summaries.' },
] as const
</script>

<template>
  <p class="light">
    The frontend (layer-craft 2.1+) reads these settings at startup and every minute, and right away when you save here: it only needs <code>NUXT_PUBLIC_BACKEND_URL</code>. A variable set on the frontend (e.g. <code>NUXT_CACHE_ENABLED</code>) still wins over its switch here: check <a :href="healthUrl">Health</a> for what the frontend actually uses.
  </p>

  <div v-if="isDevMode" class="warning with-icon">
    Craft is running in dev mode, so the data cache is off by default here: a local frontend shows every change right away.
  </div>

  <form @submit.prevent="save(form)">
    <Field
      v-for="item in switches"
      :id="`tk-fe-${item.key}`"
      :key="item.key"
      :label="item.label"
      :instructions="item.instructions"
    >
      <Lightswitch :id="`tk-fe-${item.key}`" v-model="form[item.key]" />
    </Field>

    <Field
      id="tk-fe-routeCacheMaxAge"
      label="Page cache max age (seconds)"
      instructions="How long a page stays cached at most: purges by tag remove it earlier."
      :tip="`Default: ${defaults.routeCacheMaxAge}`"
    >
      <TextInput id="tk-fe-routeCacheMaxAge" v-model="form.routeCacheMaxAge" type="number" :min="1" :placeholder="defaults.routeCacheMaxAge" />
    </Field>

    <Field
      id="tk-fe-routeCacheExclude"
      label="Never cache"
      instructions="Path prefixes the page cache skips, one per line (e.g. /account, /cart). Pages with a query string are never cached anyway."
    >
      <TextArea id="tk-fe-routeCacheExclude" v-model="form.routeCacheExclude" code :rows="3" placeholder="/account" />
    </Field>

    <Field
      id="tk-fe-internalUrl"
      label="Internal URL"
      instructions="Craft's address on the Docker network: when it answers with Craft's own key, the frontend sends its server-side requests (GraphQL, redirects) there instead of through the public URL. With the craft-php image it's found on its own; empty uses $CRAFT_INTERNAL_URL."
      :tip="resolvedInternalUrl ? `Now: ${resolvedInternalUrl}` : 'Now: none, the frontend uses the public URL'"
    >
      <TextInput id="tk-fe-internalUrl" v-model="form.internalUrl" code :placeholder="defaults.internalUrl" />
    </Field>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save settings
      </Btn>
    </div>
  </form>

  <p class="light">
    The frontend is told through the <a :href="kvCacheUrl">KV Cache</a> Frontend URL. A cache switched back on is flushed first.
  </p>
</template>
