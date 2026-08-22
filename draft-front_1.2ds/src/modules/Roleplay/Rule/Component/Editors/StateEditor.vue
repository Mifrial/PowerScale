<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import PeriodicityEditor from '@/modules/Roleplay/Rule/Component/Editors/State/PeriodicityEditor.vue';
import DecayEditor from '@/modules/Roleplay/Rule/Component/Editors/State/DecayEditor.vue';
import { stateSpecService } from '@/modules/Roleplay/Rule/Service/Instance/stateSpecService';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { StateValueType } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { StateAggregation } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import type { StateEffect } from '@/modules/Roleplay/Rule/Dto/State/StateEffect';

const props = defineProps<{
  name: string;
  code: string;
  codeDisabled?: boolean;
  description: string;
  mechanicId: number | null;
  keywordIds: number[];
  spec: RuleSpec | null;
  mechanicOptions: { title: string; value: number }[];
  keywordOptions: { title: string; value: number }[];
  spaceId: number;
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: StateSpec];
}>();

const expandedPanels = ref<string[]>(['general', 'state']);

const innerSpec = ref<StateSpec>(stateSpecService.createEmpty());

const characteristicOptions = computed(() =>
  ruleReferenceService.characteristicOptions(props.rules, props.spaceId).map((c) => ({ title: c.name, value: c.code })),
);
const valueTypeOptions: { title: string; value: StateValueType }[] = [
  { title: 'Флаг (есть/нет)', value: 'flag' },
  { title: 'Целое число', value: 'number' },
  { title: 'Размерное число', value: 'dimensional' },
];
const aggregationOptions: { title: string; value: StateAggregation }[] = [
  { title: 'Суммируется', value: 'sum' },
  { title: 'Берётся наибольшее', value: 'max' },
  { title: 'Каждая отдельно', value: 'independent' },
];
const damageSourceOptions: { title: string; value: 'value' | 'fixed' }[] = [
  { title: 'Значение состояния', value: 'value' },
  { title: 'Фиксированное число', value: 'fixed' },
];

const specToEmit = computed<StateSpec>(() => ({
  value_type: innerSpec.value.value_type,
  aggregation: innerSpec.value.aggregation,
  icon_code: innerSpec.value.icon_code,
  effects: innerSpec.value.effects ?? [],
}));

watch(specToEmit, (value) => emit('update:spec', value), { deep: true });

onMounted(() => {
  if (props.spec) {
    const existing = props.spec as StateSpec;
    innerSpec.value = {
      value_type: existing.value_type ?? 'flag',
      aggregation: existing.aggregation ?? 'sum',
      icon_code: existing.icon_code ?? null,
      effects: existing.effects ?? [],
    };
  }
});

function addEffect(): void {
  innerSpec.value.effects = innerSpec.value.effects ?? [];
  innerSpec.value.effects.push({
    type: 'characteristic_modify',
    characteristic_code: '',
    amount: 0,
  });
}

function addDotEffect(): void {
  innerSpec.value.effects = innerSpec.value.effects ?? [];
  innerSpec.value.effects.push({
    type: 'damage_over_time',
    damage: { kind: 'value' },
    periodicity: { kind: 'literal', value: 1, step: 'turn' },
  });
}

function removeEffect(index: number): void {
  innerSpec.value.effects?.splice(index, 1);
}

function setDamageSource(effect: Extract<StateEffect, { type: 'damage_over_time' }>, kind: 'value' | 'fixed'): void {
  effect.damage = kind === 'fixed' ? { kind: 'fixed', amount: 1 } : { kind: 'value' };
}

function isModifyEffect(effect: StateEffect): effect is Extract<StateEffect, { type: 'characteristic_modify' }> {
  return effect.type === 'characteristic_modify';
}

function isDotEffect(effect: StateEffect): effect is Extract<StateEffect, { type: 'damage_over_time' }> {
  return effect.type === 'damage_over_time';
}
</script>

<template>
  <div>
    <v-expansion-panels v-model="expandedPanels" multiple>
      <v-expansion-panel value="general">
        <v-expansion-panel-title>Общее</v-expansion-panel-title>
        <v-expansion-panel-text>
          <RuleEditorBase
            :name="name"
            @update:name="(v) => emit('update:name', v)"
            :code="code"
            @update:code="(v) => emit('update:code', v)"
            :code-disabled="codeDisabled"
            :description="description"
            @update:description="(v) => emit('update:description', v)"
            :mechanic-id="mechanicId"
            @update:mechanic-id="(v) => emit('update:mechanicId', v)"
            :keyword-ids="keywordIds"
            @update:keyword-ids="(v) => emit('update:keywordIds', v)"
            :mechanic-options="mechanicOptions"
            :keyword-options="keywordOptions"
          >
            <template #spec></template>
          </RuleEditorBase>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="state">
        <v-expansion-panel-title>Состояние</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-text-field
            v-model="innerSpec.icon_code"
            label="Иконка (mdi)"
            placeholder="mdi-fire"
            density="compact"
            hide-details
          />

          <v-select
            v-model="innerSpec.value_type"
            :items="valueTypeOptions"
            label="Хранение значения"
            density="compact"
            class="mt-3"
          />

          <v-select
            v-model="innerSpec.aggregation"
            :items="aggregationOptions"
            label="Объединение повторов"
            density="compact"
            class="mt-3"
          />
          <div class="text-caption text-medium-emphasis mt-1">
            Повторы правила в списке состояний персонажа: суммируются, берётся наибольшее или действуют отдельно
            (например, каждая Рана со своим значением).
          </div>

          <div class="d-flex align-center justify-space-between mt-3 mb-1">
            <div class="text-body-2 font-weight-medium">Эффекты</div>
            <div class="d-flex ga-2">
              <v-btn size="small" variant="text" prepend-icon="mdi-plus" @click="addEffect">Модификатор</v-btn>
              <v-btn size="small" variant="text" prepend-icon="mdi-fire" @click="addDotEffect">Урон со временем</v-btn>
            </div>
          </div>

          <v-sheet v-for="(effect, index) in innerSpec.effects" :key="index" class="pa-2 rounded border mb-2">
            <div class="d-flex align-center justify-space-between mb-1">
              <div class="text-body-2 font-weight-medium">
                {{ effect.type === 'characteristic_modify' ? 'Модификатор характеристики' : 'Урон со временем' }}
              </div>
              <v-btn icon="mdi-delete-outline" size="x-small" variant="text" @click="removeEffect(index)" />
            </div>

            <template v-if="isModifyEffect(effect)">
              <v-row dense>
                <v-col cols="6">
                  <v-select
                    v-model="effect.characteristic_code"
                    :items="characteristicOptions"
                    label="Характеристика"
                    density="compact"
                    hide-details
                  />
                </v-col>
                <v-col cols="3">
                  <v-text-field
                    v-model.number="effect.amount"
                    label="Значение"
                    type="number"
                    density="compact"
                    hide-details
                  />
                </v-col>
                <v-col cols="3" class="d-flex align-center">
                  <v-switch v-model="effect.per_unit" label="за ед." density="compact" hide-details />
                </v-col>
              </v-row>
              <div class="text-caption text-medium-emphasis mt-1">
                ±3 = ±1 размер (модифицирует Силу через modify). «за ед.» — умножается на текущее значение состояния.
              </div>
            </template>

            <template v-else-if="isDotEffect(effect)">
              <v-row dense>
                <v-col cols="6">
                  <v-select
                    :model-value="effect.damage.kind"
                    :items="damageSourceOptions"
                    label="Урон из"
                    density="compact"
                    hide-details
                    @update:model-value="(v) => setDamageSource(effect, v)"
                  />
                </v-col>
              </v-row>
              <v-row v-if="effect.damage.kind === 'fixed'" dense class="mt-2">
                <v-col cols="6">
                  <v-text-field
                    v-model.number="effect.damage.amount"
                    label="Значение урона за тик"
                    type="number"
                    min="1"
                    density="compact"
                    hide-details
                  />
                </v-col>
              </v-row>
              <v-row dense class="mt-2">
                <v-col cols="6">
                  <PeriodicityEditor
                    :model-value="effect.periodicity"
                    @update:model-value="effect.periodicity = $event"
                  />
                </v-col>
                <v-col cols="6">
                  <DecayEditor
                    :model-value="effect.decay"
                    :characteristics="characteristicOptions"
                    @update:model-value="effect.decay = $event"
                  />
                </v-col>
              </v-row>
            </template>
          </v-sheet>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
