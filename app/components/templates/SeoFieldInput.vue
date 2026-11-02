<script setup lang="ts">
import type { SchemaPiecesInputProps } from '~/components/organisms/SchemaPiecesInput.vue'
import type { SeoPreviewImage } from '~/components/organisms/SeoSocialPreviews.vue'
import type { PickedAsset } from '~/composables/useElementSelector'

interface SeoValue {
  title: string
  description: string
  ogTitle: string
  ogDescription: string
  imageId: number | null
  robots: string[]
  canonical: string
  aiSummary: string
  aiExclude: boolean
}

interface SeoFieldInputProps {
  // The field's input name, namespaced by Craft (`fields[seo]`)
  name: string
  value: SeoValue
  auto: {
    title: string
    titleSource: string
    description: string
    descriptionSource: string
    imageFilename: string | null
  }
  preview: {
    url: string | null
    siteName: string
    domain: string
    customImage: SeoPreviewImage | null
    autoImage: SeoPreviewImage | null
  }
  customImageTitle: string | null
  robotsOptions: string[]
  defaultRobots: string[]
  descriptionMaxLength: number
  schema: SchemaPiecesInputProps
}

const props = defineProps<SeoFieldInputProps>()

const id = useId()

// In script: vue-tsc misreads a `//` in a static attribute
const placeholders = { url: 'https://' }
const fieldName = (key: string) => inputName(props.name, key)

// Title and description come prefilled with their automatic values, as Ether did
const form = reactive({
  ...props.value,
  title: props.value.title || props.auto.title,
  titleAuto: !props.value.title,
  description: props.value.description || props.auto.description,
  descriptionAuto: !props.value.description,
})

const image = ref<PickedAsset | null>(props.value.imageId
  ? { id: props.value.imageId, title: props.customImageTitle ?? '', thumbUrl: props.preview.customImage?.url ?? null }
  : null)
const customImage = ref(props.preview.customImage)

// A picked image comes back with the size the previews and checks need
async function resolveImage(assetId: number): Promise<PickedAsset | null> {
  const result = await action<{ image: SeoPreviewImage | null }>('toolkit/seo/image-preview', { id: assetId })

  if (!result.ok || !result.data.image)
    return null

  customImage.value = result.data.image

  return { id: assetId, title: result.data.image.filename, thumbUrl: result.data.image.url }
}

const previews = computed(() => {
  const title = form.title.trim()
  const description = form.description.trim()

  return {
    title,
    description,
    ogTitle: form.ogTitle.trim() || title,
    ogDescription: form.ogDescription.trim() || description,
    siteName: props.preview.siteName,
    domain: props.preview.domain,
    url: props.preview.url,
    image: image.value ? customImage.value : props.preview.autoImage,
  }
})

function sourceNote(source: string, fallback: string) {
  return source ? `Empty: taken from ${source}.` : fallback
}

const robotsOptions = computed(() => props.robotsOptions
  .filter(option => !props.defaultRobots.includes(option))
  .map(option => ({ label: option, value: option })))

const imageInstructions = computed(() => props.auto.imageFilename
  ? `Empty: ${props.auto.imageFilename}. Best at 1200 × 630.`
  : 'Empty: no image, unless the rules name a source or the field a default. Best at 1200 × 630.')
</script>

<template>
  <div>
    <SeoSocialPreviews :values="previews" />

    <Field :id="`${id}-title`" label="Title">
      <template #instructions>
        <p>{{ sourceNote(auto.titleSource, 'Automatic: the entry title.') }} Edit it freely, site name included: once changed it stays as typed.</p>
      </template>
      <SeoTextInput
        :id="`${id}-title`"
        v-model="form.title"
        v-model:automatic="form.titleAuto"
        :name="fieldName('title')"
        :auto-value="auto.title"
        :limit="60"
        reset-label="Back to the automatic title"
      />
    </Field>

    <Field :id="`${id}-description`" label="Description">
      <template #instructions>
        <p>{{ sourceNote(auto.descriptionSource, 'Automatic: none, unless the rules name a source.') }} Once changed it stays as typed.</p>
      </template>
      <SeoTextInput
        :id="`${id}-description`"
        v-model="form.description"
        v-model:automatic="form.descriptionAuto"
        :name="fieldName('description')"
        :auto-value="auto.description"
        :limit="descriptionMaxLength"
        multiline
        :rows="3"
        reset-label="Back to the automatic description"
      />
    </Field>

    <details class=":uno: mt-3 pt-3 border-t border-hairline" :open="!!(value.ogTitle || value.ogDescription || value.imageId)">
      <summary class=":uno: font-bold mb-3 cursor-pointer">
        Social sharing
      </summary>

      <Field :id="`${id}-ogTitle`" label="Social title" instructions="Empty: the title.">
        <SeoTextInput :id="`${id}-ogTitle`" v-model="form.ogTitle" :name="fieldName('ogTitle')" :placeholder="previews.title" :limit="60" />
      </Field>

      <Field :id="`${id}-ogDescription`" label="Social description" instructions="Empty: the description.">
        <SeoTextInput
          :id="`${id}-ogDescription`"
          v-model="form.ogDescription"
          :name="fieldName('ogDescription')"
          :placeholder="previews.description"
          :limit="110"
          multiline
          :rows="2"
        />
      </Field>

      <Field label="Social image" :instructions="imageInstructions">
        <AssetPicker v-model="image" :name="fieldName('imageId')" :resolve="resolveImage" label="Choose an image" />
      </Field>
    </details>

    <details class=":uno: mt-3 pt-3 border-t border-hairline" :open="!!(value.robots.length || value.canonical)">
      <summary class=":uno: font-bold mb-3 cursor-pointer">
        Search engines
      </summary>

      <Field label="Robots" :instructions="defaultRobots.length ? `Always applied by the field: ${defaultRobots.join(', ')}.` : undefined">
        <CheckboxGroup v-model="form.robots" :options="robotsOptions" :name="fieldName('robots')" />
      </Field>

      <Field
        :id="`${id}-canonical`"
        label="Canonical URL"
        instructions="Empty: the page's own URL. Set it when the same content lives at another URL."
      >
        <TextInput :id="`${id}-canonical`" v-model="form.canonical" :name="fieldName('canonical')" type="url" :placeholder="placeholders.url" />
      </Field>
    </details>

    <details class=":uno: mt-3 pt-3 border-t border-hairline" :open="!!(value.aiSummary || value.aiExclude)">
      <summary class=":uno: font-bold mb-3 cursor-pointer">
        AI assistants
      </summary>

      <Field
        :id="`${id}-aiSummary`"
        label="AI summary"
        instructions="A short, factual summary for ChatGPT, Perplexity, Gemini and the like: what the page is about, for whom, the key facts. Used for llms.txt. Empty: the description."
      >
        <TextArea :id="`${id}-aiSummary`" v-model="form.aiSummary" :name="fieldName('aiSummary')" :placeholder="previews.description" />
      </Field>

      <Field
        :id="`${id}-aiExclude`"
        label="Exclude from AI"
        instructions="Leaves the page out of llms.txt and adds noai, noimageai to the robots directives."
      >
        <Lightswitch :id="`${id}-aiExclude`" v-model="form.aiExclude" :name="fieldName('aiExclude')" />
      </Field>
    </details>

    <details class=":uno: mt-3 pt-3 border-t border-hairline" :open="!!schema.rows.length">
      <summary class=":uno: font-bold mb-3 cursor-pointer">
        Structured data
      </summary>

      <p class="light">
        schema.org pieces for this page (Article, Product, Event, FAQPage, a clinic's address and hours…), on top of the site's identity from Toolkit → Org Schema.
      </p>
      <SchemaPiecesInput v-bind="schema" />
    </details>
  </div>
</template>
