<script setup lang="ts">
import { computed } from 'vue';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import { buildLoreTree } from '@/modules/Roleplay/Lore/Utils/loreTree';
import { flattenLoreNav } from '@/modules/Roleplay/Lore/Utils/loreNav';

const props = defineProps<{
  world: LoreWorldDetail;
  currentArticleCode?: string | null;
}>();

const emit = defineEmits<{
  open: [code: string];
}>();

const rows = computed(() => flattenLoreNav(buildLoreTree(props.world.sections, props.world.articles)));

function isActive(code: string): boolean {
  return props.currentArticleCode === code;
}
</script>

<template>
  <v-card variant="outlined" class="lore-nav">
    <v-card-text class="pa-2">
      <div v-if="rows.length === 0" class="text-body-2 text-medium-emphasis pa-3">В этом мире пока нет статей.</div>
      <v-list v-else density="compact" nav>
        <v-list-item
          v-for="row in rows"
          :key="row.kind === 'section' ? `s:${row.code}` : `a:${row.article.code}`"
          :style="{ paddingInlineStart: `${12 + row.depth * 16}px` }"
          :active="row.kind === 'article' && isActive(row.article.code)"
          @click="row.kind === 'article' ? emit('open', row.article.code) : undefined"
        >
          <template #prepend>
            <v-icon v-if="row.kind === 'section'" size="small" class="mr-2">{{ row.icon || 'mdi-folder' }}</v-icon>
            <v-icon v-else size="x-small" class="mr-2">mdi-text-box-outline</v-icon>
          </template>
          <v-list-item-title v-if="row.kind === 'section'" class="text-body-2 text-medium-emphasis">
            {{ row.name }}
          </v-list-item-title>
          <v-list-item-title v-else class="text-body-2">{{ row.article.title }}</v-list-item-title>
          <template v-if="row.kind === 'article'" #append>
            <v-chip v-if="row.article.status === 'draft'" size="x-small" color="warning" variant="tonal">
              черновик
            </v-chip>
            <v-icon v-if="row.article.visibility === 'secret'" size="x-small" color="warning" class="ml-1">
              mdi-lock
            </v-icon>
          </template>
        </v-list-item>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.lore-nav {
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}
</style>
