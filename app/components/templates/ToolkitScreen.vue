<script setup lang="ts">
import type { Component } from 'vue'
import type { NavItem } from '~/components/organisms/ToolkitNav.vue'

interface SectionTab {
  id: string
  label: string
  url: string
}

interface ToolkitScreenProps {
  section: string
  // The nav item the section is under: its group (Assets, Redirects) or itself
  navId: string
  title: string
  nav: NavItem[]
  // The group's sections, each its own page: none for a section on its own
  tabs: SectionTab[]
  props: Record<string, any>
}

const props = defineProps<ToolkitScreenProps>()

const screens: Record<string, () => Promise<Component>> = {
  'health': () => import('./screens/HealthScreen.vue'),
  'site-config': () => import('./screens/SiteConfigScreen.vue'),
  'kv-cache': () => import('./screens/KvCacheScreen.vue'),
  'cloudflare': () => import('./screens/CloudflareScreen.vue'),
  'static-labels': () => import('./screens/StaticLabelsScreen.vue'),
  'average-color': () => import('./screens/AverageColorScreen.vue'),
  'redirect': () => import('./screens/RedirectScreen.vue'),
  'redirects': () => import('./screens/RedirectsScreen.vue'),
  'org-schema': () => import('./screens/OrgSchemaScreen.vue'),
  'seo': () => import('./screens/SeoScreen.vue'),
  'streaming-video': () => import('./screens/StreamingVideoScreen.vue'),
  'unused-assets': () => import('./screens/UnusedAssetsScreen.vue'),
  'advanced': () => import('./screens/AdvancedScreen.vue'),
}

const screen = defineAsyncComponent(screens[props.section]!)
const icon = computed(() => props.nav.find(item => item.id === props.navId)?.iconSvg ?? null)

// Saves answer with the screen's props again: the screen shows what was stored
const state = ref(props.props)
</script>

<template>
  <ToolkitNav :items="nav" :active="navId" />
  <div class="pane">
    <div class=":uno: mb-2 flex gap-2 items-center">
      <RawIcon :svg="icon" class=":uno: text-gray-500 size-5" />
      <h2 class=":uno: m-0">
        {{ title }}
      </h2>
    </div>
    <nav v-if="tabs.length" class=":uno: mb-4 border-b border-hairline flex flex-wrap gap-5" :aria-label="`${title} sections`">
      <a
        v-for="tab in tabs"
        :key="tab.id"
        :href="tab.url"
        class=":uno: py-2 border-b-2 no-underline -mb-px"
        :class="tab.id === section ? ':uno: text-text font-600 border-primary' : ':uno: text-light border-transparent hover:text-text'"
        :aria-current="tab.id === section ? 'page' : undefined"
      >
        {{ tab.label }}
      </a>
    </nav>
    <component :is="screen" v-bind="state" @saved="state = $event" />
  </div>
</template>
