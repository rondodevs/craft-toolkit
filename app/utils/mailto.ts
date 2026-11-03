// The query of a Link field's URL suffix (`?subject=…&body=…`), which Craft appends to the link's URL

export type SuffixParam = [key: string, value: string]

function decode(value: string) {
  try {
    return decodeURIComponent(value)
  }
  catch {
    return value
  }
}

/**
 * The params of a URL suffix, in order, and its `#fragment` if it has one.
 */
export function parseSuffix(suffix: string): { params: SuffixParam[], hash: string } {
  const [query = '', ...hash] = suffix.trim().replace(/^\?/, '').split('#')

  const params = query.split('&').filter(Boolean).map((pair): SuffixParam => {
    const [key = '', ...value] = pair.split('=')
    return [decode(key), decode(value.join('=')).replace(/\r\n/g, '\n')]
  })

  return { params, hash: hash.length ? `#${hash.join('#')}` : '' }
}

/**
 * Back to a URL suffix, encoded as mailto links want it (RFC 6068): spaces as %20, not +, and line breaks as %0D%0A.
 */
export function formatSuffix(params: SuffixParam[], hash = ''): string {
  const query = params
    .filter(([, value]) => value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value.replace(/\r?\n/g, '\r\n'))}`)
    .join('&')

  return `${query ? `?${query}` : ''}${hash}`
}
