<script setup lang="ts">
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { ITEM_MODIFIER_OP_TYPE_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_MODIFIER_OP_TYPE_OPTIONS';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';

const props = defineProps<{
  modelValue: ItemModifierOp[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: ItemModifierOp[]];
}>();

function emptyOp(type: ItemModifierOp['type'] = 'weight'): ItemModifierOp {
  switch (type) {
    case 'min_strength':
      return { type, delta: 0 };
    case 'action_strength':
      return { type, field: 'damage', delta: 0 };
    case 'resistance':
      return { type, damage_type_code: 'magic-damage', mode: 'max', value: 1 };
    case 'weight':
      return { type: 'weight' };
    case 'durability':
      return { type: 'durability' };
    case 'block':
      return { type: 'block' };
    case 'defense':
      return { type: 'defense' };
    case 'armor_reliability':
      return { type: 'armor_reliability' };
    case 'max_agility':
      return { type: 'max_agility' };
    case 'strength_penalty':
      return { type: 'strength_penalty' };
    case 'keyword':
      return { type: 'keyword', add: [], remove: [] };
    case 'min_action_cost':
      return { type, min: 2 };
    case 'magic_conductor':
      return { type, value: 1 };
    case 'advantage':
      return { type, delta: -1, source_code: 'tool' };
    case 'check_advantage':
      return { type, delta: -1, characteristic_codes: ['attention'] };
  }
}

function setOps(ops: ItemModifierOp[]): void {
  emit('update:modelValue', ops);
}

function addOp(): void {
  setOps([...(props.modelValue ?? []), emptyOp()]);
}

function removeOp(index: number): void {
  setOps(props.modelValue.filter((_, i) => i !== index));
}

function changeType(index: number, type: ItemModifierOp['type']): void {
  setOps(props.modelValue.map((op, i) => (i === index ? emptyOp(type) : op)));
}

function patch(index: number, partial: Record<string, unknown>): void {
  setOps(props.modelValue.map((op, i) => (i === index ? { ...op, ...partial } : op)));
}

function splitCodes(value: string): string[] {
  return value
    .split(',')
    .map((code) => code.trim())
    .filter((code) => code.length > 0);
}
</script>

<template>
  <div class="ops">
    <div v-for="(op, index) in modelValue" :key="index" class="ops__row">
      <v-select
        :model-value="op.type"
        :items="ITEM_MODIFIER_OP_TYPE_OPTIONS"
        item-title="title"
        item-value="value"
        label="Поле"
        density="compact"
        hide-details
        style="flex: 0 0 180px"
        @update:model-value="(v: ItemModifierOp['type']) => changeType(index, v)"
      />
      <template v-if="op.type === 'weight'">
        <ClampedNumberField
          :model-value="op.factor ?? 0"
          label="Множитель"
          :min="0"
          step="0.05"
          density="compact"
          hide-details
          style="flex: 1 1 120px"
          @update:model-value="(v: number) => patch(index, { factor: v !== 0 ? v : undefined })"
        />
        <ClampedNumberField
          :model-value="op.add_kg ?? 0"
          label="+ кг"
          step="0.1"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { add_kg: v !== 0 ? v : undefined })"
        />
      </template>
      <template v-else-if="op.type === 'min_strength' || op.type === 'action_strength'">
        <ClampedNumberField
          :model-value="'delta' in op ? op.delta : 0"
          label="Пункты"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { delta: v })"
        />
        <v-select
          v-if="op.type === 'action_strength'"
          :model-value="op.field"
          :items="[
            { title: 'Урон', value: 'damage' },
            { title: 'Пробитие', value: 'penetration' },
          ]"
          item-title="title"
          item-value="value"
          label="Формула"
          density="compact"
          hide-details
          style="flex: 0 0 140px"
          @update:model-value="(v: string) => patch(index, { field: v })"
        />
      </template>
      <template v-else-if="op.type === 'durability' || op.type === 'max_agility'">
        <ClampedNumberField
          :model-value="op.delta ?? 0"
          label="Пункты"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { delta: v !== 0 ? v : undefined })"
        />
        <ClampedNumberField
          :model-value="op.add_size ?? 0"
          label="Размер"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { add_size: v !== 0 ? v : undefined })"
        />
      </template>
      <template v-else-if="op.type === 'block' || op.type === 'defense'">
        <ClampedNumberField
          :model-value="op.factor ?? 0"
          label="Множитель"
          :min="0"
          step="0.05"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { factor: v !== 0 ? v : undefined })"
        />
        <ClampedNumberField
          :model-value="op.add ?? 0"
          label="База"
          density="compact"
          hide-details
          style="flex: 1 1 80px"
          @update:model-value="(v: number) => patch(index, { add: v !== 0 ? v : undefined })"
        />
        <ClampedNumberField
          :model-value="op.add_size ?? 0"
          label="Размер"
          density="compact"
          hide-details
          style="flex: 1 1 80px"
          @update:model-value="(v: number) => patch(index, { add_size: v !== 0 ? v : undefined })"
        />
      </template>
      <template v-else-if="op.type === 'armor_reliability' || op.type === 'strength_penalty'">
        <ClampedNumberField
          :model-value="op.set ?? 0"
          label="Задать"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { set: v !== 0 ? v : undefined })"
        />
        <ClampedNumberField
          :model-value="op.add ?? 0"
          label="Слагаемое"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { add: v !== 0 ? v : undefined })"
        />
      </template>
      <template v-else-if="op.type === 'resistance'">
        <v-text-field
          :model-value="op.damage_type_code"
          label="Тип урона"
          density="compact"
          hide-details
          style="flex: 1 1 140px"
          @update:model-value="(v: string) => patch(index, { damage_type_code: v })"
        />
        <v-select
          :model-value="op.mode"
          :items="[
            { title: 'add', value: 'add' },
            { title: 'add_size', value: 'add_size' },
            { title: 'max', value: 'max' },
          ]"
          item-title="title"
          item-value="value"
          label="Режим"
          density="compact"
          hide-details
          style="flex: 0 0 140px"
          @update:model-value="(v: string) => patch(index, { mode: v })"
        />
        <ClampedNumberField
          :model-value="op.value"
          label="Значение"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { value: v })"
        />
      </template>
      <template v-else-if="op.type === 'keyword'">
        <v-text-field
          :model-value="(op.add ?? []).join(', ')"
          label="Добавить (коды)"
          density="compact"
          hide-details
          style="flex: 1 1 160px"
          @update:model-value="(v: string) => patch(index, { add: splitCodes(v) })"
        />
        <v-text-field
          :model-value="(op.remove ?? []).join(', ')"
          label="Снять (коды)"
          density="compact"
          hide-details
          style="flex: 1 1 160px"
          @update:model-value="(v: string) => patch(index, { remove: splitCodes(v) })"
        />
      </template>
      <template v-else-if="op.type === 'min_action_cost'">
        <ClampedNumberField
          :model-value="op.min"
          label="Мин. ОД"
          :min="1"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { min: v })"
        />
      </template>
      <template v-else-if="op.type === 'magic_conductor'">
        <ClampedNumberField
          :model-value="op.value"
          label="Величина"
          :min="0"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { value: v })"
        />
      </template>
      <template v-else-if="op.type === 'advantage'">
        <ClampedNumberField
          :model-value="op.delta"
          label="Дельта"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { delta: v })"
        />
        <v-text-field
          :model-value="op.source_code"
          label="Источник"
          density="compact"
          hide-details
          style="flex: 1 1 140px"
          @update:model-value="(v: string) => patch(index, { source_code: v })"
        />
      </template>
      <template v-else-if="op.type === 'check_advantage'">
        <ClampedNumberField
          :model-value="op.delta"
          label="Дельта"
          density="compact"
          hide-details
          style="flex: 1 1 100px"
          @update:model-value="(v: number) => patch(index, { delta: v })"
        />
        <v-text-field
          :model-value="op.characteristic_codes.join(', ')"
          label="Характеристики"
          density="compact"
          hide-details
          style="flex: 1 1 160px"
          @update:model-value="(v: string) => patch(index, { characteristic_codes: splitCodes(v) })"
        />
      </template>
      <v-btn icon size="small" color="error" variant="text" @click="removeOp(index)">
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-btn variant="text" color="primary" size="small" @click="addOp">
      <v-icon start>mdi-plus</v-icon>
      Операция
    </v-btn>
  </div>
</template>

<style scoped>
.ops__row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
}
</style>
