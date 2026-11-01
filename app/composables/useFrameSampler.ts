/**
 * Draws frames of a video into canvases, one seek at a time, from a hidden copy of it: the
 * visible video keeps its position. Canvases, not image URLs: a video on another domain (a CDN)
 * can be drawn, but not read back.
 */
export function useFrameSampler() {
  let sampler: HTMLVideoElement | null = null

  function stop() {
    if (!sampler)
      return

    sampler.removeAttribute('src')
    sampler.load()
    sampler = null
  }

  function draw(src: string, jobs: { time: number, canvas: HTMLCanvasElement }[]) {
    stop()

    if (!jobs.length)
      return

    const video = document.createElement('video')
    let index = 0

    sampler = video
    video.muted = true
    video.playsInline = true
    video.preload = 'auto'
    video.src = src
    video.addEventListener('loadeddata', () => {
      video.currentTime = jobs[0]!.time
    }, { once: true })
    video.addEventListener('seeked', () => {
      const { canvas } = jobs[index]!

      canvas.height = Math.round(canvas.width * video.videoHeight / video.videoWidth) || canvas.height
      canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
      index++

      if (index < jobs.length)
        video.currentTime = jobs[index]!.time
      else
        stop()
    })
  }

  onBeforeUnmount(stop)

  return { draw }
}
