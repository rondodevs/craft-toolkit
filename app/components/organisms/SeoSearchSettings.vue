<script setup lang="ts">
import type { SelectOption } from '~/components/atoms/SelectInput.vue'

interface SeoSearchSettingsProps {
  // The SEO field's input name (`fields[seo]`)
  name: string
  defaultRobots: string[]
  robotsOptions: SelectOption[]
}

defineProps<SeoSearchSettingsProps>()

const robots = defineModel<string[]>('robots', { required: true })
const canonical = defineModel<string>('canonical', { required: true })

const id = useId()

// In script: vue-tsc misreads a `//` in a static attribute
const placeholder = 'https://'
</script>

<template>
  <div>
    <Field label="Robots" :instructions="defaultRobots.length ? `Always applied by the field: ${defaultRobots.join(', ')}.` : undefined">
      <CheckboxGroup v-model="robots" :options="robotsOptions" :name="inputName(name, 'robots')" />
    </Field>

    <Field
      :id="`${id}-canonical`"
      label="Canonical URL"
      instructions="Empty: the page's own URL. Set it when the same content lives at another URL."
    >
      <TextInput :id="`${id}-canonical`" v-model="canonical" :name="inputName(name, 'canonical')" type="url" :placeholder />
    </Field>
  </div>
</template>
