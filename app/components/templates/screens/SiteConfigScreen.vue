<script setup lang="ts">
interface SiteConfig {
  siteName: string
  siteUrl: string
}

interface SiteConfigScreenProps {
  defaults: SiteConfig
  overrides: SiteConfig
  resolved: SiteConfig & { latestSiteConfigUpdate: string | null }
}

const props = defineProps<SiteConfigScreenProps>()

const emit = defineEmits<{
  saved: [props: SiteConfigScreenProps]
}>()

const form = useForm(() => props.overrides)
const { save, saving } = useScreenSave<SiteConfigScreenProps>('toolkit/site-config/save', p => emit('saved', p))
</script>

<template>
  <p class="light">
    Defaults come from env vars CRAFT_SITE_NAME and CRAFT_FRONTEND_URL. You can override both values here.
  </p>

  <form @submit.prevent="save(form)">
    <Field id="tk-site-name" label="Site name override" :tip="`Default: ${defaults.siteName}`">
      <TextInput id="tk-site-name" v-model="form.siteName" :placeholder="defaults.siteName" />
    </Field>

    <Field id="tk-site-url" label="Site URL override" :tip="`Default: ${defaults.siteUrl}`">
      <TextInput id="tk-site-url" v-model="form.siteUrl" type="url" :placeholder="defaults.siteUrl" />
    </Field>

    <div class="field">
      <p><strong>Resolved output</strong></p>
      <p class="light">
        siteName: {{ resolved.siteName }}<br>
        siteUrl: {{ resolved.siteUrl }}<br>
        latestSiteConfigUpdate: {{ resolved.latestSiteConfigUpdate ?? 'null' }}
      </p>
    </div>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save overrides
      </Btn>
    </div>
  </form>
</template>
