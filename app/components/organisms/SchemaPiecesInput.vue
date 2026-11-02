<script setup lang="ts">
export interface BasicField {
  key: string
  label: string
  inputType: string
}

export interface SchemaPiece {
  type: string
  mode: 'ui' | 'json'
  props: Record<string, string>
  propertiesJson: string
  hasError: boolean
}

export interface SchemaPiecesInputProps {
  // The pieces' input name, namespaced by Craft (`fields[seo][schema]`)
  name: string
  rows: SchemaPiece[]
  types: string[]
  typeFields: Record<string, BasicField[]>
  defaultFields: BasicField[]
}

const props = defineProps<SchemaPiecesInputProps>()

let uid = 0
const rows = ref(props.rows.map(row => ({ ...row, props: { ...row.props }, uid: ++uid })))
const listId = useId()

function fieldsFor(type: string) {
  return props.typeFields[type] ?? props.defaultFields
}

// The JSON mode starts from what the simple fields hold
function setMode(row: SchemaPiece, mode: SchemaPiece['mode']) {
  const filled = Object.fromEntries(Object.entries(row.props).filter(([, value]) => value.trim() !== ''))

  if (mode === 'json' && Object.keys(filled).length)
    row.propertiesJson = JSON.stringify(filled, null, 2)

  row.mode = mode
}

function add() {
  rows.value.push({ type: '', mode: 'ui', props: {}, propertiesJson: '', hasError: false, uid: ++uid })
}

const modes = [
  { id: 'ui', label: 'Simple' },
  { id: 'json', label: 'JSON' },
]
</script>

<template>
  <div>
    <!-- Curated types to pick from, or any schema.org type typed by hand -->
    <datalist :id="listId">
      <option v-for="type in types" :key="type" :value="type" />
    </datalist>

    <div class=":uno: mb-2 flex flex-col gap-3">
      <div
        v-for="(row, index) in rows"
        :key="row.uid"
        class=":uno: p-3 border rounded-md bg-white"
        :class="row.hasError ? ':uno: border-error' : ':uno: border-hairline'"
      >
        <div class=":uno: mb-3 flex flex-wrap gap-2 items-center">
          <input
            v-model="row.type"
            :name="inputName(name, 'rows', index, 'type')"
            type="text"
            class=":uno: text flex-1 min-w-50"
            :list="listId"
            placeholder="Select or type a schema.org type"
            aria-label="schema.org type"
          >
          <TabGroup :model-value="row.mode" :tabs="modes" small label="Mode" @update:model-value="setMode(row, $event as SchemaPiece['mode'])" />
          <HiddenInput :name="inputName(name, 'rows', index, 'mode')" :value="row.mode" />
          <Btn @click="rows.splice(index, 1)">
            Remove
          </Btn>
        </div>

        <div v-show="row.mode === 'ui'" class=":uno: gap-x-3 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))]">
          <Field
            v-for="basic in fieldsFor(row.type)"
            :key="basic.key"
            :label="basic.label"
            :class="{ ':uno: col-span-full': basic.inputType === 'textarea' }"
          >
            <TextArea
              v-if="basic.inputType === 'textarea'"
              v-model="row.props[basic.key]"
              :name="inputName(name, 'rows', index, 'props', basic.key)"
              :rows="2"
            />
            <TextInput
              v-else
              v-model="row.props[basic.key]"
              :name="inputName(name, 'rows', index, 'props', basic.key)"
              :type="basic.inputType"
            />
          </Field>
        </div>

        <div v-show="row.mode === 'json'" class="field">
          <TextArea
            v-model="row.propertiesJson"
            :name="inputName(name, 'rows', index, 'propertiesJson')"
            :rows="6"
            code
            placeholder="{ &quot;name&quot;: &quot;…&quot;, &quot;url&quot;: &quot;…&quot; }"
          />
          <p class="light">
            Additional or overriding schema.org properties for this type, as a JSON object. The type above is added automatically as "@type".
          </p>
          <p v-if="row.hasError" class="error">
            Invalid JSON: this row is not saved until it is fixed.
          </p>
        </div>
      </div>
    </div>

    <!-- Posts an empty list once every row is removed -->
    <input v-if="!rows.length" type="hidden" :name="inputName(name, 'rows')" value="">

    <div class="buttons">
      <Btn add @click="add">
        Add schema
      </Btn>
    </div>
  </div>
</template>
