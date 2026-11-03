<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { SupportReactionOption } from '@/modules/Roleplay/Game/Dto/SupportReactionOption';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import { actionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';

const props = defineProps<{
  open: boolean;
  options: SupportReactionOption[];
  busy: boolean;
  error: string | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  submit: [payload: { option: SupportReactionOption; side: SupportSide; itemInventoryId: number }];
}>();

const optionIndex = ref(0);
const side = ref<SupportSide>('attacker');
const weaponId = ref<number | null>(null);

const current = computed<SupportReactionOption | null>(() => props.options[optionIndex.value] ?? null);

const advantage = computed(() => {
  const spec = current.value?.rule.spec;
  if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'action') return 0;

  return spec.support?.advantage ?? 0;
});

const cost = computed(() => {
  const spec = current.value?.rule.spec;
  if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'action') return 0;
  const components = spec.action_components as ActionComponent[] | undefined;

  return actionOdCost(components, 0, 'action-points');
});

watch(
  () => [props.open, current.value] as const,
  () => {
    if (!current.value) return;
    side.value = current.value.sides[0] ?? 'attacker';
    weaponId.value = current.value.weapons[0]?.itemInventoryId ?? null;
  },
  { immediate: true },
);

function close(): void {
  emit('update:open', false);
}

function submit(): void {
  if (!current.value || weaponId.value === null) return;
  emit('submit', {
    option: current.value,
    side: side.value,
    itemInventoryId: weaponId.value,
  });
}
</script>

<template>
  <v-dialog :model-value="open" max-width="460" persistent @update:model-value="close">
    <v-card>
      <v-card-title class="text-body-1">Поддержать удар</v-card-title>
      <v-card-text>
        <div class="text-body-2 mb-3">
          Кто-то бьёт в ближнем бою. Поддержка даёт +{{ advantage }} преимущества от обстоятельств выбранной стороне и
          стоит {{ cost }} ОД.
        </div>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <v-select
          v-if="options.length > 1"
          v-model="optionIndex"
          :items="options.map((entry, index) => ({ title: entry.actor.name, value: index }))"
          label="Кто поддерживает"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <v-select
          v-if="current"
          v-model="side"
          :items="current.sides.map((value) => ({ title: value === 'attacker' ? 'Атакующий' : 'Защищающийся', value }))"
          label="Сторона"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <v-select
          v-if="current"
          v-model="weaponId"
          :items="current.weapons.map((weapon) => ({ title: weapon.name, value: weapon.itemInventoryId }))"
          label="Оружие"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <div class="text-caption text-medium-emphasis">
          Мастерство выбранного оружия проверит сервер. Если оно ниже требования карточки, поддержка будет отклонена.
        </div>
      </v-card-text>
      <v-card-actions>
        <v-btn variant="text" :disabled="busy" @click="close">Отказаться</v-btn>
        <v-spacer />
        <v-btn color="primary" :loading="busy" :disabled="!current || weaponId === null" @click="submit">
          Поддержать
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
