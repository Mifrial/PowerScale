<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameLoot } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';

const props = defineProps<{
  rules: Rule[];
  groupOptions: string[];
  /** null — создание записи; иначе — правка лута в запасе. */
  initial: GameLoot | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  save: [data: CreateLootData];
}>();

const kind = ref<'item' | 'money'>('item');
const itemRuleCode = ref<string | null>(null);
const quantity = ref<number>(1);
const moneyAmount = ref<number | null>(null);
const group = ref('');
const notes = ref('');

const itemRules = computed(() => props.rules.filter((rule) => rule.type === 'item'));

const canSave = computed(() => {
  if (kind.value === 'item') return itemRuleCode.value !== null && quantity.value >= 1;
  const amount = moneyAmount.value;

  return amount !== null && Number.isFinite(amount) && amount > 0;
});

function reset(): void {
  const initial = props.initial;
  if (initial && initial.itemRuleCode !== null) {
    kind.value = 'item';
    itemRuleCode.value = initial.itemRuleCode;
    quantity.value = initial.quantity;
    moneyAmount.value = null;
  } else if (initial && initial.moneyAmount !== null) {
    kind.value = 'money';
    moneyAmount.value = initial.moneyAmount;
    itemRuleCode.value = null;
    quantity.value = 1;
  } else {
    kind.value = 'item';
    itemRuleCode.value = null;
    quantity.value = 1;
    moneyAmount.value = null;
  }
  group.value = initial?.group ?? '';
  notes.value = initial?.notes ?? '';
}

function save(): void {
  const data: CreateLootData = {
    group: group.value.trim() || null,
    itemRuleCode: kind.value === 'item' ? itemRuleCode.value : null,
    quantity: kind.value === 'item' ? quantity.value : 0,
    moneyAmount: kind.value === 'money' ? moneyAmount.value : null,
    notes: notes.value.trim() || null,
  };
  emit('save', data);
}

watch(
  () => props.initial,
  () => {
    if (open.value) reset();
  },
);

watch(open, (value) => {
  if (value) reset();
});
</script>

<template>
  <v-dialog v-model="open" max-width="520">
    <v-card>
      <v-card-title class="text-subtitle-1">{{ initial ? 'Правка добычи' : 'Добавить лут' }}</v-card-title>
      <v-card-text class="d-flex flex-column ga-2">
        <v-btn-toggle v-model="kind" color="primary" density="compact" variant="tonal" divided>
          <v-btn value="item">Предмет</v-btn>
          <v-btn value="money">Деньги</v-btn>
        </v-btn-toggle>

        <template v-if="kind === 'item'">
          <v-autocomplete
            v-model="itemRuleCode"
            :items="itemRules"
            item-title="name"
            item-value="code"
            label="Предмет"
            hint="Из правил ревизии игры"
            persistent-hint
            density="compact"
            clearable
          />
          <v-text-field
            v-model.number="quantity"
            label="Количество"
            type="number"
            min="1"
            density="compact"
            hide-details
          />
        </template>

        <v-text-field
          v-else
          v-model.number="moneyAmount"
          label="Сумма, гм"
          type="number"
          min="1"
          density="compact"
          hide-details
        />

        <v-combobox
          v-model="group"
          :items="groupOptions"
          label="Группа"
          hint="Свободный тег для группировки добычи (например, «Тролльи холмы»)"
          persistent-hint
          density="compact"
          clearable
        />
        <v-textarea v-model="notes" label="Заметки" rows="2" density="compact" hide-details />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="tonal" :disabled="!canSave" @click="save">
          {{ initial ? 'Сохранить' : 'Добавить' }}
        </v-btn>
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
