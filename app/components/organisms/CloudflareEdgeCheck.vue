<script setup lang="ts">
import type { CloudflareRule } from './CloudflareRules.vue'
import type { StatusColor } from '~/components/atoms/StatusDot.vue'

interface Check {
  label: string
  status: 'ok' | 'warn' | 'fail' | 'skip'
  message: string
  link?: { url: string, label: string }
}

interface CloudflareEdgeCheckProps {
  cacheRuleName: string
  cacheRuleExpression: string | null
}

const props = defineProps<CloudflareEdgeCheckProps>()

// Craft's status dots
const colors: Record<Check['status'], StatusColor> = { ok: 'green', warn: 'orange', fail: 'red', skip: 'disabled' }

const checks = ref<Check[]>([])
const ruleAction = ref<'create' | 'fix' | null>(null)

const check = useAsyncAction<{ checks: Check[], ruleAction: 'create' | 'fix' | null }>('toolkit/cloudflare/check-edge-cache', {
  idle: 'not checked',
  pending: 'checking…',
})

async function runCheck() {
  const result = await check.run()

  checks.value = result?.ok ? result.data.checks : []
  ruleAction.value = result?.ok ? result.data.ruleAction : null
}

// With a rule already on the zone the button rewrites it, never adds a second one
const createRule = useAsyncAction('toolkit/cloudflare/create-cache-rule', {
  pending: 'creating the rule…',
  confirm: () => ruleAction.value === 'fix'
    ? `Overwrite the existing Cache Rule with the "${props.cacheRuleName}" settings?`
    : `Create the Cache Rule "${props.cacheRuleName}" on the Cloudflare zone?`,
})

// Checks again once the rule is written, so the list shows the new state
async function writeRule() {
  const result = await createRule.run()

  if (result?.ok)
    await runCheck()
  else if (result)
    check.status.value = createRule.status.value
}

const rulesOpen = ref(false)
const rules = useAsyncAction<{ host: string | null, rules: CloudflareRule[], tokenUrl?: string }>('toolkit/cloudflare/list-cache-rules')

function showRules() {
  rulesOpen.value = true
  rules.run()
}
</script>

<template>
  <div class="field">
    <p><strong>Edge cache check</strong></p>
    <p class="light">
      Requests the Frontend URL like a visitor: checks that the frontend sends <code>CDN-Cache-Control</code> (<code>NUXT_CDN_CACHE_ENABLED</code>), that the site is proxied, that Cloudflare serves the page from its cache and never caches a Craft preview. With a token that also has the "Zone → Cache Rules → Edit" permission, it checks the Cache Rule too, and can create or fix it.
    </p>

    <StatusLine :message="check.status.value.message" :error="check.status.value.error" />

    <ul v-if="checks.length" class=":uno: mb-3">
      <li v-for="item in checks" :key="item.label" class=":uno: mb-1.5">
        <StatusDot :color="colors[item.status] ?? 'disabled'" class=":uno: mr-1.5" />
        <strong>{{ item.label }}</strong>: {{ item.message }}
        <a v-if="item.link" :href="item.link.url" target="_blank" rel="noopener noreferrer">{{ item.link.label }} ↗</a>
      </li>
    </ul>

    <div class="buttons">
      <Btn :loading="check.pending.value" @click="runCheck">
        Check edge cache
      </Btn>
      <Btn :loading="rules.pending.value" @click="showRules">
        View Cache Rules
      </Btn>
      <Btn v-if="ruleAction" variant="submit" :loading="createRule.pending.value" @click="writeRule">
        {{ ruleAction === 'fix' ? 'Fix Cache Rule' : 'Create Cache Rule' }}
      </Btn>
    </div>

    <template v-if="cacheRuleExpression">
      <p class="light">
        To create it by hand: Caching → Cache Rules → Create rule, name <code>{{ cacheRuleName }}</code>, <em>Eligible for cache</em>, Edge TTL <em>Use cache-control header if present, bypass cache if not</em>, Browser TTL <em>Respect origin TTL</em>, and this expression:
      </p>
      <CodeBlock :code="cacheRuleExpression" wrap />
    </template>
  </div>

  <Modal v-model="rulesOpen" title="Cloudflare Cache Rules">
    <p v-if="rules.pending.value" class="light">
      Loading…
    </p>
    <template v-else-if="rules.result.value && !rules.result.value.ok">
      <p class="error">
        {{ rules.result.value.message || 'Could not read the Cache Rules.' }}
      </p>
      <p v-if="rules.result.value.data.tokenUrl">
        <a :href="rules.result.value.data.tokenUrl" target="_blank" rel="noopener noreferrer">Create token on Cloudflare ↗</a>
      </p>
    </template>
    <CloudflareRules v-else-if="rules.result.value" :host="rules.result.value.data.host" :rules="rules.result.value.data.rules" />
  </Modal>
</template>
