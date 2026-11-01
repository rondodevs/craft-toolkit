<script setup lang="ts">
export interface CloudflareRule {
  position: number
  description: string
  enabled: boolean
  expression: string
  settings: Record<string, string>
  isOurs: boolean
  problems: string[]
  overridesOurs: boolean
}

interface CloudflareRulesProps {
  host: string | null
  rules: CloudflareRule[]
}

defineProps<CloudflareRulesProps>()
</script>

<template>
  <p class="light">
    In the order Cloudflare applies them: every rule that matches a request applies, and for the same setting the last one wins.
    <template v-if="host">
      Frontend host: <code>{{ host }}</code>.
    </template>
  </p>

  <ol v-if="rules.length" class=":uno: m-0 p-0 list-none flex flex-col gap-3">
    <li
      v-for="rule in rules"
      :key="rule.position"
      class=":uno: p-3 border rounded-md"
      :class="rule.isOurs ? ':uno: border-primary' : ':uno: border-hairline'"
    >
      <div class=":uno: mb-2 flex flex-wrap gap-1.5 items-center">
        <StatusDot :color="rule.enabled ? 'green' : 'disabled'" :label="rule.enabled ? 'Enabled' : 'Disabled'" />
        <strong>{{ rule.position }}. {{ rule.description || '(no name)' }}</strong>
        <Badge v-if="rule.isOurs">
          used by the check
        </Badge>
        <Badge v-if="rule.overridesOurs" tone="warning">
          applies after it, can override it
        </Badge>
      </div>
      <dl class=":uno: m-0 mb-2 gap-x-3 gap-y-1 grid grid-cols-[max-content_1fr]">
        <template v-for="(value, label) in rule.settings" :key="label">
          <dt class=":uno: text-gray-500">
            {{ label }}
          </dt>
          <dd class=":uno: m-0">
            {{ value }}
          </dd>
        </template>
      </dl>
      <CodeBlock :code="rule.expression" wrap />
      <ul v-if="rule.problems.length" class=":uno: mt-2">
        <li v-for="problem in rule.problems" :key="problem" class="error">
          {{ problem }}
        </li>
      </ul>
    </li>
  </ol>
  <p v-else>
    The zone has no Cache Rules.
  </p>
</template>
