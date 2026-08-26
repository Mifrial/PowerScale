<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { CHARACTERISTIC_FORMULA_TYPES } from '@/modules/Roleplay/Rule/Constant/CHARACTERISTIC_FORMULA_TYPES';
import { CHARACTERISTIC_GROUPS } from '@/modules/Roleplay/Rule/Constant/CHARACTERISTIC_GROUPS';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

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
  'update:spec': [value: CharacteristicSpec];
}>();

function parseFormula(formula: string | null | undefined): {
  hasFormula: boolean;
  formulaType: 'min' | 'max';
  formulaChar1: string | null;
  formulaChar2: string | null;
} {
  if (!formula) {
    return { hasFormula: false, formulaType: 'min', formulaChar1: null, formulaChar2: null };
  }
  const match = formula.match(/(min|max)\(([^,]+),\s*([^)]+)\)/);
  if (!match) {
    return { hasFormula: true, formulaType: 'min', formulaChar1: null, formulaChar2: null };
  }

  return {
    hasFormula: true,
    formulaType: match[1] as 'min' | 'max',
    formulaChar1: match[2].trim(),
    formulaChar2: match[3].trim(),
  };
}

function fromSpec(value: RuleSpec | null): {
  group: CharacteristicGroup;
  hasFormula: boolean;
  formulaType: 'min' | 'max';
  formulaChar1: string | null;
  formulaChar2: string | null;
  automatic: boolean;
  customAutomatic: boolean;
  automaticValue: DimensionalNumberValue;
  useBaseFrom: boolean;
  baseFromCode: string | null;
  baseFromSources: string[];
} {
  const spec = value && 'type' in value && value.type === 'characteristic' ? (value as CharacteristicSpec) : null;
  const parsed = parseFormula(spec?.formula);
  const rawAutomatic = spec?.automatic;
  const custom = typeof rawAutomatic === 'object' && rawAutomatic !== null;

  return {
    group: spec?.group ?? 'primary',
    ...parsed,
    automatic: Boolean(rawAutomatic),
    customAutomatic: custom,
    automaticValue: custom ? cloneData(rawAutomatic.value) : { base: 3, size: 0 },
    useBaseFrom: Boolean(spec?.base_from?.characteristic_code),
    baseFromCode: spec?.base_from?.characteristic_code ?? null,
    baseFromSources: spec?.base_from?.source_codes ?? [],
  };
}

const loaded = fromSpec(props.spec);
const group = ref(loaded.group);
const hasFormula = ref(loaded.hasFormula);
const formulaType = ref(loaded.formulaType);
const formulaChar1 = ref(loaded.formulaChar1);
const formulaChar2 = ref(loaded.formulaChar2);
const automatic = ref(loaded.automatic);
const customAutomatic = ref(loaded.customAutomatic);
const automaticValue = ref(loaded.automaticValue);
const useBaseFrom = ref(loaded.useBaseFrom);
const baseFromCode = ref(loaded.baseFromCode);
const baseFromSources = ref(loaded.baseFromSources);

const availableCharacteristics = computed(() => {
  return props.rules.filter(
    (rule: Rule) =>
      rule.type === 'characteristic' &&
      rule.spaceId === props.spaceId &&
      !(rule.spec as CharacteristicSpec | undefined)?.formula,
  );
});

const sources = computed(() => ruleReferenceService.sourceOptions(props.rules));

const innerFormula = computed(() => {
  if (hasFormula.value && formulaChar1.value && formulaChar2.value) {
    const func = formulaType.value === 'min' ? 'min' : 'max';

    return `${func}(${formulaChar1.value}, ${formulaChar2.value})`;
  }

  return null;
});

const specToEmit = computed<CharacteristicSpec>(() => {
  const spec: CharacteristicSpec = {
    type: 'characteristic',
    formula: hasFormula.value ? innerFormula.value : null,
    group: group.value,
  };
  if (automatic.value) {
    spec.automatic = customAutomatic.value ? { value: cloneData(automaticValue.value) } : true;
  }
  if (useBaseFrom.value && baseFromCode.value) {
    spec.base_from = { characteristic_code: baseFromCode.value, source_codes: [...baseFromSources.value] };
  }

  return spec;
});

watch(specToEmit, (value) => emit('update:spec', cloneData(value)), { deep: true, immediate: true });
</script>

<template>
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
    <template #spec>
      <v-card class="mt-4">
        <v-card-title>Характеристика</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis mt-2">Характеристика всегда размерная с диапазоном 3-5</div>
          <v-select
            v-model="group"
            :items="CHARACTERISTIC_GROUPS"
            item-title="title"
            item-value="value"
            label="Группа на карточке"
            density="compact"
            hide-details="auto"
            class="mt-2"
          />
          <v-switch v-model="hasFormula" label="Производная характеристика" color="primary" hide-details class="mt-2" />
          <v-switch v-model="automatic" label="Автоматическое получение" color="primary" hide-details class="mt-2" />
          <v-switch
            v-if="automatic"
            v-model="customAutomatic"
            label="Нестандартная база (не 3 средних)"
            color="primary"
            hide-details
            class="mt-2"
          />
          <DimensionalNumberInput
            v-if="automatic && customAutomatic"
            v-model="automaticValue"
            label="База"
            :min="3"
            :max="5"
            class="mt-2"
          />
          <v-switch
            v-model="useBaseFrom"
            label="База из другой характеристики"
            color="primary"
            hide-details
            class="mt-2"
          />
          <div v-if="useBaseFrom" class="mt-2">
            <v-autocomplete
              v-model="baseFromCode"
              :items="availableCharacteristics"
              item-title="name"
              item-value="code"
              label="Характеристика-источник"
              variant="outlined"
              density="compact"
              clearable
            />
            <v-select
              v-model="baseFromSources"
              :items="sources"
              item-title="name"
              item-value="code"
              label="Источники модификаторов"
              multiple
              chips
              closable-chips
              density="compact"
              hide-details="auto"
              class="mt-2"
            />
          </div>
          <div v-if="hasFormula" class="mt-2">
            <v-select
              v-model="formulaType"
              :items="CHARACTERISTIC_FORMULA_TYPES"
              item-title="label"
              item-value="value"
              label="Формула"
              variant="outlined"
              density="compact"
            />
            <div class="d-flex gap-2 mt-2">
              <v-autocomplete
                v-model="formulaChar1"
                :items="availableCharacteristics"
                item-title="name"
                item-value="code"
                label="Первая характеристика"
                variant="outlined"
                density="compact"
                clearable
                class="flex-grow-1"
              />
              <v-autocomplete
                v-model="formulaChar2"
                :items="availableCharacteristics"
                item-title="name"
                item-value="code"
                label="Вторая характеристика"
                variant="outlined"
                density="compact"
                clearable
                class="flex-grow-1"
              />
            </div>
          </div>
        </v-card-text>
      </v-card>
    </template>
  </RuleEditorBase>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
