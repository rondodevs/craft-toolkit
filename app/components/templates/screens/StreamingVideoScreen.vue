<script setup lang="ts">
import type { SelectOption } from '~/components/atoms/SelectInput.vue'
import type { StatusColor } from '~/components/atoms/StatusDot.vue'

interface StreamingVideoScreenProps {
  enabled: boolean
  envVar: string
  statuses: { value: string, label: string, color: StatusColor, count: number }[]
  failures: { assetId: number, title: string | null, cpEditUrl: string | null, error: string | null, dateUpdated: string }[]
  settings: { qualities: number[], aboveHd: boolean }
  qualityOptions: SelectOption[]
  segmentDuration: number
  defaultPosterTime: number
  aboveHdBitrates: Record<'1440' | '2160', number>
}

const props = defineProps<StreamingVideoScreenProps>()

const emit = defineEmits<{
  saved: [props: StreamingVideoScreenProps]
}>()

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'settings', label: 'Settings' },
  { id: 'costs', label: 'Costs' },
  { id: 'help', label: 'Help' },
]
const tab = ref('overview')

const form = useForm(() => props.settings)
const { save, saving } = useScreenSave<StreamingVideoScreenProps>('toolkit/streaming-video/save', p => emit('saved', p))
const queue = useScreenSave<StreamingVideoScreenProps>('toolkit/streaming-video/queue', p => emit('saved', p))

async function queueVideos() {
  const result = await queue.save({})

  if (result.ok)
    runQueue()
}

const aboveHdInstructions = computed(() => `A 1440p source also gets 1440p (${props.aboveHdBitrates['1440']} kbps), a 4K source 1440p and 2160p (${props.aboveHdBitrates['2160']} kbps), so players step up gradually. Sizes in between get the nearest quality below them. A 4K video takes about 190 MB more per minute, and encoding 4K with H.264 on a small server is several times slower than real time. Viewers only get these on large, high-density screens when the frontend caps the quality to the player size.`)
</script>

<template>
  <p class="light">
    Converts the videos you switch on to HLS (adaptive streaming, several qualities) in the queue, next to the original file in the same volume, and adds <code>hlsPlaylistUrl</code> and <code>videoPoster</code> to assets in GraphQL. The frontend plays it with hls.js and falls back to the original file while a video is encoding.
  </p>

  <div class="field">
    <TabGroup v-model="tab" :tabs label="Streaming Video sections" hash />
  </div>

  <div v-show="tab === 'overview'" role="tabpanel">
    <div class=":uno: mb-4 px-3.5 py-2.5 rounded-md bg-gray-050 flex gap-2 items-center">
      <template v-if="!enabled">
        <StatusDot color="grey" />
        <div>
          <strong>Switched off.</strong>
          Set <code>{{ envVar }}=true</code> on the app and redeploy: on the craft-php image the same variable installs FFmpeg in the worker. Until then nothing is encoded and <code>hlsPlaylistUrl</code> is always <code>null</code>, so the frontend plays the original files.
        </div>
      </template>
      <template v-else>
        <StatusDot color="green" />
        <div>
          <strong>On</strong> (<code>{{ envVar }}=true</code>). New and replaced videos are encoded in the queue. FFmpeg runs in the queue worker: if it's missing there, videos fail with "FFmpeg/FFprobe not found".
        </div>
      </template>
    </div>

    <template v-if="enabled">
      <div class=":uno: mb-4 gap-2 grid grid-cols-[repeat(auto-fit,minmax(130px,1fr))]">
        <div v-for="status in statuses" :key="status.value" class=":uno: px-3.5 py-2.5 border border-hairline rounded-md">
          <strong class=":uno: text-5 block tabular-nums">{{ status.count }}</strong>
          <StatusDot :color="status.color" /> {{ status.label }}
        </div>
      </div>

      <div class="field">
        <Btn variant="submit" :loading="queue.saving.value" @click="queueVideos">
          Queue videos
        </Btn>
        <p class="light">
          Queues only the videos switched on that need it: never encoded, replaced, failed, or encoded with other settings. Each video is encoded once per quality, so many or long videos keep the worker busy for a long time and use a lot of disk.
        </p>
      </div>

      <template v-if="failures.length">
        <hr>
        <h3>Failed videos</h3>
        <p class="light">
          Fix the cause, then queue the videos again. Files that aren't MP4/MOV, MKV/WebM or MPEG-TS are refused on purpose: convert them to MP4.
        </p>
        <table class="data fullwidth">
          <thead>
            <tr>
              <th>Video</th>
              <th>Error</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="failure in failures" :key="failure.assetId">
              <td>
                <a v-if="failure.cpEditUrl" :href="failure.cpEditUrl">{{ failure.title }}</a>
                <template v-else>
                  #{{ failure.assetId }}
                </template>
              </td>
              <td>
                <div class=":uno: text-2.75 text-error font-mono whitespace-pre-wrap break-words">
                  {{ failure.error ?? 'No error recorded.' }}
                </div>
              </td>
              <td class="nowrap">
                {{ failure.dateUpdated }}
              </td>
            </tr>
          </tbody>
        </table>
      </template>
    </template>

    <hr>

    <h3>In the frontend</h3>
    <p class="light">
      Query <code>hlsPlaylistUrl</code> and <code>videoPoster</code> next to <code>url</code>: <code>&lt;CraftMedia&gt;</code> from <code>@studio-fes/layer-craft</code> plays HLS when it's there and the original file otherwise. The Help tab has the fields to create, the fragment and the options.
    </p>

    <h3>Checklist</h3>
    <ul class="bullets">
      <li>The videos to stream have <strong>Convert to HLS</strong> switched on, in the Streaming block of their edit form.</li>
      <li>The video volume's Base URL is absolute (e.g. <code>$ASSETS_BASE_URL</code>), or the playlist URL points to the frontend's domain.</li>
      <li>The <code>.m3u8</code> and <code>.ts</code> files are served with CORS headers. The craft-php image already does it; on a bucket or CDN, allow <code>GET</code> from the frontend.</li>
      <li>The frontend's GraphQL schema can query assets in the video volume.</li>
      <li>The PHP upload limit is the maximum video size (300M on craft-php: raise <code>PHP_POST_MAX_SIZE</code> and <code>PHP_UPLOAD_MAX_FILE_SIZE</code>).</li>
      <li>On craft-php, <code>web</code> and <code>worker</code> mount the same uploads volume: the worker writes the files, nginx serves them.</li>
    </ul>
  </div>

  <form v-show="tab === 'settings'" role="tabpanel" @submit.prevent="save(form)">
    <Field
      label="Qualities"
      instructions="Only qualities up to the source height are encoded, never upscaled. Fewer qualities take less disk and less encoding time; dropping 1080p also caps the traffic of desktop viewers. A source smaller than every selected quality gets one stream at its own size."
    >
      <CheckboxGroup v-model="form.qualities" :options="qualityOptions as { label: string, value: number }[]" />
    </Field>

    <Field id="tk-sv-above-hd" label="Above 1080p, up to the source" :instructions="aboveHdInstructions">
      <Lightswitch id="tk-sv-above-hd" v-model="form.aboveHd" />
    </Field>

    <p class="light">
      H.264 video, stereo AAC 48 kHz audio, {{ segmentDuration }}-second segments with keyframes aligned across qualities. Each quality's peak bitrate is 7% above its target. All default qualities of a 1080p video take about 80 MB per minute, on top of the original.
    </p>
    <p class="light">
      Changing the qualities doesn't touch the videos already encoded: save, then <strong>Queue videos</strong> on the Overview tab to re-encode them with the new settings.
    </p>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save settings
      </Btn>
    </div>
  </form>

  <StreamingVideoCosts v-if="tab === 'costs'" />
  <StreamingVideoHelp v-if="tab === 'help'" :env-var />
</template>
