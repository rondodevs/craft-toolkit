<script setup lang="ts">
// A table of reference text: cells are written in the component, with <strong> and <code> in them
interface DocTableProps {
  head: string[]
  rows: string[][]
}

defineProps<DocTableProps>()
</script>

<template>
  <div class=":uno: mb-4 mt-2 border border-hairline rounded-md overflow-x-auto">
    <table class=":uno: data fullwidth docTable m-0">
      <thead>
        <tr>
          <th v-for="cell in head" :key="cell">
            {{ cell }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row[0]">
          <td v-for="(cell, index) in row" :key="index" v-html="cell" />
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
/* Craft's table.data has no lines of its own outside an element index */
.docTable th {
  border-block-end: 1px solid var(--hairline-color);
  background: var(--gray-050);
  font-weight: 600;
  white-space: nowrap;
}

.docTable td,
.docTable th {
  padding: 8px 12px;
  vertical-align: top;
}

.docTable tbody tr + tr td {
  border-block-start: 1px solid var(--hairline-color);
}

.docTable td:first-child {
  font-weight: 600;
}
</style>
