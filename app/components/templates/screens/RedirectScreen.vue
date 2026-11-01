<script setup lang="ts">
interface RedirectScreenProps {
  isDevMode: boolean
  enabled: boolean
  frontendUrl: string
  defaultFrontendUrl: string
  resolvedFrontendUrl: string
}

const props = defineProps<RedirectScreenProps>()

const emit = defineEmits<{
  saved: [props: RedirectScreenProps]
}>()

const form = useForm(() => ({ enabled: props.enabled, frontendUrl: props.frontendUrl }))
const { save, saving } = useScreenSave<RedirectScreenProps>('toolkit/redirect/save', p => emit('saved', p))
</script>

<template>
  <p class="light">
    Controls where the site root (<code>/</code>) sends visitors in production. In dev mode, requests are always redirected to /admin instead.
  </p>

  <div v-if="isDevMode" class="warning with-icon">
    Craft is running in dev mode (<code>devMode</code> is enabled), so the frontend redirect below is disabled by default: GET requests to the site root always go to <code>/admin</code> regardless of these settings.
  </div>

  <form @submit.prevent="save(form)">
    <Field
      id="tk-redirect-enabled"
      label="Enable frontend redirect"
      instructions="When disabled, GET requests to the site root redirect to &quot;/&quot; instead of the frontend URL below."
    >
      <Lightswitch id="tk-redirect-enabled" v-model="form.enabled" />
    </Field>

    <Field
      id="tk-redirect-url"
      label="Frontend URL"
      instructions="Absolute base URL that GET requests to the site root are redirected to."
      :tip="`Default: ${defaultFrontendUrl}`"
    >
      <TextInput id="tk-redirect-url" v-model="form.frontendUrl" type="url" :placeholder="defaultFrontendUrl" />
    </Field>

    <div class="field">
      <p class="light">
        Resolved frontend URL: {{ resolvedFrontendUrl }}
      </p>
    </div>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save settings
      </Btn>
    </div>
  </form>
</template>
