import type { Ref } from 'vue'
import type { SelectOption } from '~/components/atoms/SelectInput.vue'
import type { SeoPreviewValues } from '~/components/organisms/SeoSocialPreviews.vue'

/** What the SEO field shares with its box in the element's sidebar */
export interface SeoFieldShared {
  previews: Ref<SeoPreviewValues>
  /** Robots and canonical: in the sidebar when it's there, in the field otherwise */
  search: {
    name: string
    defaultRobots: string[]
    robotsOptions: SelectOption[]
    robots: Ref<string[]>
    canonical: Ref<string>
  }
}

// Shared by the islands of one page
const fields = shallowReactive(new Map<string, SeoFieldShared>())
const sidebarHosts = shallowReactive(new Set<string>())

/**
 * The key ties an element's SEO field to its sidebar box: element, site and
 * field handle (SeoField::previewKey()), so slideouts don't mix them up.
 */
export function provideSeoField(key: string, shared: SeoFieldShared) {
  fields.set(key, shared)
  onScopeDispose(() => {
    if (fields.get(key) === shared)
      fields.delete(key)
  })

  return { inSidebar: computed(() => sidebarHosts.has(key)) }
}

/** The sidebar box: takes over the robots and canonical inputs while it's on the page */
export function useSeoSidebar(key: string) {
  sidebarHosts.add(key)
  onScopeDispose(() => sidebarHosts.delete(key))

  return computed(() => fields.get(key) ?? null)
}
