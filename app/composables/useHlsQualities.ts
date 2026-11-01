import type Hls from 'hls.js'

interface Level {
  uri: string
  name: string
  height: number | null
  bandwidth: number
}

export interface QualityOption {
  key: string
  label: string
  title: string
}

// The qualities listed in the master playlist, largest first
async function readLevels(masterUrl: string): Promise<Level[]> {
  const lines = (await (await fetch(masterUrl)).text()).split('\n')
  const levels: Level[] = []

  lines.forEach((line, index) => {
    if (!line.startsWith('#EXT-X-STREAM-INF'))
      return

    const uri = (lines[index + 1] ?? '').trim()

    levels.push({
      uri: new URL(uri, masterUrl).href,
      name: uri.replace(/\.m3u8$/, ''),
      height: Number(/RESOLUTION=\d+x(\d+)/.exec(line)?.[1]) || null,
      bandwidth: Number(/BANDWIDTH=(\d+)/.exec(line)?.[1]) || 0,
    })
  })

  return levels.sort((a, b) => b.bandwidth - a.bandwidth)
}

/**
 * A quality menu for a video: the original file, HLS on Auto, or one HLS quality, to check each
 * encoded stream. Switching keeps the position and the play state.
 */
export function useHlsQualities(video: Ref<HTMLVideoElement | null | undefined>, playlistUrl: string, originalUrl: string) {
  const masterUrl = new URL(playlistUrl, window.location.href).href
  const levels = ref<Level[]>([])
  const selected = ref('original')
  const playingHeight = ref<number | null>(null)
  let hls: Hls | null = null

  const options = computed<QualityOption[]>(() => [
    { key: 'original', label: 'Original', title: 'The uploaded file' },
    { key: 'auto', label: playingHeight.value ? `Auto · ${playingHeight.value}p` : 'Auto', title: 'HLS, the quality picked by the player' },
    ...levels.value.map((level, index) => ({
      key: String(index),
      label: level.height ? `${level.height}p` : level.name,
      title: `HLS, ${Math.round(level.bandwidth / 1000)} kbps peak`,
    })),
  ])

  function keepingPosition(change: (el: HTMLVideoElement) => void) {
    const el = video.value!
    const time = el.currentTime
    const playing = !el.paused

    change(el)
    el.addEventListener('loadedmetadata', () => {
      el.currentTime = time
      if (playing)
        el.play().catch(() => {})
    }, { once: true })
  }

  function destroy() {
    hls?.destroy()
    hls = null
  }

  function levelIndex(level: Level | null): number {
    if (!level || !hls)
      return -1

    // hls.js keeps the playlist URL as `uri` (a string) or `url` (an array)
    return hls.levels.findIndex((candidate: any) => {
      const uri = candidate.uri || (Array.isArray(candidate.url) ? candidate.url[0] : candidate.url) || ''

      return uri.includes(`${level.name}.m3u8`)
    })
  }

  async function select(key: string) {
    selected.value = key
    playingHeight.value = null

    if (!video.value)
      return

    if (key === 'original') {
      keepingPosition((el) => {
        destroy()
        el.src = originalUrl
        el.load()
      })
      return
    }

    const level = key === 'auto' ? null : levels.value[Number(key)] ?? null

    try {
      const { default: HlsClass } = await import('hls.js/light')

      // Safari without MSE: native HLS plays the master or one quality's playlist
      if (!HlsClass.isSupported()) {
        keepingPosition((el) => {
          el.src = level ? level.uri : masterUrl
          el.load()
        })
        return
      }

      if (hls) {
        hls.currentLevel = levelIndex(level)
        return
      }

      keepingPosition((el) => {
        const instance = new HlsClass({ capLevelToPlayerSize: false })

        hls = instance
        instance.on(HlsClass.Events.MANIFEST_PARSED, () => {
          instance.currentLevel = levelIndex(level)
        })
        instance.on(HlsClass.Events.LEVEL_SWITCHED, (_, data) => {
          if (instance.autoLevelEnabled)
            playingHeight.value = instance.levels[data.level]?.height ?? null
        })
        instance.on(HlsClass.Events.ERROR, (_, data) => {
          if (data.fatal)
            select('original')
        })
        el.removeAttribute('src')
        instance.loadSource(masterUrl)
        instance.attachMedia(el)
      })
    }
    catch {
      select('original')
    }
  }

  readLevels(masterUrl).then(found => levels.value = found).catch(() => {})
  onBeforeUnmount(destroy)

  return { options, selected, select }
}
