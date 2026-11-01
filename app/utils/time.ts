/** Seconds as "1:23.4" */
export function formatTime(seconds: number): string {
  const minutes = Math.floor(seconds / 60)
  const rest = (seconds - minutes * 60).toFixed(1)

  return `${minutes}:${Number(rest) < 10 ? '0' : ''}${rest}`
}

/** "83.4", "83,4" or "1:23.4" to seconds */
export function parseTime(text: string): number | null {
  let seconds = 0

  for (const part of String(text).trim().replace(',', '.').split(':')) {
    const value = Number.parseFloat(part)

    if (Number.isNaN(value))
      return null

    seconds = seconds * 60 + value
  }

  return Math.max(0, seconds)
}
