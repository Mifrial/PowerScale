<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import { CHARACTERISTIC_FORMULA_TYPES } from '@/modules/Roleplay/Rule/Constant/CHARACTERISTIC_FORMULA_TYPES';

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
  'update:spec': [value: CharacteristicSpec | null];
}>();

const allRules = computed(() => props.rules);

const hasFormula = ref(false);
const formulaType = ref<'min' | 'max'>('min');
const formulaChar1 = ref<string | null>(null);
const formulaChar2 = ref<string | null>(null);
const automatic = ref(false);
/** Value-форма automatic (нестандартная база, напр. {3|-1}); пусто — база по умолчанию 3 средних. */
const automaticValue = ref<{ value: { base: number; size: number } } | undefined>(undefined);

const availableCharacteristics = computed(() => {
  return allRules.value.filter(
    (rule: Rule) =>
      rule.type === 'characteristic' &&
      rule.spaceId === props.spaceId &&
      !(rule.spec as CharacteristicSpec | undefined)?.formula,
  );
});

const updateFormula = () => {
  const spec: CharacteristicSpec = {
    type: 'characteristic',
    formula: hasFormula.value ? innerFormula.value : null,
    automatic: automatic.value ? (automaticValue.value ?? true) : undefined,
  };
  emit('update:spec', spec);
};

watch([hasFormula, formulaType, formulaChar1, formulaChar2, automatic], updateFormula);

onMounted(() => {
  if (props.spec) {
    const characteristicSpec = props.spec as CharacteristicSpec | null;
    const formula = characteristicSpec?.formula || null;
    const rawAutomatic = characteristicSpec?.automatic;
    automatic.value = Boolean(rawAutomatic);
    if (typeof rawAutomatic === 'object') automaticValue.value = rawAutomatic;
    if (formula) {
      hasFormula.value = true;
      const match = formula.match(/(min|max)\(([^,]+),\s*([^)]+)\)/);
      if (match) {
        formulaType.value = match[1] as 'min' | 'max';
        formulaChar1.value = match[2].trim();
        formulaChar2.value = match[3].trim();
      }
    }
  }
  emit('update:spec', {
    type: 'characteristic',
    formula: hasFormula.value ? innerFormula.value : null,
    automatic: automatic.value ? (automaticValue.value ?? true) : undefined,
  });
});

const innerFormula = computed(() => {
  if (hasFormula.value && formulaChar1.value && formulaChar2.value) {
    const func = formulaType.value === 'min' ? 'min' : 'max';

    return `${func}(${formulaChar1.value}, ${formulaChar2.value})`;
  }

  return null;
});
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

          <v-switch v-model="hasFormula" label="Производная характеристика" color="primary" hide-details class="mt-2" />
          <v-switch
            v-model="automatic"
            label="Автоматическое получение (база 3 средних, если раса не задала иное)"
            color="primary"
            hide-details
            class="mt-2"
          />
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
