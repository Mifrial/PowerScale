<script setup lang="ts">
import { computed, ref } from 'vue';
import type { GameActionModifier } from '@/modules/Roleplay/Game/Dto/GameActionModifier';

const props = defineProps<{
  options: GameActionModifier[];
  modelValue: string[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string[]];
}>();

const open = ref(false);

const groups = computed(() => {
  const order: string[] = [];
  const byCard = new Map<string, GameActionModifier[]>();
  for (const option of props.options) {
    const current = byCard.get(option.actionCode);
    if (!current) {
      order.push(option.actionCode);
      byCard.set(option.actionCode, [option]);
      continue;
    }
    current.push(option);
  }

  return order.map((code) => ({
    code,
    title: byCard.get(code)?.[0]?.actionName || code,
    items: byCard.get(code) ?? [],
  }));
});

const selected = computed(() => props.options.filter((option) => props.modelValue.includes(option.code)));

function isOn(code: string): boolean {
  return props.modelValue.includes(code);
}

function toggle(code: string, on: boolean | null): void {
  const next = props.modelValue.filter((item) => item !== code);
  if (on) next.push(code);
  emit('update:modelValue', next);
}

function remove(code: string): void {
  emit(
    'update:modelValue',
    props.modelValue.filter((item) => item !== code),
  );
}
</script>

<template>
  <div v-if="options.length">
    <div class="d-flex align-center flex-wrap ga-2">
      <v-btn size="small" variant="tonal" :disabled="disabled" @click="open = true">
        <v-icon start size="18">mdi-tune-variant</v-icon>
        Модификаторы действия
        <v-badge v-if="selected.length" inline :content="selected.length" color="primary" class="ms-2" />
      </v-btn>
      <v-chip v-for="option in selected" :key="option.code" size="small" closable @click:close="remove(option.code)">
        {{ option.name }}
      </v-chip>
    </div>

    <v-dialog v-model="open" max-width="520">
      <v-card>
        <v-card-title class="text-body-1">Модификаторы действия</v-card-title>
        <v-card-text>
          <div v-for="group in groups" :key="group.code" class="mb-2">
            <div v-if="groups.length > 1" class="text-caption text-medium-emphasis">{{ group.title }}</div>
            <template v-for="option in group.items" :key="option.code">
              <v-checkbox
                :model-value="isOn(option.code)"
                :label="option.name"
                hide-details
                density="compact"
                @update:model-value="toggle(option.code, $event)"
              />
              <div v-if="option.summary" class="text-caption text-medium-emphasis ms-8 mb-2">
                {{ option.summary }}
              </div>
            </template>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn color="primary" variant="text" @click="open = false">Готово</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
