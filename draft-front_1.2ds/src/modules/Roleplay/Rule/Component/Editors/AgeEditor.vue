<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { AgeSpec } from '@/modules/Roleplay/Rule/Dto/Age/AgeSpec';
import type { Age } from '@/modules/Roleplay/Rule/Dto/Age/Age';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { ageSpecService } from '@/modules/Roleplay/Rule/Service/Instance/ageSpecService';
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
  'update:spec': [value: AgeSpec];
}>();

const draft = ref<AgeSpec>(ageSpecService.fromRuleSpec(props.spec));
const specToEmit = computed<AgeSpec>(() => cloneData(draft.value));
watch(specToEmit, (value) => emit('update:spec', value), { deep: true, immediate: true });

const characteristics = computed(() => ruleReferenceService.characteristicOptions(props.rules, props.spaceId));

function addStage(): void {
  draft.value.ages = [...draft.value.ages, ageSpecService.createEmptyStage()];
}

function removeStage(index: number): void {
  if (draft.value.ages.length <= 1) return;
  draft.value.ages = draft.value.ages.filter((_, i) => i !== index);
}

function patchStage(index: number, patch: Partial<Age>): void {
  draft.value.ages = draft.value.ages.map((stage, i) => (i === index ? { ...stage, ...patch } : stage));
}

function addEffect(stageIndex: number): void {
  const stage = draft.value.ages[stageIndex];
  if (!stage) return;
  patchStage(stageIndex, { effects: [...stage.effects, ageSpecService.createEmptyEffect()] });
}

function removeEffect(stageIndex: number, effectIndex: number): void {
  const stage = draft.value.ages[stageIndex];
  if (!stage) return;
  patchStage(stageIndex, { effects: stage.effects.filter((_, i) => i !== effectIndex) });
}

function patchEffect(
  stageIndex: number,
  effectIndex: number,
  patch: { characteristic_code?: string; delta?: number; scope?: string | null },
): void {
  const stage = draft.value.ages[stageIndex];
  if (!stage) return;
  patchStage(stageIndex, {
    effects: stage.effects.map((effect, i) => (i === effectIndex ? { ...effect, ...patch } : effect)),
  });
}
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
      <div class="text-subtitle-2 mb-2">Возрастные ступени</div>
      <div v-for="(stage, stageIndex) in draft.ages" :key="stageIndex" class="mb-4 pa-3 age-stage">
        <div class="d-flex ga-2 align-center mb-2">
          <v-text-field
            :model-value="stage.name"
            @update:model-value="(v) => patchStage(stageIndex, { name: v ?? '' })"
            label="Ступень"
            :rules="[(v) => !!v?.trim() || 'Обязательное поле']"
            density="compact"
            hide-details="auto"
            class="flex-grow-1"
          />
          <ClampedNumberField
            :model-value="stage.ol"
            @update:model-value="(v: number) => patchStage(stageIndex, { ol: v })"
            label="ОЛ"
            :min="0"
            density="compact"
            hide-details
            style="max-width: 100px"
          />
          <ClampedNumberField
            :model-value="stage.featureLimit"
            @update:model-value="(v: number) => patchStage(stageIndex, { featureLimit: v })"
            label="Лимит особенностей"
            :min="0"
            density="compact"
            hide-details
            style="max-width: 160px"
          />
          <v-btn
            icon
            size="small"
            color="error"
            variant="text"
            :disabled="draft.ages.length <= 1"
            @click="removeStage(stageIndex)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>
        <div v-for="(effect, effectIndex) in stage.effects" :key="effectIndex" class="d-flex ga-2 align-center mb-1">
          <v-autocomplete
            :model-value="effect.characteristic_code"
            @update:model-value="(v) => patchEffect(stageIndex, effectIndex, { characteristic_code: v ?? '' })"
            :items="characteristics"
            item-title="name"
            item-value="code"
            label="Характеристика"
            density="compact"
            hide-details
            class="flex-grow-1"
          />
          <ClampedNumberField
            :model-value="effect.delta"
            @update:model-value="(v: number) => patchEffect(stageIndex, effectIndex, { delta: v })"
            label="Смещение"
            density="compact"
            hide-details
            style="max-width: 120px"
          />
          <v-text-field
            :model-value="effect.scope ?? ''"
            @update:model-value="(v) => patchEffect(stageIndex, effectIndex, { scope: v || null })"
            label="Условие (опционально)"
            density="compact"
            hide-details
            style="flex: 1 1 180px"
          />
          <v-btn icon size="small" variant="text" @click="removeEffect(stageIndex, effectIndex)">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </div>
        <v-btn variant="text" color="primary" size="small" @click="addEffect(stageIndex)">
          <v-icon start>mdi-plus</v-icon>
          Эффект
        </v-btn>
      </div>
      <v-btn variant="text" color="primary" size="small" @click="addStage">
        <v-icon start>mdi-plus</v-icon>
        Добавить ступень
      </v-btn>
    </template>
  </RuleEditorBase>
</template>

<style scoped>
.age-stage {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
