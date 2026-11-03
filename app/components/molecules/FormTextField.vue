<script setup lang="ts">
import type { TextFieldProps } from '@formwerk/core'
import { useTextField } from '@formwerk/core'

// A formwerk text field in Craft's `_includes/forms/field` markup (as Field), for forms built with formwerk's useForm
interface FormTextFieldProps extends TextFieldProps {
  instructions?: string
  warning?: string
  code?: boolean
}

const props = defineProps<FormTextFieldProps>()

const { inputProps, labelProps, errorMessage, errorMessageProps, isBlurred } = useTextField(props)

const errored = computed(() => isBlurred.value && errorMessage.value)
</script>

<template>
  <div class="field" :class="{ 'has-errors': errored }">
    <div class="heading">
      <label v-bind="labelProps">{{ label }}</label>
    </div>
    <div v-if="instructions" class="instructions">
      <p v-html="inlineMarkdown(instructions)" />
    </div>
    <div class="input ltr">
      <input v-bind="inputProps" class="text fullwidth" :class="{ code }" autocomplete="off">
    </div>
    <p v-if="warning" class="warning has-icon">
      <span class="icon" aria-hidden="true" />
      <span v-html="inlineMarkdown(warning)" />
    </p>
    <ul v-if="errored" class="errors">
      <li v-bind="errorMessageProps">
        {{ errorMessage }}
      </li>
    </ul>
  </div>
</template>
