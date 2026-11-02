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
  // Shared with the SEO box in the element's sidebar
  previewKey: string
  // The published page, for Google's Rich Results Test
  testUrl: string | null
}

const props = defineProps<SeoFieldInputProps>()

const id = useId()

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

const previewOpen = ref(false)

const { inSidebar } = provideSeoField(props.previewKey, {
  previews,
  search: {
    name: props.name,
    defaultRobots: props.defaultRobots,
    robotsOptions: robotsOptions.value,
    robots: toRef(form, 'robots'),
    canonical: toRef(form, 'canonical'),
  },
})

// Robots and canonical live in the sidebar box when the element editor has one
const tabs = computed(() => [
  { id: 'general', label: 'General' },
  { id: 'social', label: 'Social sharing' },
  ...(inSidebar.value ? [] : [{ id: 'search', label: 'Search engines' }]),
  { id: 'ai', label: 'AI assistants' },
  { id: 'schema', label: 'Structured data' },
])
const tab = ref('general')

watch(inSidebar, (value) => {
  if (value && tab.value === 'search')
    tab.value = 'general'
})

const imageInstructions = computed(() => props.auto.imageFilename
  ? `Empty: ${props.auto.imageFilename}. Best at 1200 × 630.`
  : 'Empty: no image, unless the rules name a source or the field a default. Best at 1200 × 630.')
</script>

<template>
  <div>
    <div class=":uno: field flex flex-wrap gap-2 items-center justify-between">
      <TabGroup v-model="tab" :tabs label="SEO sections" />
      <Btn @click="previewOpen = true">
        Preview
      </Btn>
    </div>

    <!-- Follows what's typed, whatever tab is open -->
    <Modal v-model="previewOpen" title="Search and social previews">
      <SeoSocialPreviews :values="previews" />
    </Modal>

    <div v-show="tab === 'general'" role="tabpanel">
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
    </div>

    <div v-show="tab === 'social'" role="tabpanel">
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
    </div>

    <!-- v-if: the inputs are in one place only, here or in the sidebar -->
    <SeoSearchSettings
      v-if="!inSidebar"
      v-show="tab === 'search'"
      v-model:robots="form.robots"
      v-model:canonical="form.canonical"
      role="tabpanel"
      :name
      :default-robots
      :robots-options
    />

    <div v-show="tab === 'ai'" role="tabpanel">
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
    </div>

    <div v-show="tab === 'schema'" role="tabpanel">
      <p class="light">
        schema.org pieces for this page (Article, Product, Event, FAQPage, a clinic's address and hours…), on top of the site's identity from Toolkit → Org Schema.
      </p>
      <SchemaPiecesInput v-bind="schema" :test-url />
    </div>
  </div>
</template>
