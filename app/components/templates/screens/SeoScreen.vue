<script setup lang="ts">
interface SeoScreenProps {
  etherInstalled: boolean
  etherInComposer: boolean
  etherFields: { name: string, handle: string, missing: boolean }[]
  etherRedirectCount: number
  toolkitFields: { name: string, handle: string, cpEditUrl: string }[]
  usages: {
    entryType: { name: string, handle: string, cpEditUrl: string }
    fieldHandle: string
    rules: string[]
  }[]
  orgSchemaFields: { name: string, handle: string, cpEditUrl: string }[]
  canMigrate: boolean
  compatEnabled: boolean
  deprecationsUrl: string
  fieldsUrl: string
}

const props = defineProps<SeoScreenProps>()

const emit = defineEmits<{
  saved: [props: SeoScreenProps]
}>()

const migrate = useAsyncAction<{ props: SeoScreenProps }>('toolkit/seo/migrate-ether', {
  confirm: 'Convert the Ether SEO fields and uninstall Ether SEO?',
})

async function runMigration() {
  const result = await migrate.run()

  if (result) {
    notify(result)
    if (result.ok)
      emit('saved', result.data.props)
  }
}

const fragment = `fragment Seo on ToolkitSeo {
  title
  description
  ogTitle
  ogDescription
  image { ...Media }
  robotsContent
  canonical
  ai { summary exclude }
  schema { json }
}

seo { ...Seo }`

const etherFieldsLabel = computed(() => props.etherFields.length === 1 ? 'the Ether SEO field' : `the ${props.etherFields.length} Ether SEO fields`)
</script>

<template>
  <p class="light">
    The <strong>SEO</strong> field type gives every entry a title, description, social image and texts, robots directives, a canonical URL, an AI summary and its schema.org structured data. Whatever the editor leaves empty is filled from the field's settings: an ordered list of fields to take the title, description and image from, then a Twig template as the last fallback. It replaces Ether SEO's field.
  </p>

  <template v-if="etherInstalled || etherFields.length">
    <hr>
    <h3>Move off Ether SEO</h3>
    <p>
      "Migrate" switches {{ etherFieldsLabel }} below to the SEO type in place and uninstalls Ether SEO, in one step. Same handle, same content: the values Ether saved are read as they are, and titles and descriptions come out the same, Ether's title template included.
    </p>

    <table v-if="etherFields.length" class=":uno: data fullwidth mb-4">
      <thead>
        <tr>
          <th>Field</th>
          <th>Handle</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="field in etherFields" :key="field.handle">
          <td>{{ field.name }}</td>
          <td><code>{{ field.handle }}</code></td>
          <td>{{ field.missing ? 'Ether SEO no longer installed' : 'Ether SEO' }}</td>
        </tr>
      </tbody>
    </table>

    <p>
      <strong>The GraphQL queries already in the frontends keep working.</strong> <code>fragment SEO on Ether_SeoData</code> still validates and returns the same values, but those fields are deprecated and will be removed: update the frontends to the <code>ToolkitSeo</code> fields below (<code>@studio-fes/layer-craft</code> reads both). Each query still using them shows up in <a :href="deprecationsUrl">Deprecation Warnings</a>.
    </p>
    <ul class="bullets">
      <li>Facebook and Twitter now share one social title, description and image: where they differed, Facebook's are kept.</li>
      <li>Keywords and the SEO score are not carried over: <code>keywords</code> returns an empty list.</li>
      <li v-if="etherRedirectCount" class="warning">
        Ether SEO has <strong>{{ etherRedirectCount }} redirect(s)</strong>. They are not migrated and are deleted with its tables: move them to the frontend first.
      </li>
      <li>Ether's sitemap and robots.txt go too: Nuxt SEO serves them.</li>
    </ul>

    <template v-if="canMigrate">
      <div class=":uno: mt-4">
        <Btn variant="submit" :loading="migrate.pending.value" @click="runMigration">
          {{ etherFields.length ? 'Migrate and uninstall Ether SEO' : 'Uninstall Ether SEO' }}
        </Btn>
      </div>
      <p class="light">
        Or from the terminal: <code>php craft toolkit/seo/migrate-ether</code>. Then run <code>composer remove ether/seo</code>, commit the project config and deploy: <code>craft up</code> applies the same change on the other environments.
      </p>
    </template>
    <p v-else class="warning">
      Admin changes are off in this environment. Migrate in development, then deploy the project config.
    </p>
  </template>
  <template v-else-if="etherInComposer">
    <hr>
    <p class="warning">
      Ether SEO is uninstalled but <code>ether/seo</code> is still in Composer: run <code>composer remove ether/seo</code>.
    </p>
  </template>

  <template v-if="orgSchemaFields.length">
    <hr>
    <h3>Org Schema fields left</h3>
    <p class="warning">
      The Org Schema field is deprecated: its pieces are now the SEO field's "Structured data". Updating the toolkit moved them into the SEO field of each entry type, except where the layout has no SEO field. Add the SEO field there, move the pieces by hand, then delete these fields.
    </p>
    <ul class="bullets">
      <li v-for="field in orgSchemaFields" :key="field.handle">
        <a :href="field.cpEditUrl">{{ field.name }}</a> <code>{{ field.handle }}</code>
      </li>
    </ul>
  </template>

  <hr>
  <h3>Fields</h3>
  <template v-if="toolkitFields.length">
    <p class="light">
      One SEO field for the whole site is enough: its settings are the default rules, and each entry type can replace them in its field layout.
    </p>
    <ul class="bullets">
      <li v-for="field in toolkitFields" :key="field.handle">
        <a :href="field.cpEditUrl">{{ field.name }}</a> <code>{{ field.handle }}</code>
      </li>
    </ul>
  </template>
  <p v-else class="light">
    No SEO field yet. <a :href="fieldsUrl">Create one</a> with the "SEO" type and add it to the entry types.
  </p>

  <hr>
  <h3>Rules by entry type</h3>
  <p class="light">
    What fills an entry's title, description and image when the editor leaves them empty. Each entry type can have its own rules: open its field layout and click the SEO field (the settings slideout), they read that layout's fields. Without, the SEO field's settings apply. Prefix, suffix, default image and robots are always the field's.
  </p>
  <table v-if="usages.length" class="data fullwidth">
    <thead>
      <tr>
        <th>Entry type</th>
        <th>Field</th>
        <th>Rules</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="usage in usages" :key="`${usage.entryType.handle}-${usage.fieldHandle}`">
        <td>
          <a :href="usage.entryType.cpEditUrl">{{ usage.entryType.name }}</a> <code>{{ usage.entryType.handle }}</code>
        </td>
        <td><code>{{ usage.fieldHandle }}</code></td>
        <td>{{ usage.rules.length ? `Own: ${usage.rules.join(', ')}` : 'Field defaults' }}</td>
      </tr>
    </tbody>
  </table>
  <p v-else class="light">
    No entry type uses the SEO field yet.
  </p>

  <hr>
  <h3>GraphQL</h3>
  <p class="light">
    Every value comes resolved, automatic ones included, and as plain text. Each <code>schema</code> piece's <code>json</code> is ready for Nuxt SEO's <code>useSchemaOrg()</code>: <code>useCraftSEO()</code> from <code>@studio-fes/layer-craft</code> registers them.
  </p>
  <CodeBlock :code="fragment" />
  <p v-if="!compatEnabled" class="light">
    While Ether SEO is installed, <code>ToolkitSeo</code> doesn't implement <code>Ether_SeoData</code>: the type names would clash.
  </p>
</template>
