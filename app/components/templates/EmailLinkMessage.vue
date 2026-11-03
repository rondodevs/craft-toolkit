<script setup lang="ts">
import type { SuffixParam } from '../../utils/mailto'

// Subject and body of an email link, kept in the Link field's URL suffix (FieldHandlers): the inputs have no name, the suffix is what's saved
interface EmailLinkMessageProps {
  inputId: string
  types: string[]
}

const props = defineProps<EmailLinkMessageProps>()

const MESSAGE_KEYS = ['subject', 'body']

const root = useTemplateRef<HTMLElement>('root')
const subject = ref('')
const body = ref('')
const isEmail = ref(false)

let suffixInput: HTMLInputElement | null = null
let typeSelect: HTMLSelectElement | null = null
let reading = false

const isMessageKey = (key: string) => MESSAGE_KEYS.includes(key.toLowerCase())

function read() {
  const { params } = parseSuffix(suffixInput?.value ?? '')
  const value = (key: string) => params.find(([name]) => name.toLowerCase() === key)?.[1] ?? ''

  reading = true
  subject.value = value('subject')
  body.value = value('body')
  nextTick(() => reading = false)
}

// Other params (cc, bcc…) and a fragment stay as the editor typed them; off the email type, subject and body come out
function write() {
  if (!suffixInput)
    return

  const { params, hash } = parseSuffix(suffixInput.value)
  const kept = params.filter(([key]) => !isMessageKey(key))
  const message: SuffixParam[] = isEmail.value
    ? [['subject', subject.value], ['body', body.value]]
    : []
  const suffix = formatSuffix([...message, ...kept], hash)

  if (suffix === suffixInput.value)
    return

  suffixInput.value = suffix
  // Through jQuery too, so the element editor sees the change and saves a draft
  const $ = (window as any).jQuery
  $ ? $(suffixInput).trigger('change') : suffixInput.dispatchEvent(new Event('change', { bubbles: true }))
}

function updateType() {
  isEmail.value = typeSelect
    ? typeSelect.value === 'email'
    : props.types.length === 1 && props.types[0] === 'email'
}

function onTypeChange() {
  updateType()
  write()
}

watch([subject, body], () => {
  if (!reading)
    write()
})

onMounted(() => {
  suffixInput = document.getElementById(`${props.inputId}-url-suffix`) as HTMLInputElement | null
  typeSelect = document.getElementById(`${props.inputId}-type`) as HTMLSelectElement | null

  // Craft's markup changed: the URL suffix can still be typed by hand
  if (!suffixInput)
    return

  // Right below the address, instead of after the field's Advanced pane
  const island = root.value?.parentElement
  const linkRow = document.getElementById(props.inputId)?.querySelector('.link-input')
  if (island && linkRow)
    linkRow.after(island)

  updateType()
  read()

  // Typed in the URL suffix by hand
  suffixInput.addEventListener('input', read)

  // Craft's fieldtoggle changes the select through jQuery
  const $ = (window as any).jQuery
  $ ? $(typeSelect).on('change', onTypeChange) : typeSelect?.addEventListener('change', onTypeChange)
})

onBeforeUnmount(() => {
  suffixInput?.removeEventListener('input', read)
  const $ = (window as any).jQuery
  $ ? $(typeSelect).off('change', onTypeChange) : typeSelect?.removeEventListener('change', onTypeChange)
})
</script>

<template>
  <div v-show="isEmail" ref="root" class=":uno: mt-3 [&_.field:last-child]:!mb-0 [&_.field]:!mb-3">
    <Field :id="`${inputId}-email-subject`" label="Subject">
      <TextInput :id="`${inputId}-email-subject`" v-model="subject" />
    </Field>
    <Field :id="`${inputId}-email-body`" label="Body">
      <TextArea :id="`${inputId}-email-body`" v-model="body" :rows="4" />
    </Field>
  </div>
</template>
