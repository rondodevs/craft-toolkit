<script setup lang="ts">
interface Volume {
  id: number
  handle: string
  name: string
  selected: boolean
  hasAverageColorField: boolean
}

interface AverageColorScreenProps {
  enabled: boolean
  volumes: Volume[]
  allowAdminChanges: boolean
}

const props = defineProps<AverageColorScreenProps>()

const emit = defineEmits<{
  saved: [props: AverageColorScreenProps]
}>()

const form = useForm(() => ({
  enabled: props.enabled,
  volumeIds: props.volumes.filter(volume => volume.selected).map(volume => String(volume.id)),
}))

const options = computed(() => props.volumes.map(volume => ({ ...volume, id: String(volume.id) })))
const selected = computed(() => options.value.filter(volume => form.value.volumeIds.includes(volume.id)))

const { save, saving } = useScreenSave<AverageColorScreenProps>('toolkit/average-color/save', p => emit('saved', p))

// The field is added right away: the selection stays as it is, unsaved
const creating = ref<string | null>(null)
const fieldStatus = ref<{ message: string, error: boolean } | null>(null)
const withField = ref<string[]>([])

async function createField(id: string) {
  creating.value = id
  const result = await action('toolkit/average-color/create-field', { volumeId: id })
  creating.value = null
  fieldStatus.value = { message: result.message, error: !result.ok }

  if (result.ok)
    withField.value.push(id)
}

function hasField(volume: { id: string, hasAverageColorField: boolean }) {
  return volume.hasAverageColorField || withField.value.includes(volume.id)
}
</script>

<template>
  <p class="light">
    Automatically calculates and stores an average color for image assets on save, using the "averageColor" field on the asset's volume field layout. Videos get their poster's color once Streaming Video has a poster for them (it needs FFmpeg in the queue worker).
  </p>

  <form @submit.prevent="save(form)">
    <Field
      id="tk-average-color-enabled"
      label="Enable automatic average color calculation"
      instructions="When disabled, no average color is calculated or written on asset save, regardless of the volumes selected below."
    >
      <Lightswitch id="tk-average-color-enabled" v-model="form.enabled" />
    </Field>

    <Field
      label="Volumes"
      instructions="Select the volumes on which the average color should be calculated automatically. The &quot;media&quot; volume is selected by default if it exists, but you can change the selection. Only volumes whose field layout already includes an &quot;averageColor&quot; field can actually store the calculated value."
    >
      <p v-if="!volumes.length" class="light">
        No volumes found.
      </p>
      <template v-else>
        <div v-if="selected.length" class=":uno: mb-2 flex flex-wrap gap-1.5">
          <Chip
            v-for="volume in selected"
            :key="volume.id"
            :label="volume.name"
            removable
            @remove="form.volumeIds = form.volumeIds.filter(id => id !== volume.id)"
          >
            <template v-if="!hasField(volume)">
              <Badge tone="missing">
                Missing field
              </Badge>
              <Btn
                v-if="allowAdminChanges"
                small
                :loading="creating === volume.id"
                title="Add the &quot;averageColor&quot; field to this volume's field layout"
                @click="createField(volume.id)"
              >
                Create field
              </Btn>
            </template>
          </Chip>
        </div>

        <Combobox
          v-model="form.volumeIds"
          :options
          :text="volume => `${volume.name} (${volume.handle})`"
          placeholder="Search volumes..."
          empty-text="No matching volumes."
        >
          <template #option="{ option }">
            <span>{{ option.name }} ({{ option.handle }})</span>
            <Badge :tone="hasField(option) ? 'ok' : 'missing'">
              {{ hasField(option) ? 'Found' : 'Missing' }}
            </Badge>
          </template>
        </Combobox>

        <p v-if="fieldStatus" class=":uno: mt-2">
          <strong :class="{ error: fieldStatus.error }">{{ fieldStatus.message }}</strong>
        </p>
      </template>
    </Field>

    <div class="buttons">
      <Btn type="submit" variant="submit" :loading="saving">
        Save settings
      </Btn>
    </div>
  </form>
</template>
