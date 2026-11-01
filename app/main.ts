import { initLivePreview } from './utils/livePreview'
import 'virtual:uno.css'

// Loaded on every CP page: Vue and each island come in only where the page has one

const unmounts = new WeakMap<HTMLElement, () => void>()

async function mount(el: HTMLElement) {
  if (unmounts.has(el))
    return

  unmounts.set(el, () => {})
  const { mountIsland } = await import('./islands')

  // Closed (a slideout, say) while its code was loading
  if (el.isConnected)
    unmounts.set(el, await mountIsland(el))
}

function mountAll(root: ParentNode) {
  root.querySelectorAll<HTMLElement>('[data-tk-app]').forEach(mount)
}

// Craft moves nodes around (remove, then insert again): only an island that left the page goes
function unmountAll(node: HTMLElement) {
  for (const el of [node, ...node.querySelectorAll<HTMLElement>('[data-tk-app]')]) {
    if (el.isConnected || !unmounts.has(el))
      continue

    unmounts.get(el)!()
    unmounts.delete(el)
  }
}

// Slideouts, element editors and modals add islands after load, and take them away on close
new MutationObserver((mutations) => {
  for (const mutation of mutations) {
    mutation.removedNodes.forEach(node => node instanceof HTMLElement && unmountAll(node))
    mutation.addedNodes.forEach(node => node instanceof HTMLElement && mountAll(node.parentNode ?? node))
  }
}).observe(document.body, { childList: true, subtree: true })

mountAll(document)
initLivePreview()
