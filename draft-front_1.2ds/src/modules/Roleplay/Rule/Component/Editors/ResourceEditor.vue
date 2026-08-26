<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { resourceSpecService } from '@/modules/Roleplay/Rule/Service/Instance/resourceSpecService';
import type { ResourceSpec, ResourceLimitAdjustment } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';

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
  /** Правила пространства (для выбора характеристик и источников условий лимита). */
  rules?: Rule[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: ResourceSpec];
}>();

const expandedPanels = ref<string[]>(['general', 'resource']);

function resourceFromSpec(value: RuleSpec | null): ResourceSpec {
  if (!value) return resourceSpecService.createEmpty();
  const spec = value as ResourceSpec;
  const legacy = spec as ResourceSpec & { initial_value?: DimensionalNumberValue | number | null };
  const base: DimensionalNumberValue | number | undefined =
    spec.limit?.base ?? (legacy.initial_value as DimensionalNumberValue | number | null | undefined) ?? undefined;

  return {
    is_dimensional: spec.is_dimensional ?? true,
    auto_add: spec.auto_add ?? false,
    limit: {
      base: base ?? (spec.is_dimensional ? { base: 3, size: 0 } : 0),
      adjustments: spec.limit?.adjustments ?? [],
    },
  };
}

const innerSpec = ref<ResourceSpec>(resourceFromSpec(props.spec));

const characteristics = computed(() =>
  (props.rules ?? [])
    .filter((rule) => rule.type === 'characteristic')
    .map((rule) => ({ code: rule.code, name: rule.name })),
);

const sources = computed(() =>
  (props.rules ?? []).filter((rule) => rule.type === 'source').map((rule) => ({ code: rule.code, name: rule.name })),
);

const ADJUSTMENT_KINDS: { label: string; value: Formula['type'] }[] = [
  { label: 'Число', value: 'fixed' },
  { label: 'Размер характеристики', value: 'characteristic_size' },
  { label: 'Разница размеров', value: 'characteristic_size_gap' },
];

const dimensionalBase = computed<DimensionalNumberValue | null>({
  get: () => {
    const raw = innerSpec.value.limit?.base;
    if (raw && typeof raw === 'object') return raw;

    return null;
  },
  set: (val) => {
    if (!innerSpec.value.limit) return;
    if (val) innerSpec.value.limit.base = val;
  },
});

const specToEmit = computed<ResourceSpec>(() => ({
  is_dimensional: innerSpec.value.is_dimensional,
  auto_add: innerSpec.value.auto_add,
  limit: innerSpec.value.limit
    ? { ...innerSpec.value.limit, adjustments: [...innerSpec.value.limit.adjustments] }
    : undefined,
}));

watch(
  specToEmit,
  (value) => {
    emit('update:spec', value);
  },
  { deep: true, immediate: true },
);

function addAdjustment(): void {
  if (!innerSpec.value.limit) return;
  innerSpec.value.limit.adjustments = [
    ...innerSpec.value.limit.adjustments,
    { value: { type: 'characteristic_size', characteristic_code: '' }, source_code: '' },
  ];
}

function removeAdjustment(index: number): void {
  if (!innerSpec.value.limit) return;
  innerSpec.value.limit.adjustments = innerSpec.value.limit.adjustments.filter((_, i) => i !== index);
}

function updateAdjustment(index: number, patch: Partial<ResourceLimitAdjustment>): void {
  if (!innerSpec.value.limit) return;
  innerSpec.value.limit.adjustments = innerSpec.value.limit.adjustments.map((entry, i) =>
    i === index ? { ...entry, ...patch } : entry,
  );
}

function adjustmentKind(adjustment: ResourceLimitAdjustment): Formula['type'] {
  return adjustment.value?.type ?? 'fixed';
}

function updateAdjustmentKind(index: number, type: Formula['type']): void {
  if (type === 'fixed') updateAdjustment(index, { value: { type: 'fixed', value: 0 } });
  else if (type === 'characteristic_size')
    updateAdjustment(index, { value: { type: 'characteristic_size', characteristic_code: '' } });
  else if (type === 'characteristic_size_gap')
    updateAdjustment(index, {
      value: { type: 'characteristic_size_gap', characteristic_code_from: '', characteristic_code_to: '' },
    });
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

      <v-expansion-panel value="resource">
        <v-expansion-panel-title>Ресурс</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-switch v-model="innerSpec.is_dimensional" label="Размерный ресурс" color="primary" hide-details />
          <v-switch
            v-model="innerSpec.auto_add"
            label="Автодобавление персонажу"
            color="primary"
            hide-details
            class="mt-1"
          />
          <div v-if="innerSpec.auto_add" class="text-body-2 text-medium-emphasis mt-1">
            Ресурс добавляется каждому персонажу автоматически; лимит считается из базового значения и условий ниже.
          </div>

          <div class="mt-2">
            <div v-if="innerSpec.is_dimensional">
              <DimensionalNumberInput v-model="dimensionalBase" label="Базовый лимит" />
            </div>
            <v-text-field
              v-else
              :model-value="typeof innerSpec.limit?.base === 'number' ? innerSpec.limit.base : 0"
              @update:model-value="
                (v) => {
                  if (innerSpec.limit) innerSpec.limit.base = Number(v) || 0;
                }
              "
              label="Базовый лимит"
              type="number"
              density="compact"
            />
          </div>

          <div class="mt-3">
            <div class="text-subtitle-2 d-flex align-center justify-space-between">
              <span>Условия изменения лимита</span>
              <v-btn
                variant="text"
                size="small"
                color="primary"
                prepend-icon="mdi-plus"
                :disabled="!innerSpec.limit"
                @click="addAdjustment"
              >
                Добавить
              </v-btn>
            </div>

            <div
              v-for="(adjustment, index) in innerSpec.limit?.adjustments ?? []"
              :key="index"
              class="d-flex ga-2 align-center mt-2"
            >
              <v-select
                :model-value="adjustmentKind(adjustment)"
                @update:model-value="(v) => updateAdjustmentKind(index, v as Formula['type'])"
                :items="ADJUSTMENT_KINDS"
                item-title="label"
                item-value="value"
                label="Условие"
                density="compact"
                hide-details
                style="min-width: 160px"
              />

              <v-text-field
                v-if="adjustment.value?.type === 'fixed'"
                :model-value="adjustment.value.type === 'fixed' ? adjustment.value.value : 0"
                @update:model-value="
                  (v) => updateAdjustment(index, { value: { type: 'fixed', value: Number(v) || 0 } })
                "
                label="Значение"
                type="number"
                density="compact"
                hide-details
                style="flex: 1 1 auto"
              />

              <template v-if="adjustment.value?.type === 'characteristic_size'">
                <v-autocomplete
                  :model-value="adjustment.value.characteristic_code"
                  @update:model-value="
                    (v) =>
                      updateAdjustment(index, {
                        value: { type: 'characteristic_size', characteristic_code: v ?? '' },
                      })
                  "
                  :items="characteristics"
                  item-title="name"
                  item-value="code"
                  label="Характеристика"
                  density="compact"
                  hide-details
                  style="flex: 1 1 auto"
                />
              </template>

              <template v-if="adjustment.value?.type === 'characteristic_size_gap'">
                <v-autocomplete
                  :model-value="adjustment.value.characteristic_code_from"
                  @update:model-value="
                    (v) =>
                      updateAdjustment(index, {
                        value: {
                          ...(adjustment.value as Extract<Formula, { type: 'characteristic_size_gap' }>),
                          characteristic_code_from: v ?? '',
                        },
                      })
                  "
                  :items="characteristics"
                  item-title="name"
                  item-value="code"
                  label="От (выше)"
                  density="compact"
                  hide-details
                  style="flex: 1 1 auto"
                />
                <v-autocomplete
                  :model-value="adjustment.value.characteristic_code_to"
                  @update:model-value="
                    (v) =>
                      updateAdjustment(index, {
                        value: {
                          ...(adjustment.value as Extract<Formula, { type: 'characteristic_size_gap' }>),
                          characteristic_code_to: v ?? '',
                        },
                      })
                  "
                  :items="characteristics"
                  item-title="name"
                  item-value="code"
                  label="До (ниже)"
                  density="compact"
                  hide-details
                  style="flex: 1 1 auto"
                />
              </template>

              <v-autocomplete
                :model-value="adjustment.source_code"
                @update:model-value="(v) => updateAdjustment(index, { source_code: v ?? '' })"
                :items="sources"
                item-title="name"
                item-value="code"
                label="Источник"
                density="compact"
                hide-details
                style="flex: 1 1 auto"
              />

              <v-btn icon="mdi-close" variant="text" size="small" @click="removeAdjustment(index)" />
            </div>
            <div v-if="(innerSpec.limit?.adjustments ?? []).length === 0" class="text-body-2 text-medium-emphasis mt-1">
              Условий нет — лимит равен базовому значению.
            </div>
          </div>

          <div class="text-body-2 text-medium-emphasis mt-2">
            Правило описывает определение ресурса. Текущее и максимальное значение хранятся на персонаже.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
