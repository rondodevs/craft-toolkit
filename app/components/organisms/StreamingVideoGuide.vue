<script setup lang="ts">
interface StreamingVideoGuideProps {
  envVar: string
}

defineProps<StreamingVideoGuideProps>()

const open = defineModel<boolean>({ default: false })

const tabs = [
  { id: 'frontend', label: 'In the frontend' },
  { id: 'help', label: 'Help' },
  { id: 'costs', label: 'Costs' },
]
const tab = ref('frontend')
</script>

<template>
  <Modal v-model="open" title="Streaming Video guide">
    <div class="field">
      <TabGroup v-model="tab" :tabs label="Guide sections" small />
    </div>

    <Prose v-if="tab === 'frontend'">
      <p>
        Query <code>hlsPlaylistUrl</code> and <code>videoPoster</code> next to <code>url</code>: <code>&lt;CraftMedia&gt;</code> from <code>@studio-fes/layer-craft</code> plays HLS when it's there and the original file otherwise. The Help section has the fields to create, the fragment and the options.
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
    </Prose>
    <StreamingVideoHelp v-if="tab === 'help'" :env-var />
    <StreamingVideoCosts v-if="tab === 'costs'" />
  </Modal>
</template>
