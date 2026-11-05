<script setup lang="ts">
import type { StatusColor } from '~/components/atoms/StatusDot.vue'

interface Check {
  label: string
  status: 'ok' | 'warn' | 'fail' | 'skip'
  message: string
  link?: { url: string, label: string } | null
}

interface Group {
  label: string
  checks: Check[]
}

interface HealthScreenProps {
  tokenEnv: string
  frontendHealthPath: string
}

defineProps<HealthScreenProps>()

// Craft's status dots
const colors: Record<Check['status'], StatusColor> = { ok: 'green', warn: 'orange', fail: 'red', skip: 'disabled' }

const groups = ref<Group[]>([])

const check = useAsyncAction<{ groups: Group[] }>('toolkit/health/check', {
  idle: 'not checked',
  pending: 'checking…',
})

async function runCheck() {
  const result = await check.run()

  groups.value = result?.ok ? result.data.groups : []
}

onMounted(runCheck)
</script>

<template>
  <p class="light">
    Checks that Craft, the frontend and Cloudflare are set up to work together, and names the variable to set for each problem. Craft signs its requests to the frontend (purges, stats, this check) with a key derived from its security key, and the frontend checks them with Craft's public key: nothing to share between the two services. <code>{{ tokenEnv }}</code> is only needed for a frontend that can't check signatures. The frontend's settings are read from <code>{{ frontendHealthPath }}</code>.
  </p>

  <StatusLine :message="check.status.value.message" :error="check.status.value.error" />

  <div v-for="group in groups" :key="group.label" class="field">
    <p><strong>{{ group.label }}</strong></p>
    <ul>
      <li v-for="item in group.checks" :key="item.label" class=":uno: mb-1.5">
        <StatusDot :color="colors[item.status] ?? 'disabled'" class=":uno: mr-1.5" />
        <strong>{{ item.label }}</strong>: {{ item.message }}
        <a v-if="item.link" :href="item.link.url">{{ item.link.label }} →</a>
      </li>
    </ul>
  </div>

  <div class="buttons">
    <Btn :loading="check.pending.value" @click="runCheck">
      Check again
    </Btn>
  </div>
</template>
