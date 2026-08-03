<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import { raceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';

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
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: SpeciesSpec];
}>();

const revisionStore = useSpaceRevisionStore();

const expandedPanels = ref<string[]>(['general', 'parent', 'abilities']);
const innerSpec = ref<SpeciesSpec>(raceSpecService.createEmptySpecies());

const spaceRules = computed(() => revisionStore.effectiveRules);

const speciesOptions = computed(() =>
  spaceRules.value
    .filter((r: Rule) => r.type === 'species' && r.id !== props.ruleId)
    .map((r) => ({ code: r.code, name: r.name })),
);

const abilityOptions = computed(() =>
  spaceRules.value.filter((r: Rule) => r.type === 'ability').map((r) => ({ code: r.code, name: r.name })),
);

function updateAbility(index: number, key: 'ability_code' | 'automatic', value: string | boolean) {
  const abilities = innerSpec.value.abilities.map((a, i) => (i === index ? { ...a, [key]: value } : a));
  innerSpec.value = { ...innerSpec.value, abilities };
}

function removeAbility(index: number) {
  innerSpec.value = {
    ...innerSpec.value,
    abilities: innerSpec.value.abilities.filter((_, i) => i !== index),
  };
}

function addAbility() {
  innerSpec.value = {
    ...innerSpec.value,
    abilities: [...innerSpec.value.abilities, { ability_code: '', automatic: false }],
  };
}

const specToEmit = computed<SpeciesSpec>(() => structuredClone(innerSpec.value));

watch(
  specToEmit,
  (value) => {
    emit('update:spec', value);
  },
  { deep: true },
);

onMounted(() => {
  if (props.spec) {
    const loaded = structuredClone(props.spec as SpeciesSpec);
    innerSpec.value = {
      parent_race_code: loaded.parent_race_code ?? null,
      abilities: loaded.abilities ?? [],
    };
  }
});
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

      <v-expansion-panel value="parent">
        <v-expansion-panel-title>Родитель</v-expansion-panel-title>
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
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Вид — корневой узел без ссылки (пусто). Подвид — с ссылкой на родителя.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="abilities">
        <v-expansion-panel-title>Способности</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Способности вида/подвида наследуются расами цепочки (вид → подвид → раса).
          </div>
          <div v-for="(ref, index) in innerSpec.abilities" :key="index" class="d-flex ga-2 align-center mb-1">
            <v-autocomplete
              :model-value="ref.ability_code"
              @update:model-value="(v) => updateAbility(index, 'ability_code', v)"
              :items="abilityOptions"
              item-title="name"
              item-value="code"
              label="Способность"
              density="compact"
              hide-details
              class="flex-grow-1"
            />
            <v-switch
              :model-value="ref.automatic"
              @update:model-value="(v) => updateAbility(index, 'automatic', !!v)"
              label="Бесплатная"
              density="compact"
              hide-details
            />
            <v-btn icon size="small" color="error" variant="text" @click="removeAbility(index)">
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </div>
          <v-btn variant="text" color="primary" size="small" @click="addAbility">
            <v-icon start>mdi-plus</v-icon>
            Добавить способность
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
