<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import PeriodicityEditor from '@/modules/Roleplay/Rule/Component/Editors/State/PeriodicityEditor.vue';
import DecayEditor from '@/modules/Roleplay/Rule/Component/Editors/State/DecayEditor.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { poisonSpecService } from '@/modules/Roleplay/Rule/Service/Instance/poisonSpecService';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

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
  'update:spec': [value: PoisonSpec];
}>();

const expandedPanels = ref<string[]>(['general', 'poison']);

function poisonFromSpec(value: RuleSpec | null): PoisonSpec {
  if (!value) return poisonSpecService.createEmpty();
  const existing = value as PoisonSpec;

  return {
    icon_code: existing.icon_code ?? null,
    damage_type_code: existing.damage_type_code ?? '',
    default_strength: existing.default_strength,
    default_periodicity: existing.default_periodicity,
    default_decay: existing.default_decay,
  };
}

const innerSpec = ref<PoisonSpec>(poisonFromSpec(props.spec));

const damageTypeOptions = computed(() =>
  ruleReferenceService.damageTypeOptions(props.rules).map((c) => ({ title: c.name, value: c.code })),
);
const characteristicOptions = computed(() =>
  ruleReferenceService.characteristicOptions(props.rules, props.spaceId).map((c) => ({ title: c.name, value: c.code })),
);

const specToEmit = computed<PoisonSpec>(() => ({
  icon_code: innerSpec.value.icon_code,
  damage_type_code: innerSpec.value.damage_type_code,
  default_strength: innerSpec.value.default_strength,
  default_periodicity: innerSpec.value.default_periodicity,
  default_decay: innerSpec.value.default_decay,
}));

watch(specToEmit, (value) => emit('update:spec', value), { deep: true, immediate: true });

function setStrength(value: DimensionalNumberValue | null): void {
  innerSpec.value.default_strength = value ?? undefined;
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

      <v-expansion-panel value="poison">
        <v-expansion-panel-title>Яд</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-text-field
            v-model="innerSpec.icon_code"
            label="Иконка (mdi)"
            placeholder="mdi-skull-outline"
            density="compact"
            hide-details
          />

          <v-row dense class="mt-3">
            <v-col cols="6">
              <v-select
                v-model="innerSpec.damage_type_code"
                :items="damageTypeOptions"
                label="Тип урона яда"
                :rules="[(v) => !!v || 'Обязательное поле']"
                density="compact"
                hide-details="auto"
              />
            </v-col>
          </v-row>

          <div class="text-body-2 font-weight-medium mt-3 mb-1">Сила по умолчанию (урон за тик)</div>
          <DimensionalNumberInput
            :model-value="innerSpec.default_strength ?? null"
            label="Сила"
            @update:model-value="setStrength"
          />

          <div class="text-body-2 font-weight-medium mt-3 mb-1">Периодичность по умолчанию</div>
          <PeriodicityEditor v-model="innerSpec.default_periodicity" />

          <div class="text-body-2 font-weight-medium mt-3 mb-1">Затухание по умолчанию</div>
          <DecayEditor v-model="innerSpec.default_decay" :characteristics="characteristicOptions" />
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
