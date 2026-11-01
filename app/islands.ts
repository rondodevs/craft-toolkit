import type { Component } from 'vue'
import { createApp, h, nextTick } from 'vue'

// The PHP side renders `<div data-tk-app="name" data-props="{…}">` (Island::render)
const islands: Record<string, () => Promise<{ default: Component }>> = {
  'screen': () => import('./components/templates/ToolkitScreen.vue'),
  'seo-field': () => import('./components/templates/SeoFieldInput.vue'),
  'org-schema-field': () => import('./components/templates/OrgSchemaFieldInput.vue'),
  'streaming-video': () => import('./components/templates/StreamingVideoBlock.vue'),
  'hls-preview': () => import('./components/templates/HlsPreview.vue'),
}

let count = 0

/**
 * Craft keeps each form's serialized value from page load (`initialSerializedValue`) to warn about
 * unsaved changes and to post only what changed. An island adds its inputs after that: a form
 * that was clean before the island mounted is clean after it too.
 */
async function keepFormsClean(el: HTMLElement, mount: () => void) {
  const $ = (window as any).jQuery

  const forms = $
    ? $(el).parents('form').toArray().map((form: HTMLFormElement) => {
        const $form = $(form)
        const serialize = $form.data('serializer') || (() => $form.serialize())

        return { $form, serialize, clean: $form.data('initialSerializedValue') === serialize() }
      })
    : []

  mount()
  await nextTick()

  for (const { $form, serialize, clean } of forms) {
    if (clean)
      $form.data('initialSerializedValue', serialize())
  }
}

export async function mountIsland(el: HTMLElement): Promise<() => void> {
  const load = islands[el.dataset.tkApp ?? '']

  if (!load)
    return () => {}

  const { default: component } = await load()
  const props = JSON.parse(el.dataset.props || '{}')
  const app = createApp({ render: () => h(component, props) })

  // Ids stay unique with many islands on one page (a field in a slideout, say)
  app.config.idPrefix = `tk${++count}`
  await keepFormsClean(el, () => app.mount(el))

  return () => app.unmount()
}
