<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import type { BreadcrumbItem } from '@/router/meta'

const route = useRoute()

const items = computed(() => {
  const crumbs: BreadcrumbItem[] = []
  for (const record of route.matched) {
    const resolve = record.meta.crumb
    if (resolve) {
      const part = resolve(route)
      if (part.length) crumbs.push(...part)
    }
  }
  if (!crumbs.length) {
    crumbs.push({ title: route.meta.title || 'PowerScale' })
  }
  return crumbs.map((crumb, i) => ({
    title: crumb.title,
    to: crumb.to,
    disabled: i === crumbs.length - 1 || !crumb.to,
  }))
})
</script>

<template>
  <v-breadcrumbs :items="items" class="px-2 pt-0 pb-0" />
</template>
