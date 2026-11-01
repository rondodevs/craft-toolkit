// Reads the stats endpoint of nuxt-multi-cache, whatever shape the frontend gives its entries

const TAG_KEYS = ['cacheTags', 'tags']

export type Json = string | number | boolean | null | Json[] | { [key: string]: Json }
export interface JsonObject { [key: string]: Json }

export function isObject(value: unknown): value is JsonObject {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

function isFilled(value: unknown): value is string | number | boolean {
  return value !== null && value !== undefined && typeof value !== 'object' && String(value) !== ''
}

export function formatScalar(value: unknown): string {
  if (value === null)
    return 'null'

  return typeof value === 'object' ? JSON.stringify(value) : String(value)
}

/** Every cache tag in a value, nested ones too, in order of appearance */
export function collectTags(value: unknown, depth = 0, found = new Set<string>()): Set<string> {
  if (depth > 8 || value === null || typeof value !== 'object')
    return found

  if (Array.isArray(value)) {
    value.forEach(item => collectTags(item, depth + 1, found))
    return found
  }

  for (const [key, item] of Object.entries(value)) {
    if (TAG_KEYS.includes(key) && Array.isArray(item))
      item.filter(isFilled).forEach(tag => found.add(String(tag)))
    else
      collectTags(item, depth + 1, found)
  }

  return found
}

export function allTags(details: unknown): string[] {
  return [...collectTags(details)].sort()
}

export function entryKey(item: Json): string | null {
  return isObject(item) && isFilled(item.key) ? String(item.key) : null
}

export interface EntryInfo {
  title: string
  id: string
  extraCount: number
}

/** The Craft entries a cached response holds: anything with a title and an id */
export function entryInfo(item: Json): EntryInfo | null {
  const found: { title: string, id: string }[] = []

  function walk(value: unknown, depth: number) {
    if (found.length >= 5 || depth > 6 || value === null || typeof value !== 'object')
      return

    if (Array.isArray(value)) {
      value.forEach(v => walk(v, depth + 1))
      return
    }

    const object = value as JsonObject
    const id = object.id ?? object.entryId

    if (isFilled(object.title) && isFilled(id)) {
      found.push({ title: String(object.title), id: String(id) })
      return
    }

    Object.entries(object).forEach(([key, v]) => !TAG_KEYS.includes(key) && walk(v, depth + 1))
  }

  walk(item, 0)

  return found[0] ? { ...found[0], extraCount: found.length - 1 } : null
}

/** `{ total: 12, rows: [...] }`: a list with its count */
export function countedList(value: Json): { key: string, total: number } | null {
  if (!isObject(value) || typeof value.total !== 'number')
    return null

  const key = Object.keys(value).find(k => k !== 'total' && Array.isArray(value[k]))

  return key ? { key, total: value.total } : null
}

export function countLabel(value: Json): string | null {
  const counted = countedList(value)

  if (counted)
    return plural(counted.total, 'item')
  if (Array.isArray(value))
    return plural(value.length, 'item')
  if (isObject(value))
    return plural(Object.keys(value).length, 'field')

  return null
}
