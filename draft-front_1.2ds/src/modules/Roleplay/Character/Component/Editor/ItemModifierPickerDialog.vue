<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { InventoryModifierOption } from '@/modules/Roleplay/Character/Dto/Editor/InventoryModifierOption';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';

const props = defineProps<{
  modifiers: InventoryModifierOption[];
  selectedRuleIds: readonly string[];
  rules: Rule[];
}>();

const open = defineModel<boolean>({ default: false });

const emit = defineEmits<{
  apply: [value: string[]];
}>();

const draft = ref<string[]>([]);

watch(open, (value) => {
  if (value) draft.value = [...props.selectedRuleIds];
});

const groups = computed(() => {
  const titles: string[] = [];
  const byTitle = new Map<string, InventoryModifierOption[]>();
  for (const modifier of props.modifiers) {
    const title = modifier.category || 'Прочее';
    if (!byTitle.has(title)) {
      titles.push(title);
      byTitle.set(title, []);
    }
    byTitle.get(title)?.push(modifier);
  }

  return titles.map((title) => ({ title, items: byTitle.get(title) ?? [] }));
});

function isSelected(ruleId: string): boolean {
  return draft.value.includes(ruleId);
}

function toggle(ruleId: string): void {
  draft.value = itemModifierService.toggleSelection(draft.value, ruleId, props.rules);
}

function onCancel(): void {
  open.value = false;
}

function onApply(): void {
  emit('apply', [...draft.value]);
  open.value = false;
}
</script>

<template>
  <v-dialog v-model="open" max-width="640" scrollable>
    <v-card rounded="lg">
      <v-card-title class="text-h6 pa-4 pb-2">Модификаторы предмета</v-card-title>
      <v-card-subtitle class="px-4 pb-2">
        В каждом типе — не больше одного. Выбор заменяет предыдущий модификатор того же типа.
      </v-card-subtitle>

      <v-card-text class="pa-0 picker-body">
        <div v-for="group in groups" :key="group.title" class="picker-group">
          <div class="picker-group__title">{{ group.title }}</div>
          <button
            v-for="modifier in group.items"
            :key="modifier.ruleId"
            type="button"
            class="picker-option"
            :class="{ 'picker-option--selected': isSelected(modifier.ruleId) }"
            @click="toggle(modifier.ruleId)"
          >
            <div class="picker-option__head">
              <span class="picker-option__name">{{ modifier.name }}</span>
              <span v-if="modifier.priceLabel" class="picker-option__price">{{ modifier.priceLabel }}</span>
            </div>
            <div v-for="(text, index) in modifier.effects" :key="index" class="picker-option__effect">
              {{ text }}
            </div>
            <div v-if="modifier.effects.length === 0" class="picker-option__effect text-medium-emphasis">
              Без текстового эффекта — влияет на цену.
            </div>
          </button>
        </div>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4 pt-2">
        <v-btn variant="text" color="medium-emphasis" @click="onCancel">Отмена</v-btn>
        <v-spacer />
        <v-btn variant="tonal" color="primary" @click="onApply">Применить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.picker-body {
  max-height: 70vh;
  padding: 8px 16px 12px;
}

.picker-group + .picker-group {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.picker-group__title {
  font-size: 12px;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-bottom: 8px;
}

.picker-option {
  display: block;
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  margin-bottom: 6px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.picker-option:last-child {
  margin-bottom: 0;
}

.picker-option--selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.picker-option__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.picker-option__name {
  font-weight: 600;
}

.picker-option__price {
  flex-shrink: 0;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.72);
}

.picker-option__effect {
  margin-top: 6px;
  font-size: 13px;
  line-height: 1.35;
  white-space: pre-line;
}
</style>
