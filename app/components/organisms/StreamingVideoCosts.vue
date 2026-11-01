<template>
  <Prose>
    <p>What streaming video costs, when it's worth buying object storage (S3), and when a video service like Mux is the better choice. Written for portfolios and small e-commerce sites on Hetzner servers managed with Coolify.</p>

    <p><strong>In short</strong>: keep the videos on the server's local volume, which costs nothing extra. Buy object storage only when the server runs short of disk or bandwidth, or when web and worker run on different servers. Use Mux for the clients whose product is video.</p>

    <h3>Prices</h3>
    <p class="light">
      As of October 2026, from public pricing pages that don't fully agree with each other. Check them before quoting a client.
    </p>
    <DocTable
      :head="['Service', 'Included', 'Beyond that']"
      :rows="[
        ['Hetzner Cloud server (EU)', '20 TB of traffic per month, per server', '~€1/TB'],
        ['Hetzner Object Storage', '~€5-6.5/month for 1 TB stored + 1 TB of traffic', '~€1/TB of traffic. Traffic inside the same region and API calls are free'],
        ['Mux', 'Free plan: 100,000 minutes watched per month, <strong>max 10 videos</strong>', 'Launch plan $20/month with usage credit; at 1080p ~$0.0012 per minute watched and ~$0.00375 per minute stored per month'],
      ]"
    />

    <h3>Assumptions</h3>
    <ul class="bullets">
      <li><strong>30 MB per minute watched</strong> (~4 Mbit/s): a mix of 1080p on desktop and 480p/720p on mobile. This holds <strong>only if the frontend sets <code>capLevelToPlayerSize</code></strong>; without it most viewers get 1080p, count ~40 MB per minute.</li>
      <li>So <strong>1 TB ≈ 33,000 minutes watched</strong> (550 hours).</li>
      <li><strong>155 MB stored per minute of video</strong>: the default HLS qualities up to 1080p (~80 MB) plus the original upload (~75 MB). So <strong>1 hour of video ≈ 9 GB</strong>. Adding 1440p and 4K takes ~190 MB more per minute, plus a larger original: count <strong>~25 GB per hour</strong>.</li>
      <li>Encoding costs nothing extra in every option: it runs on your worker, and Mux's basic encoding is free.</li>
    </ul>

    <h3>Monthly cost by project size</h3>
    <DocTable
      :head="['Project', 'Video library', 'Minutes watched / month', 'Traffic', 'Toolkit, local volume', 'Mux']"
      :rows="[
        ['Small portfolio', '10 min (~1.6 GB)', '5,000', '0.15 TB', '€0 extra', '$0 if ≤10 videos, else $20'],
        ['Portfolio with showreel', '30 min (~4.7 GB)', '50,000', '1.5 TB', '€0 extra', '$0 if ≤10 videos, else $20'],
        ['Small e-commerce', '120 min (~19 GB)', '200,000', '6 TB', '€0 extra, but 30% of the server\'s traffic', '~$120'],
        ['Busy e-commerce / campaign', '500 min (~78 GB)', '1,000,000', '30 TB', '~€10 overage, and too many concurrent viewers for one server: needs object storage (~€35 total)', '~$1,080'],
      ]"
    />
    <p>With a local volume the videos cost nothing beyond the server you already pay, as long as it has disk and traffic left. <strong>All projects on the server share the same disk and the same 20 TB.</strong> The difference is all in delivery: 1,000 minutes watched cost about <strong>€0.03</strong> of Hetzner traffic against about <strong>$1.20</strong> on Mux, once its free minutes are used up.</p>

    <h3>Concurrent viewers</h3>
    <p>Playback is only static files served by nginx without PHP: 100 people watching the same video cost almost nothing in CPU. The limit is <strong>bandwidth</strong>, each viewer downloads continuously at the quality they're watching.</p>
    <DocTable
      :head="['Quality', 'Peak per viewer', '100 viewers']"
      :rows="[
        ['2160p (4K)', '~17.3 Mbit/s', '~1.7 Gbit/s'],
        ['1440p', '~9.8 Mbit/s', '~980 Mbit/s'],
        ['1080p', '~5.5 Mbit/s', '~550 Mbit/s'],
        ['720p', '~3.3 Mbit/s', '~330 Mbit/s'],
        ['480p', '~1.7 Mbit/s', '~170 Mbit/s'],
      ]"
    />
    <p>On a 1 Gbit/s link that's roughly <strong>100-200 concurrent viewers</strong> up to 1080p, and <strong>about 50 at 4K</strong>: with <code>capLevelToPlayerSize</code> only viewers on large, high-density screens get it. Past that nothing crashes, players switch to lower qualities, but every site on the server slows down.</p>

    <h3>When to buy object storage</h3>
    <p>Most portfolios and small e-commerce sites never need it. It starts to make sense when the <strong>server</strong> (all its projects added up, not one site) runs into one of these limits:</p>
    <DocTable
      :head="['Signal (whole server)', 'Local volume is fine', 'Start thinking about it', 'Buy object storage']"
      :rows="[
        ['Video stored (HLS + originals)', 'Under ~20 GB (~2 hours of video)', '20-50 GB, or video over ~30% of the disk', 'Over ~50 GB (~5-6 hours), or disk over ~70% full'],
        ['Peak concurrent viewers', 'Under ~50', '~50-100', 'Over ~100: the 1 Gbit/s link is the limit'],
        ['Video traffic per month', 'Under ~5 TB', '~5-15 TB', 'Over ~15 TB, close to the 20 TB included'],
        ['Backups and server moves', 'Backed up and restored in minutes', 'Backups get slow or heavy because of the videos', 'Restoring or moving takes hours because of the videos'],
        ['Architecture', 'Web and worker on the same server', '', 'Web and worker on different servers, or more than one web container'],
      ]"
    />
    <ul class="bullets">
      <li><strong>The cost is per account, not per client</strong>: ~€5-6.5/month covers 1 TB stored and 1 TB of traffic for every project in it.</li>
      <li><strong>If only the disk is short</strong> and traffic is low, a bigger server or a Hetzner Volume is simpler: no CORS, no new filesystem.</li>
      <li><strong>Put the bucket in the same location as the server</strong>: the worker downloads every original to encode it, and traffic within the region isn't billed.</li>
      <li><strong>Only the video volume needs to move</strong>: images can stay on the local volume.</li>
    </ul>

    <h3>When to use a video service instead</h3>
    <p>On money alone the Toolkit always wins. A service like Mux is worth it for other reasons:</p>
    <DocTable
      :head="['Signal', 'Why the Toolkit falls short']"
      :rows="[
        ['Hours of new video every week, or videos online minutes after upload', 'Encoding runs on the same worker as every other queue job, at roughly real time or slower for each quality'],
        ['Automatic captions, viewer analytics, private/signed videos, DRM, live streaming', 'Not supported'],
        ['Maintaining it for a client costs more than their Mux bill', 'Support, FFmpeg and Craft updates are on you'],
        ['The client wants a provider with an SLA', 'No SLA: you are the provider'],
      ]"
    />
    <p>Mux is a US service that collects viewer analytics by default: one more processor to declare in the client's privacy policy.</p>
    <p><strong>Rule of thumb</strong>: local volume by default, object storage when the server runs short of disk or bandwidth, Mux for the clients whose product is video (courses, video-heavy campaigns, streaming).</p>

    <h3>Moving the videos to Hetzner Object Storage</h3>
    <ol>
      <li>Create a public-read bucket and an access key in Hetzner Cloud, in the same location as the server.</li>
      <li>Create a filesystem with an S3-compatible plugin that supports a custom endpoint (e.g. <code>https://fsn1.your-objectstorage.com</code>), with an absolute Base URL.</li>
      <li>Allow <code>GET</code> from the frontend's origin in the bucket's CORS settings.</li>
      <li>Point the video volume to the new filesystem, move the existing files, then <strong>Queue videos</strong> to encode into the bucket.</li>
    </ol>
  </Prose>
</template>
