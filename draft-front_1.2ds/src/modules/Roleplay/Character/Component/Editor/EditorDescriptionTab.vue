<script setup lang="ts">
import { computed } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';

const props = defineProps<{
  build: CharacterBuild;
  draftKey: string | null;
  model: CharacterEditorModel | null;
}>();

const draftStore = useCharacterDraftStore();

/** Текущая ступень возраста по числу лет (из сводки «Личности»). */
const ageStep = computed(() => {
  const personality = props.model?.personality;
  if (!personality) return null;

  return personality.ageScale.find((step) => step.name === personality.ageName) ?? null;
});

/** Значение поля «Число лет»: текущее число лет или минимум выбранной ступени. */
const ageYearsValue = computed(() => props.build.ageYears ?? ageStep.value?.min ?? 0);

function patch(patch: Partial<CharacterBuild>): void {
  draftStore.patchBuild(props.draftKey, patch);
}
</script>

<template>
  <v-card>
    <v-card-text>
      <div class="d-flex align-center ga-4 mb-4">
        <v-text-field
          :model-value="build.name"
          label="Имя персонажа"
          placeholder="Введите имя"
          class="flex-grow-1"
          @update:model-value="(v) => patch({ name: String(v) })"
        />
        <div v-if="ageStep" class="d-flex align-center ga-3">
          <ClampedNumberField
            :model-value="ageYearsValue"
            :min="ageStep.min"
            :max="ageStep.max ?? undefined"
            label="Число лет"
            min-width="125px"
            density="compact"
            control-variant="stacked"
            hide-details
            @update:model-value="(v) => patch({ ageYears: v })"
          />
          <span class="text-body-2 text-medium-emphasis">
            Ступень: {{ ageStep.name }} ({{
              ageStep.min === 0
                ? `до ${ageStep.max}`
                : ageStep.max === null
                  ? `от ${ageStep.min}`
                  : `от ${ageStep.min} до ${ageStep.max}`
            }})
          </span>
        </div>
      </div>
      <div v-if="!ageStep" class="text-body-2 text-medium-emphasis mb-4">Выберите возраст на этапе «Личность».</div>
      <v-text-field
        :model-value="build.shortDescription"
        label="Краткое описание"
        placeholder="Кратко о персонаже"
        class="mb-4"
        @update:model-value="(v) => patch({ shortDescription: v ? String(v) : null })"
      />
      <v-textarea
        :model-value="build.fullDescription"
        label="Полное описание"
        rows="6"
        @update:model-value="(v) => patch({ fullDescription: v ? String(v) : null })"
      />
    </v-card-text>
  </v-card>
</template>
