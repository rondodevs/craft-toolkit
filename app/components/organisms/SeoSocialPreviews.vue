<script setup lang="ts">
// Approximations of how each network renders a shared link, and what to fix before sharing
export interface SeoPreviewImage {
  url: string
  width: number | null
  height: number | null
  filename: string
}

export interface SeoPreviewValues {
  title: string
  description: string
  ogTitle: string
  ogDescription: string
  siteName: string
  domain: string
  url: string | null
  image: SeoPreviewImage | null
}

interface SeoSocialPreviewsProps {
  values: SeoPreviewValues
  // For the narrow element sidebar: no frame, the networks in a select
  compact?: boolean
}

const props = defineProps<SeoSocialPreviewsProps>()

const tabs = [
  { id: 'google', label: 'Google' },
  { id: 'facebook', label: 'Facebook' },
  { id: 'x', label: 'X' },
  { id: 'linkedin', label: 'LinkedIn' },
  { id: 'whatsapp', label: 'WhatsApp' },
  { id: 'slack', label: 'Slack' },
]
const tab = ref('google')
const networkOptions = tabs.map(({ id, label }) => ({ label, value: id }))

type Check = ['ok' | 'notice' | 'warning' | 'error', string]

const checks = computed<Check[]>(() => {
  const { title, description, image } = props.values
  const list: Check[] = []

  if (!title)
    list.push(['error', 'No title.'])
  else if (title.length > 60)
    list.push(['warning', `Title is ${title.length} characters: Google cuts it at about 60.`])

  if (!description)
    list.push(['warning', 'No description: Google and social networks pick some text from the page.'])
  else if (description.length > 160)
    list.push(['warning', `Description is ${description.length} characters: Google cuts it at about 160.`])

  if (!image)
    list.push(['warning', 'No social image: links are shared without a picture, or with the site fallback.'])
  else if (image.width && image.height && (image.width < 1200 || image.height < 630))
    list.push(['warning', `Image is ${image.width} × ${image.height}: at least 1200 × 630 looks sharp everywhere.`])
  else if (image.width && image.height && Math.abs(image.width / image.height - 1.91) > 0.3)
    list.push(['notice', `Image is ${image.width} × ${image.height}: networks crop it to 1.91:1, check the previews.`])

  return list.length ? list : [['ok', 'Title, description and image look good.']]
})
</script>

<template>
  <div :class="compact ? ':uno: min-w-0' : ':uno: mb-5 px-4 pb-4 pt-3 border border-hairline rounded-2 bg-gray-050'">
    <SelectInput v-if="compact" v-model="tab" :options="networkOptions" small fullwidth class=":uno: mb-3" aria-label="Network" />
    <TabGroup v-else v-model="tab" :tabs label="Previews" small class=":uno: mb-3 flex-wrap" />

    <div class="panel">
      <div v-if="tab === 'google'" class="google">
        <div class="google-site">
          <span class="google-favicon" aria-hidden="true" />
          <span>
            <span class="google-sitename">{{ values.siteName }}</span>
            <span class="google-url">{{ values.url }}</span>
          </span>
        </div>
        <div class="google-title">
          {{ values.title }}
        </div>
        <div class="google-description">
          {{ values.description }}
        </div>
      </div>

      <div v-else-if="tab === 'facebook'" class="facebook">
        <SeoPreviewImageBox :image="values.image" />
        <div class="facebook-body">
          <div class="facebook-domain">
            {{ values.domain }}
          </div>
          <div class="facebook-title">
            {{ values.ogTitle }}
          </div>
          <div class="facebook-description">
            {{ values.ogDescription }}
          </div>
        </div>
      </div>

      <div v-else-if="tab === 'x'">
        <div class="x-card">
          <SeoPreviewImageBox :image="values.image" />
          <div v-if="values.ogTitle" class="x-overlay">
            {{ values.ogTitle }}
          </div>
        </div>
        <div class="x-from">
          From {{ values.domain }}
        </div>
      </div>

      <div v-else-if="tab === 'linkedin'" class="linkedin">
        <SeoPreviewImageBox :image="values.image" />
        <div class="linkedin-body">
          <div class="linkedin-title">
            {{ values.ogTitle }}
          </div>
          <div class="linkedin-domain">
            {{ values.domain }}
          </div>
        </div>
      </div>

      <div v-else-if="tab === 'whatsapp'" class="whatsapp">
        <div class="whatsapp-bubble">
          <div class="whatsapp-card">
            <SeoPreviewImageBox :image="values.image" />
            <div class="whatsapp-body">
              <div class="whatsapp-title">
                {{ values.ogTitle }}
              </div>
              <div class="whatsapp-description">
                {{ values.ogDescription }}
              </div>
              <div class="whatsapp-domain">
                {{ values.domain }}
              </div>
            </div>
          </div>
          <div class="whatsapp-link">
            {{ values.url }}
          </div>
        </div>
      </div>

      <div v-else class="slack">
        <div class="slack-site">
          {{ values.siteName }}
        </div>
        <div class="slack-title">
          {{ values.ogTitle }}
        </div>
        <div class="slack-description">
          {{ values.ogDescription }}
        </div>
        <SeoPreviewImageBox :image="values.image" class="slack-image" />
      </div>
    </div>

    <ul class=":uno: m-0 mt-3 p-0 list-none">
      <li v-for="[level, message] in checks" :key="message" class="check" :class="level">
        {{ message }}
      </li>
    </ul>
  </div>
</template>

<style scoped>
.panel {
  min-width: 0;
  max-width: 520px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
}

.google {
  padding: 12px 16px;
  border-radius: 8px;
  background: #fff;
  font-family: Arial, sans-serif;
}

.google-site {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}

.google-favicon {
  flex: none;
  width: 26px;
  height: 26px;
  border: 1px solid #dadce0;
  border-radius: 50%;
  background: #f1f3f4;
}

.google-sitename {
  display: block;
  color: #202124;
  font-size: 14px;
}

.google-site > span:last-child {
  min-width: 0;
}

.google-url {
  display: block;
  max-width: 420px;
  overflow: hidden;
  color: #4d5156;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.google-title {
  overflow: hidden;
  color: #1a0dab;
  font-size: 20px;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.google-description {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: #4d5156;
  font-size: 14px;
  line-height: 1.58;
}

.facebook {
  border: 1px solid #dadde1;
  background: #fff;
}

.facebook-body {
  padding: 10px 12px;
  border-top: 1px solid #dadde1;
  background: #f0f2f5;
}

.facebook-domain {
  color: #65676b;
  font-size: 12px;
  text-transform: uppercase;
}

.facebook-title {
  margin: 3px 0 2px;
  overflow: hidden;
  color: #050505;
  font-size: 16px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.facebook-description {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
  overflow: hidden;
  color: #65676b;
  font-size: 14px;
}

.x-card {
  position: relative;
  overflow: hidden;
  border: 1px solid #cfd9de;
  border-radius: 16px;
}

.x-overlay {
  position: absolute;
  bottom: 12px;
  left: 12px;
  max-width: calc(100% - 24px);
  padding: 2px 6px;
  overflow: hidden;
  border-radius: 4px;
  background: rgb(0 0 0 / 77%);
  color: #fff;
  font-size: 13px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.x-from {
  margin-top: 4px;
  color: #536471;
  font-size: 13px;
}

.linkedin {
  overflow: hidden;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 0 0 1px rgb(0 0 0 / 8%);
}

.linkedin-body {
  padding: 8px 12px;
}

.linkedin-title {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: rgb(0 0 0 / 90%);
  font-size: 14px;
  font-weight: 600;
}

.linkedin-domain {
  margin-top: 2px;
  color: rgb(0 0 0 / 60%);
  font-size: 12px;
}

.whatsapp {
  padding: 12px;
  border-radius: 8px;
  background: #efeae2;
}

.whatsapp-bubble {
  max-width: 340px;
  margin-left: auto;
  padding: 4px 4px 6px;
  border-radius: 8px;
  background: #d9fdd3;
}

.whatsapp-card {
  overflow: hidden;
  border-radius: 6px;
  background: rgb(0 0 0 / 5%);
}

.whatsapp-body {
  padding: 6px 8px 8px;
}

.whatsapp-title {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: #111b21;
  font-size: 14px;
  font-weight: 600;
}

.whatsapp-description {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: #54656f;
  font-size: 13px;
}

.whatsapp-domain {
  margin-top: 2px;
  color: #667781;
  font-size: 12px;
}

.whatsapp-link {
  padding: 6px 6px 0;
  overflow: hidden;
  color: #027eb5;
  font-size: 14px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.slack {
  padding: 2px 0 2px 12px;
  border-left: 4px solid #ddd;
  background: #fff;
  font-family: Lato, 'Helvetica Neue', Helvetica, Arial, sans-serif;
}

.slack-site {
  color: #1d1c1d;
  font-size: 15px;
  font-weight: 700;
}

.slack-title {
  margin: 2px 0;
  color: #1264a3;
  font-size: 15px;
  font-weight: 700;
}

.slack-description {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: #1d1c1d;
  font-size: 15px;
}

.slack-image {
  width: 360px;
  max-width: 100%;
  margin-top: 8px;
  border-radius: 8px;
}

.check {
  position: relative;
  margin-top: 3px;
  padding-left: 18px;
  font-size: 12px;
}

.check::before {
  position: absolute;
  left: 0;
  font-weight: bold;
}

.check.ok::before {
  content: '✓';
  color: var(--success-color);
}

.check.notice::before {
  content: 'i';
  color: var(--gray-500);
}

.check.warning::before {
  content: '!';
  color: var(--warning-color, #c96c00);
}

.check.error::before {
  content: '×';
  color: var(--error-color);
}
</style>
