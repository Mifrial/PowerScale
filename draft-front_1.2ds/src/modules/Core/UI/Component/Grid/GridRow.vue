<script setup lang="ts">
import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import StringCell from '@/modules/Core/UI/Component/Grid/cells/StringCell.vue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { RowMenuAction } from '@/modules/Core/UI/Dto/Grid/RowMenuAction';

defineProps<{
  gridId?: string;
  columns: ColumnDefinition[];
  item: Record<string, unknown>;
  rowMenu?: RowMenuAction[];
}>();

const emit = defineEmits<{
  'row-action': [payload: { action: string; row: Record<string, unknown> }];
}>();

function cellComponent(type: string) {
  return fieldTypeRegistry.get(type)?.cell ?? StringCell;
}
</script>

<template>
  <tr>
    <td v-if="rowMenu?.length" class="smart-cell--settings">
      <v-menu location="bottom start" offset="4">
        <template #activator="{ props: menuProps }">
          <v-icon v-bind="menuProps" size="small" class="smart-burger-icon">mdi-dots-vertical</v-icon>
        </template>
        <v-list density="compact" nav>
          <v-list-item
            v-for="menu in rowMenu"
            :key="menu.action"
            @click="emit('row-action', { action: menu.action, row: item })"
          >
            <template #prepend>
              <v-icon size="small">{{ menu.icon }}</v-icon>
            </template>
            <v-list-item-title>{{ menu.label }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>
    </td>
    <td v-for="col in columns" :key="col.key">
      <span
        v-if="col.meta?.clickable"
        class="smart-cell--clickable"
        @click="emit('row-action', { action: 'open', row: item })"
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
