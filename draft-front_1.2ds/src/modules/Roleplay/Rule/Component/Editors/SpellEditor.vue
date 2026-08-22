<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import type { SpellSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellSpec';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const props = defineProps<{
  modelValue: SpellSpec | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: SpellSpec];
}>();

const inner = ref<SpellSpec>(defaultSpec());

function defaultSpec(): SpellSpec {
  return {
    difficulty: { base: 3, size: 0 },
    duration: { type: 'instant' },
  };
}

function patch(key: string, value: unknown) {
  inner.value = { ...inner.value, [key]: value };
}

function updateDurationType(type: string) {
  inner.value = {
    ...inner.value,
    duration: abilitySpecService.createEmptySpellDuration(type as SpellDuration['type']),
  };
}

function patchDuration(key: string, value: unknown) {
  const d = inner.value.duration;
  if (d.type === 'instant') return;
  inner.value = { ...inner.value, duration: { ...d, [key]: value } };
}

function toggleDurationLimit(checked: boolean) {
  const d = inner.value.duration;
  if (d.type === 'instant') return;
  const limit = checked ? { value: 1, unit: 'turn' as const } : undefined;
  inner.value = { ...inner.value, duration: { ...d, limit } };
}

function patchDurationLimit(key: string, value: unknown) {
  const d = inner.value.duration;
  if (d.type === 'instant' || !d.limit) return;
  inner.value = { ...inner.value, duration: { ...d, limit: { ...d.limit, [key]: value } } };
}

watch(
  inner,
  (value) => {
    emit('update:modelValue', cloneData(value));
  },
  { deep: true },
);

onMounted(() => {
  if (props.modelValue) {
    inner.value = normalize(cloneData(props.modelValue));
  }
});

function normalize(raw: SpellSpec): SpellSpec {
  return {
    difficulty: raw.difficulty ?? { base: 3, size: 0 },
    duration: raw.duration ?? { type: 'instant' },
  };
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Заклинание — волшебное действие. Сложность сотворения и длительность.
    </div>

    <DimensionalNumberInput
      :model-value="inner.difficulty ?? null"
      @update:model-value="patch('difficulty', $event)"
      label="Сложность сотворения"
    />

    <div class="mt-3">
      <div class="text-subtitle-2 mb-1">Продолжительность</div>
      <v-radio-group
        :model-value="inner.duration?.type ?? 'instant'"
        @update:model-value="(v) => updateDurationType(v ?? 'instant')"
        density="compact"
        hide-details
      >
        <v-radio label="Мгновенное" value="instant" />
        <v-radio label="Обновляемое" value="refreshable" />
        <v-radio label="Поддерживаемое" value="sustained" />
      </v-radio-group>

      <template v-if="inner.duration && inner.duration.type !== 'instant'">
        <div class="d-flex gap-2 mt-2 flex-wrap">
          <DimensionalNumberInput
            :model-value="inner.duration.difficulty ?? null"
            @update:model-value="patchDuration('difficulty', $event)"
            label="Сложность обновления/поддержания"
            style="min-width: 220px"
          />
          <ClampedNumberField
            :model-value="
              typeof inner.duration.action_cost === 'number'
                ? inner.duration.action_cost
                : (inner.duration.action_cost?.base ?? 0)
            "
            @update:model-value="patchDuration('action_cost', $event)"
            label="ОД на обновление/поддержание"
            :min="0"
            density="compact"
            hide-details
            style="min-width: 160px"
          />
        </div>
        <div class="mt-2">
          <v-checkbox
            :model-value="!!inner.duration.limit"
            @update:model-value="(v) => toggleDurationLimit(!!v)"
            label="Предел длительности"
            density="compact"
            hide-details
          />
          <div v-if="inner.duration.limit" class="d-flex gap-2 flex-wrap mt-1">
            <ClampedNumberField
              :model-value="
                typeof inner.duration.limit.value === 'number'
                  ? inner.duration.limit.value
                  : (inner.duration.limit.value?.base ?? 0)
              "
              @update:model-value="patchDurationLimit('value', $event)"
              label="Значение"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 120px"
            />
            <v-select
              :model-value="inner.duration.limit.unit"
              @update:model-value="patchDurationLimit('unit', $event)"
              :items="[
                { label: 'Ход', value: 'turn' },
                { label: 'Минута', value: 'minute' },
                { label: 'Час', value: 'hour' },
              ]"
              item-title="label"
              item-value="value"
              label="Единица"
              density="compact"
              hide-details
              style="min-width: 160px"
            />
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
