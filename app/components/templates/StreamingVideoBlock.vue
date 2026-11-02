<script setup lang="ts">
import type { StatusColor } from '~/components/atoms/StatusDot.vue'

interface SavedPoster {
  thumbUrl: string | null
  custom: boolean
  time: number | null
  pending: boolean
}

interface StreamingVideoBlockProps {
  // `toolkitStreaming`, namespaced by Craft: the asset gets it through StreamingVideoBehavior
  name: string
  assetId: number | null
  enabled: boolean
  envEnabled: boolean
  envVar: string
  status: { label: string, color: StatusColor } | null
  url: string | null
  poster: SavedPoster
  pickedTime: number | null
  posterTime: number
  candidates: number[] | null
  extensions: string[]
  volumeUid: string
}

const props = defineProps<StreamingVideoBlockProps>()

const enabled = ref(props.enabled)
const message = ref('')

// The poster as saved, and the change waiting for the asset's Save
type Staged
  = | { type: 'frame', time: number, video: HTMLVideoElement | null }
    | { type: 'image', imageId: number, thumbUrl: string | null }
    | { type: 'reset' }

const saved = ref({ ...props.poster })
const staged = shallowRef<Staged | null>(null)

const inputs = computed(() => {
  const change = staged.value

  if (!change)
    return { time: props.pickedTime ?? '', image: '', reset: '' }
  if (change.type === 'frame')
    return { time: change.time.toFixed(1), image: '', reset: '' }
  if (change.type === 'image')
    return { time: '', image: String(change.imageId), reset: '' }

  return { time: '', image: '', reset: '1' }
})

// "Restore automatic" goes back to the best suggested moment, known once the video was analysed
const automaticTime = computed(() => props.candidates?.[0] ?? null)
const isAutomatic = computed(() => staged.value ? staged.value.type === 'reset' : !saved.value.custom && props.pickedTime === null)

const kind = computed(() => {
  const change = staged.value

  if (change?.type === 'frame')
    return `Frame at ${change.time.toFixed(1)}s`
  if (change?.type === 'image')
    return 'Custom image'
  if (change?.type === 'reset')
    return automaticTime.value === null ? 'Automatic' : `Automatic · Frame at ${automaticTime.value.toFixed(1)}s`
  if (saved.value.custom)
    return 'Custom image'

  return saved.value.time !== null ? `Frame at ${saved.value.time.toFixed(1)}s` : ''
})

const currentTime = computed(() => {
  if (staged.value?.type === 'reset')
    return automaticTime.value
  if (staged.value)
    return staged.value.type === 'frame' ? staged.value.time : null

  return saved.value.time
})

function stage(change: Staged) {
  staged.value = change
  message.value = ''
}

function undo() {
  staged.value = null
  message.value = ''
}

const { select } = useElementSelector({ sources: [`volume:${props.volumeUid}`], kind: ['image'] })

async function choose() {
  const imageId = await select()

  if (imageId === null)
    return

  const result = await action<{ imageId: number, thumbUrl: string | null }>('toolkit/streaming-video/image', { imageId }, 'GET')

  if (result.ok)
    stage({ type: 'image', ...result.data })
  else
    message.value = result.message || 'Could not use that image.'
}

// The uploaded image is stored at once (it has to go somewhere), but only becomes the poster
// with the asset's Save
async function upload(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file || !props.assetId)
    return

  const data = new FormData()
  data.append('assetId', String(props.assetId))
  data.append('poster', file)
  input.value = ''
  message.value = 'Uploading…'

  const result = await action<{ imageId: number, thumbUrl: string | null }>('toolkit/streaming-video/upload-poster', data)

  if (result.ok)
    stage({ type: 'image', ...result.data })
  else
    message.value = result.message || 'Could not upload the image.'
}

const reprocess = useAsyncAction('toolkit/streaming-video/reprocess')

async function reencode() {
  const result = await reprocess.run({ assetId: props.assetId })

  message.value = result?.message || 'Could not queue the video.'
  if (result?.ok)
    runQueue()
}

// After a save that changed the poster: the new one shows once the queue extracts it
async function waitForPoster() {
  for (let attempt = 0; attempt < 90; attempt++) {
    await new Promise(resolve => setTimeout(resolve, 2000))

    const result = await action<SavedPoster & { kind: string }>('toolkit/streaming-video/poster', { assetId: props.assetId }, 'GET')

    // Changed by the editor meanwhile: leave their pending change alone
    if (staged.value)
      return
    if (!result.ok || result.data.pending)
      continue

    saved.value = { thumbUrl: result.data.thumbUrl ?? saved.value.thumbUrl, custom: result.data.kind === 'Custom image', time: result.data.time, pending: false }
    message.value = 'Poster updated.'
    return
  }

  message.value = 'The poster is still in the queue: it changes once the worker gets to it.'
}

// Jobs pushed by a save from a slideout don't start the queue on their own (no worker in DDEV,
// for one): start it like Craft does after an upload
onMounted(() => {
  if (saved.value.pending) {
    message.value = 'The poster is being updated in the queue.'
    runQueue()
    waitForPoster()
  }
})
</script>

<template>
  <div class=":uno: gap-2.5 grid">
    <p v-if="!envEnabled" class=":uno: warning has-icon m-0">
      <span class="icon" aria-hidden="true" />
      <span>Streaming is switched off on this environment (<code>{{ envVar }}</code>): nothing is encoded and the frontend plays the original file.</span>
    </p>

    <div class=":uno: flex flex-wrap gap-2 items-center">
      <Lightswitch v-model="enabled" :name="inputName(name, 'enabled')" label="Convert to HLS" />
      <span>Convert to HLS</span>
    </div>
    <p class=":uno: light m-0">
      Adaptive streaming in several qualities, encoded in the queue after saving. A progressive MP4 is encoded either way: off, the frontend plays it, and switching HLS off deletes the streams.
    </p>

    <div v-if="assetId && status" class=":uno: flex flex-wrap gap-2 items-center">
      <span><StatusDot :color="status.color" /> {{ status.label }}</span>
      <Btn :loading="reprocess.pending.value" @click="reencode">
        Re-encode
      </Btn>
    </div>

    <div class=":uno: gap-2.5 grid max-w-160">
      <span class=":uno: font-600">Poster</span>

      <div class=":uno: p-3 border border-hairline rounded-md flex flex-wrap gap-4">
        <div class=":uno: rounded-sm bg-gray-050 flex-none w-50 aspect-video overflow-hidden">
          <FrameSnapshot v-if="staged?.type === 'frame' && staged.video" :key="staged.time" :video="staged.video" class=":uno: size-full block object-cover" />
          <VideoFrame v-else-if="staged?.type === 'reset' && url && automaticTime !== null" :src="url" :time="automaticTime" class=":uno: size-full block object-cover" />
          <img v-else-if="staged?.type === 'image' && staged.thumbUrl" :src="staged.thumbUrl" alt="Poster" class=":uno: size-full block object-cover">
          <img v-else-if="!staged && saved.thumbUrl" :src="saved.thumbUrl" alt="Current poster" class=":uno: size-full block object-cover">
          <span v-else class=":uno: light text-3 p-3 text-center flex size-full items-center justify-center">
            {{ staged?.type === 'reset' ? 'The best moment, extracted after saving.' : 'Extracted in the queue after saving.' }}
          </span>
        </div>

        <!-- Title on top, actions on the thumbnail's bottom line -->
        <div class=":uno: flex flex-1 flex-col gap-3 min-w-60 justify-between">
          <div>
            <p class=":uno: font-600 m-0 flex gap-2 items-center">
              {{ kind }}
              <span v-if="staged" title="Applied when you save the asset"><Badge tone="warning">Unsaved</Badge></span>
            </p>
            <p v-if="message" class=":uno: light text-3 m-0 mt-1" aria-live="polite">
              {{ message }}
            </p>
          </div>

          <div v-if="assetId" class=":uno: flex flex-col gap-2 items-start">
            <div class=":uno: flex gap-2">
              <label class="btn">
                Upload image
                <input type="file" class="visually-hidden" :accept="extensions.map(e => `.${e}`).join(',')" @change="upload">
              </label>
              <Btn @click="choose">
                Choose from assets
              </Btn>
            </div>
            <div v-if="!isAutomatic || staged" class=":uno: text-3 flex gap-2 items-center">
              <Btn v-if="!isAutomatic" variant="tertiary" title="Back to the best moment of the video" @click="stage({ type: 'reset' })">
                Restore automatic
              </Btn>
              <template v-if="staged">
                <span v-if="!isAutomatic" class="light" aria-hidden="true">·</span>
                <Btn variant="tertiary" title="Back to the poster as saved" @click="undo">
                  Restore last
                </Btn>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- The poster change goes with the asset's Save: nothing changes until then -->
      <HiddenInput :name="inputName(name, 'posterTime')" :value="inputs.time" />
      <HiddenInput :name="inputName(name, 'posterImageId')" :value="inputs.image" />
      <HiddenInput :name="inputName(name, 'resetPoster')" :value="inputs.reset" />

      <FramePicker
        :url
        :time="posterTime"
        :candidates
        :current-time
        :can-use="!!assetId"
        @use="(time, video) => stage({ type: 'frame', time, video })"
        @invalid="message = 'Type the timestamp as 83.4 or 1:23.4.'"
      />
      <p class=":uno: light m-0">
        Drag along the strip, use the arrow keys or jump to a suggested moment{{ candidates === null ? ' (found with the first poster)' : '' }}, then <strong>Use this frame</strong>. Poster changes are applied when you save the asset.
      </p>
    </div>
  </div>
</template>
