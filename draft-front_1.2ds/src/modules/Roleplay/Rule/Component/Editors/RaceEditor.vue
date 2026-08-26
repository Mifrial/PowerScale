<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic';
import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';
import type { InheritedAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/InheritedAbilityRef';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import { raceSpecService } from '@/modules/Roleplay/Rule/Service/Instance/raceSpecService';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import RaceCharacteristicsEditor from '@/modules/Roleplay/Rule/Component/Editors/RaceCharacteristicsEditor.vue';
import RaceAbilitiesEditor from '@/modules/Roleplay/Rule/Component/Editors/RaceAbilitiesEditor.vue';
import InheritancePreview from '@/modules/Roleplay/Rule/Component/Editors/InheritancePreview.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
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
  ruleId?: string;
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: RaceSpec];
}>();

const expandedPanels = ref<string[]>(['general', 'race', 'characteristics', 'abilities', 'preview']);

function raceFromSpec(value: RuleSpec | null): RaceSpec {
  if (!value) return raceSpecService.createEmptyRace();
  const loaded = cloneData(value as RaceSpec);

  return {
    parent_race_code: loaded.parent_race_code ?? null,
    cost_os: loaded.cost_os ?? 0,
    characteristics: loaded.characteristics ?? [],
    abilities: loaded.abilities ?? [],
  };
}

const innerSpec = ref<RaceSpec>(raceFromSpec(props.spec));

const spaceRules = computed(() => props.rules);

const speciesOptions = computed(() => ruleReferenceService.speciesOptions(spaceRules.value, props.ruleId));
const characteristicOptions = computed(() =>
  ruleReferenceService.characteristicOptions(spaceRules.value, props.spaceId),
);
const abilityOptions = computed(() => ruleReferenceService.abilityOptions(spaceRules.value));
const abilityNameMap = computed(() => ruleReferenceService.abilityNameMap(spaceRules.value));

/** Параметры «X» способностей (code → параметры), для задания потолка у расы. */
const abilityParameters = computed<Map<string, AbilityParameter[]>>(() => {
  const map = new Map<string, AbilityParameter[]>();
  for (const rule of spaceRules.value) {
    if (rule.type !== 'ability') continue;
    const spec = rule.spec as AbilitySpec | undefined;
    if (!spec || spec.type === 'group') continue;
    if (spec.parameters?.length) map.set(rule.code, spec.parameters);
  }

  return map;
});

const characteristics = computed<RaceCharacteristic[]>({
  get: () => innerSpec.value.characteristics,
  set: (value) => {
    innerSpec.value = { ...innerSpec.value, characteristics: value };
  },
});

const abilities = computed<RaceAbilityRef[]>({
  get: () => innerSpec.value.abilities,
  set: (value) => {
    innerSpec.value = { ...innerSpec.value, abilities: value };
  },
});

const inheritedAbilities = computed<InheritedAbilityRef[]>(() => {
  const byCode = new Map<string, Rule>();
  for (const r of spaceRules.value) byCode.set(r.code, r);

  return raceSpecService.collectInheritedAbilities(innerSpec.value.parent_race_code, byCode);
});

const specToEmit = computed<RaceSpec>(() => cloneData(innerSpec.value));

watch(
  specToEmit,
  (value) => {
    emit('update:spec', value);
  },
  { deep: true, immediate: true },
);
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

      <v-expansion-panel value="race">
        <v-expansion-panel-title>Раса</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-autocomplete
            v-model="innerSpec.parent_race_code"
            :items="speciesOptions"
            item-title="name"
            item-value="code"
            label="Родительский вид/подвид"
            clearable
            density="compact"
            hide-details
            class="mb-2"
          />
          <div class="text-body-2 text-medium-emphasis mb-2">
            Раса — терминальная точка цепочки (Вид → … → Раса). Родитель — всегда вид/подвид.
          </div>
          <ClampedNumberField
            :model-value="innerSpec.cost_os"
            @update:model-value="(v) => (innerSpec = { ...innerSpec, cost_os: v })"
            label="Стоимость (ОС)"
            density="compact"
            hide-details
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Раса тратит ОС из бюджета игры. Стоимость — это цена расы; отрицательная стоимость даёт ОС.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="characteristics">
        <v-expansion-panel-title>Характеристики</v-expansion-panel-title>
        <v-expansion-panel-text>
          <RaceCharacteristicsEditor v-model="characteristics" :characteristics="characteristicOptions" />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="abilities">
        <v-expansion-panel-title>Способности</v-expansion-panel-title>
        <v-expansion-panel-text>
          <RaceAbilitiesEditor
            v-model="abilities"
            :abilities="abilityOptions"
            :ability-parameters="abilityParameters"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="preview">
        <v-expansion-panel-title>Превью наследования</v-expansion-panel-title>
        <v-expansion-panel-text>
          <InheritancePreview :refs="inheritedAbilities" :ability-name-map="abilityNameMap" />
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
