export function escapeHtml(value: unknown): string {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/** `code` and **bold** in instructions, as Craft renders them with Markdown */
export function inlineMarkdown(text: string): string {
  return escapeHtml(text)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
}

export function plural(count: number, one: string, many = `${one}s`): string {
  return `${count} ${count === 1 ? one : many}`
}

/** `orgSchema[it][addresses][0]` from a base name and a path */
export function inputName(base: string, ...path: (string | number)[]): string {
  return base + path.map(part => `[${part}]`).join('')
}

export function filesize(bytes: number): string {
  const units = ['B', 'kB', 'MB', 'GB', 'TB']
  let size = bytes
  let unit = 0

  while (size >= 1000 && unit < units.length - 1) {
    size /= 1000
    unit++
  }

  return `${unit === 0 ? size : size.toFixed(1)} ${units[unit]}`
}
