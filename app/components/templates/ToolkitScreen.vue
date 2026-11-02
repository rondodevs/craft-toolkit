<script setup lang="ts">
import type { Component } from 'vue'
import type { NavItem } from '~/components/organisms/ToolkitNav.vue'

interface ToolkitScreenProps {
  section: string
  title: string
  nav: NavItem[]
  props: Record<string, any>
}

const props = defineProps<ToolkitScreenProps>()

const screens: Record<string, () => Promise<Component>> = {
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
const icon = computed(() => props.nav.find(item => item.id === props.section)?.iconSvg ?? null)

// Saves answer with the screen's props again: the screen shows what was stored
const state = ref(props.props)
</script>

<template>
  <ToolkitNav :items="nav" :active="section" />
  <div class="pane">
    <div class=":uno: mb-2 flex gap-2 items-center">
      <RawIcon :svg="icon" class=":uno: text-gray-500 size-5" />
      <h2 class=":uno: m-0">
        {{ title }}
      </h2>
    </div>
    <component :is="screen" v-bind="state" @saved="state = $event" />
  </div>
</template>
