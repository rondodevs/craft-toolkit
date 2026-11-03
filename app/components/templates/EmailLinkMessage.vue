<script setup lang="ts">
import type { SuffixParam } from '../../utils/mailto'
import { useForm } from '@formwerk/core'

/**
 * Subject and body of an email link (FieldHandlers): the inputs have no name, Craft's own inputs are what's saved.
 * With "URL Suffix" on the field they go in the suffix, otherwise in the query of the link's value
 * (`mailto:a@b.it?subject=…`, see EmailLinkType), kept out of the address input while it's edited.
 */
interface EmailLinkMessageProps {
  inputId: string
  types: string[]
  maxLength: number
}

const props = defineProps<EmailLinkMessageProps>()

const MESSAGE_KEYS = ['subject', 'body']

const root = useTemplateRef<HTMLElement>('root')
const subject = ref('')
const body = ref('')
const isEmail = ref(false)
const inValue = ref(false)

// The modal edits a copy: Cancel leaves the link as it was
const open = ref(false)
const { values: draft, formProps, handleSubmit, reset, setValues } = useForm({
  initialValues: { subject: '', body: '' },
})

const hasMessage = computed(() => !!(subject.value || body.value))
const summary = computed(() => subject.value || truncate(body.value.replace(/\s+/g, ' '), 60))

let suffixInput: HTMLInputElement | null = null
let typeSelect: HTMLSelectElement | null = null
let container: HTMLElement | null = null
let observer: MutationObserver | null = null
// The value's `?query`, kept while the address is typed (Craft writes the hidden input from the text input)
let valueQuery = ''

const isMessageKey = (key: string) => MESSAGE_KEYS.includes(key.toLowerCase())
const hiddenInput = () => container?.querySelector<HTMLInputElement>(':scope > input[type=hidden]') ?? null
function splitValue(value: string) {
  const at = value.indexOf('?')
  return at < 0 ? [value, ''] : [value.slice(0, at), value.slice(at)]
}

function truncate(text: string, length: number) {
  return text.length > length ? `${text.slice(0, length)}…` : text
}

function source() {
  return suffixInput ? suffixInput.value : valueQuery
}

function read() {
  const { params } = parseSuffix(source())
  const value = (key: string) => params.find(([name]) => name.toLowerCase() === key)?.[1] ?? ''

  subject.value = value('subject')
  body.value = value('body')
}

// Through jQuery too, so the element editor sees the change and saves a draft
function changed(input: HTMLInputElement) {
  const $ = (window as any).jQuery
  $ ? $(input).trigger('change') : input.dispatchEvent(new Event('change', { bubbles: true }))
}

// The query with this subject and body: other params (cc, bcc…) and a fragment stay as the editor typed them
function withMessage(query: string, message: SuffixParam[]) {
  const { params, hash } = parseSuffix(query)
  return formatSuffix([...message, ...params.filter(([key]) => !isMessageKey(key))], hash)
}

function writeValue() {
  const hidden = hiddenInput()
  if (!hidden)
    return

  const [address] = splitValue(hidden.value)
  const value = address ? `${address}${valueQuery}` : ''

  if (value !== hidden.value) {
    hidden.value = value
    changed(hidden)
  }
}

// Off the email type, subject and body come out of the suffix; a value belongs to its type already
function write() {
  const message: SuffixParam[] = [['subject', subject.value], ['body', body.value]]

  if (!suffixInput) {
    valueQuery = withMessage(valueQuery, message)
    writeValue()
    return
  }

  const suffix = withMessage(suffixInput.value, isEmail.value ? message : [])
  if (suffix !== suffixInput.value) {
    suffixInput.value = suffix
    changed(suffixInput)
  }
}

// Past the limit the field's own validation refuses the link: the value counts whole, query included
const length = computed(() => {
  if (!inValue.value)
    return 0

  const [address = ''] = splitValue(hiddenInput()?.value ?? '')
  return address.length + withMessage(valueQuery, [['subject', draft.subject?.trim() ?? ''], ['body', draft.body?.trim() ?? '']]).length
})

async function edit() {
  read()
  await reset({ value: { subject: subject.value, body: body.value } })
  open.value = true
}

// The message out of the link, once applied
function clear() {
  setValues({ subject: '', body: '' })
}

const apply = handleSubmit((data) => {
  const message = data.toObject()
  subject.value = message.subject?.trim() ?? ''
  body.value = message.body?.trim() ?? ''
  write()
  open.value = false
})

function updateType() {
  isEmail.value = typeSelect
    ? typeSelect.value === 'email'
    : props.types.length === 1 && props.types[0] === 'email'
}

function onTypeChange() {
  updateType()
  if (suffixInput)
    write()
}

// Craft just wrote the hidden input from the address: a query typed or pasted with it replaces ours
function onAddressInput() {
  const [, query] = splitValue(hiddenInput()?.value ?? '')

  if (query) {
    valueQuery = query
    read()
  }
  else {
    writeValue()
  }
}

// Craft's chip and text input show the whole value: only the address, the query has its own button
function hideQuery() {
  container?.querySelectorAll<HTMLAnchorElement>(':scope > .chip a').forEach((link) => {
    const [address] = splitValue(link.textContent ?? '')
    if (address !== link.textContent)
      link.textContent = address!.trim()
  })

  const text = container?.querySelector<HTMLInputElement>(':scope > input.text')
  if (text?.value.includes('?'))
    text.value = splitValue(text.value)[0]!
}

onMounted(() => {
  const $ = (window as any).jQuery
  suffixInput = document.getElementById(`${props.inputId}-url-suffix`) as HTMLInputElement | null
  typeSelect = document.getElementById(`${props.inputId}-type`) as HTMLSelectElement | null
  container = document.getElementById(`${props.inputId}-email`)

  if (!suffixInput) {
    // Craft's markup changed: nowhere to keep the message
    if (!container || !hiddenInput())
      return

    inValue.value = true
    valueQuery = splitValue(hiddenInput()!.value)[1]!
    hideQuery()

    // Craft's text input triggers `input` through jQuery
    $ ? $(container).on('input', onAddressInput) : container.addEventListener('input', onAddressInput)
    observer = new MutationObserver(hideQuery)
    observer.observe(container, { childList: true })
  }
  else {
    // Typed in the URL suffix by hand
    suffixInput.addEventListener('input', read)
  }

  // Right below the address, instead of after the field's Advanced pane
  const island = root.value?.parentElement
  const linkRow = document.getElementById(props.inputId)?.querySelector('.link-input')
  if (island && linkRow)
    linkRow.after(island)

  updateType()
  read()

  // Craft's fieldtoggle changes the select through jQuery
  $ ? $(typeSelect).on('change', onTypeChange) : typeSelect?.addEventListener('change', onTypeChange)
})

onBeforeUnmount(() => {
  const $ = (window as any).jQuery
  suffixInput?.removeEventListener('input', read)
  observer?.disconnect()
  $ ? $(container).off('input', onAddressInput) : container?.removeEventListener('input', onAddressInput)
  $ ? $(typeSelect).off('change', onTypeChange) : typeSelect?.removeEventListener('change', onTypeChange)
})
</script>

<template>
  <div v-show="isEmail" ref="root" class=":uno: mt-3 flex gap-2 items-center">
    <Btn small @click="edit">
      {{ hasMessage ? 'Edit subject and body' : 'Add subject and body' }}
    </Btn>
    <span v-if="hasMessage" class=":uno: text-light min-w-0 truncate">{{ summary }}</span>
  </div>

  <Modal v-model="open" title="Email subject and body" narrow>
    <form v-bind="formProps" @submit.prevent="apply">
      <FormTextField name="subject" label="Subject" />
      <FormTextAreaField
        name="body"
        label="Body"
        :rows="6"
        :warning="length > maxLength ? `The link would be ${length} characters long, over this field's Max Length (${maxLength}). Shorten the body, or raise Max Length (or turn on URL Suffix) in the field's settings.` : undefined"
      />
      <div class=":uno: flex gap-3 items-center justify-between">
        <Btn variant="tertiary" :disabled="!draft.subject && !draft.body" @click="clear">
          Reset
        </Btn>
        <div class="buttons">
          <Btn @click="open = false">
            Cancel
          </Btn>
          <Btn type="submit" variant="submit">
            Apply
          </Btn>
        </div>
      </div>
    </form>
  </Modal>
</template>
