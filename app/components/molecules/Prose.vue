<template>
  <!-- Long help text: readable line length, spaced headings, numbered lists -->
  <div class=":uno: prose max-w-275">
    <slot />
  </div>
</template>

<style scoped>
.prose > :deep(:is(p, ul, ol)) {
  max-width: 75ch;
}

.prose :deep(h3) {
  font-size: 16px;
  margin-block: 32px 8px;
}

.prose :deep(ol) {
  list-style: decimal;
  padding-inline-start: 20px;
}

.prose :deep(li + li) {
  margin-block-start: 4px;
}

.prose :deep(pre) {
  max-width: 75ch;
  margin-block: 8px 12px;
  padding: 12px 14px;
  border: 1px solid var(--hairline-color);
  border-radius: 6px;
  background: var(--gray-050);
  overflow-x: auto;
}
</style>
