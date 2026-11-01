// The parts of Craft's CP JavaScript the toolkit uses

interface CraftActionResponse<T> {
  data: T
}

interface CraftElementSelection {
  id: number
  label: string
  url?: string
  $element: unknown
}

interface CraftElementSelectorSettings {
  sources?: string[]
  criteria?: Record<string, unknown>
  multiSelect?: boolean
  onSelect: (elements: CraftElementSelection[]) => void
}

interface CraftGlobal {
  sendActionRequest: <T = unknown>(
    method: 'GET' | 'POST',
    action: string,
    options?: { data?: unknown, params?: Record<string, unknown> },
  ) => Promise<CraftActionResponse<T>>
  createElementSelectorModal: (elementType: string, settings: CraftElementSelectorSettings) => unknown
  cp?: {
    displaySuccess: (message: string) => void
    displayError: (message: string) => void
    runQueue?: () => void
  }
  Preview?: unknown
}

interface GarnishGlobal {
  on: (target: unknown, event: string, handler: (event: any) => void) => void
}

declare const Craft: CraftGlobal
declare const Garnish: GarnishGlobal
