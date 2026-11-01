export interface PickedAsset {
  id: number
  title: string
  thumbUrl: string | null
}

interface ElementSelectorOptions {
  sources?: string[]
  kind?: string[]
}

/** Craft's own asset picker modal, so sources, uploads and search work as anywhere in the CP */
export function useElementSelector(options: ElementSelectorOptions = {}) {
  function select(): Promise<number | null> {
    return new Promise((resolve) => {
      Craft.createElementSelectorModal('craft\\elements\\Asset', {
        sources: options.sources,
        criteria: options.kind ? { kind: options.kind } : undefined,
        multiSelect: false,
        onSelect: elements => resolve(elements[0]?.id ?? null),
      })
    })
  }

  return { select }
}
