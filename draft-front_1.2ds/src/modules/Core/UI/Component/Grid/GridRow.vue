<script setup lang="ts">
import { getRenderer } from '@/modules/Core/UI/Component/Grid/cells/registry';
import StringCell from '@/modules/Core/UI/Component/Grid/cells/StringCell.vue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition';
import type { Row } from '@/modules/Core/UI/Dto/Row';

defineProps<{
  gridId?: string;
  columns: ColumnDefinition[];
  item: Row;
}>();

const emit = defineEmits<{
  'row-action': [payload: { action: string; row: Row }];
}>();

function cellComponent(type: string) {
  return getRenderer(type) ?? StringCell;
}
</script>

<template>
  <tr>
    <td v-if="gridId" class="smart-cell--settings">
      <v-menu location="bottom start" offset="4">
        <template #activator="{ props: menuProps }">
          <v-icon v-bind="menuProps" size="small" class="smart-burger-icon">mdi-dots-vertical</v-icon>
        </template>
        <v-list density="compact" nav>
          <v-list-item @click="emit('row-action', { action: 'view-profile', row: item })">
            <template #prepend>
              <v-icon size="small">mdi-account-outline</v-icon>
            </template>
            <v-list-item-title>Посмотреть профиль</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </td>
    <td v-for="col in columns" :key="col.key">
      <span
        v-if="col.meta?.clickable"
        class="smart-cell--clickable"
        @click="emit('row-action', { action: 'view-profile', row: item })"
      >
        <component :is="cellComponent(col.type)" :value="item[col.key]" :column="col" />
      </span>
      <component v-else :is="cellComponent(col.type)" :value="item[col.key]" :column="col" />
    </td>
  </tr>
</template>

<style scoped>
.smart-cell--settings {
  width: 1%;
  padding: 0;
  text-align: center;
}
.smart-cell--settings .smart-burger-icon {
  opacity: 0.5;
  cursor: pointer;
  transition: opacity 0.15s;
}
.smart-cell--settings .smart-burger-icon:hover {
  opacity: 1;
}
.smart-cell--clickable {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
}
.smart-cell--clickable:hover {
  text-decoration: underline;
}
</style>
