/** Tells the frontend in Live Preview that the entry changed, so it refetches without a full reload */
export function initLivePreview() {
  if (typeof Garnish === 'undefined' || !Craft.Preview)
    return

  Garnish.on(Craft.Preview, 'beforeUpdateIframe', (event) => {
    if (!event.refresh)
      event.target.$iframe[0].contentWindow.postMessage('entry:live-preview:updated', event.previewTarget.url)
  })
}
