console.log('[Toolkit] Script Asset Initialized ...')

Garnish.on(Craft.Preview, 'beforeUpdateIframe', function (event) {
  if (!event.refresh) {
    event.target.$iframe[0].contentWindow.postMessage(
        'entry:live-preview:updated',
        event.previewTarget.url
    )
  }
})