<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { ethnicityPickService } from '@/modules/Roleplay/Rule/init';
import { ETHNICITY_COMBO_MENU_PROPS } from '@/modules/Roleplay/Character/Constant/Ethnicity/ETHNICITY_COMBO_MENU_PROPS';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  build: CharacterBuild;
  rules: Rule[];
  draftKey: string | null;
}>();

const draftStore = useCharacterDraftStore();
const ethnicityLocal = ref<string | null>(null);
const nativeLocal = ref<string | null>(null);
const ethnicityMenu = ref(false);
const nativeMenu = ref(false);

const ethnicityOptions = computed(() =>
  ethnicityPickService.ethnicityOptions(props.rules, props.build.raceRuleCode ?? null),
);
const nativeOptions = computed(() =>
  ethnicityPickService.nativeLanguageOptions(props.rules, props.build.ethnicityCode ?? null),
);
const ethnicityItems = computed(() => ethnicityPickService.comboItems(ethnicityOptions.value));
const nativeItems = computed(() => ethnicityPickService.comboItems(nativeOptions.value));
const committedEthnicity = computed(() =>
  ethnicityPickService.displayLabel(
    props.build.ethnicityCode ?? null,
    props.build.ethnicityText ?? null,
    ethnicityOptions.value,
  ),
);
const committedNative = computed(() =>
  ethnicityPickService.displayLabel(
    props.build.nativeLanguageCode ?? null,
    props.build.nativeLanguageText ?? null,
    nativeOptions.value,
  ),
);

watch(
  committedEthnicity,
  (value) => {
    if (ethnicityMenu.value) return;
    ethnicityLocal.value = value;
  },
  { immediate: true },
);
watch(
  committedNative,
  (value) => {
    if (nativeMenu.value) return;
    nativeLocal.value = value;
  },
  { immediate: true },
);

function aliasesOf(option: { code: string; name: string }): string[] {
  return [option.code, option.name];
}

function isCatalogOrClear(raw: unknown, options: readonly { code: string; name: string }[]): boolean {
  if (raw == null || raw === '') return true;

  return ethnicityPickService.matchOption(raw, options, aliasesOf).code != null;
}

function isUnchangedEthnicity(code: string | null, text: string | null): boolean {
  return (
    (code ?? null) === (props.build.ethnicityCode ?? null) && (text ?? null) === (props.build.ethnicityText ?? null)
  );
}

function isUnchangedNative(code: string | null, text: string | null): boolean {
  return (
    (code ?? null) === (props.build.nativeLanguageCode ?? null) &&
    (text ?? null) === (props.build.nativeLanguageText ?? null)
  );
}

function patchEthnicity(raw: unknown): void {
  const picked = ethnicityPickService.matchOption(raw, ethnicityOptions.value, aliasesOf);
  if (isUnchangedEthnicity(picked.code, picked.text)) return;
  const next = characterBuildService.setEthnicity(props.build, picked.code, picked.text, props.rules);
  ethnicityLocal.value = ethnicityPickService.displayLabel(
    next.ethnicityCode ?? null,
    next.ethnicityText ?? null,
    ethnicityOptions.value,
  );
  draftStore.patchBuild(props.draftKey, {
    ethnicityCode: next.ethnicityCode,
    ethnicityText: next.ethnicityText,
  });
}

function patchNative(raw: unknown): void {
  const picked = ethnicityPickService.matchOption(raw, nativeOptions.value, aliasesOf);
  if (isUnchangedNative(picked.code, picked.text)) return;
  const next = characterBuildService.setNativeLanguage(props.build, picked.code, picked.text, props.rules);
  nativeLocal.value = ethnicityPickService.displayLabel(
    next.nativeLanguageCode ?? null,
    next.nativeLanguageText ?? null,
    nativeOptions.value,
  );
  draftStore.patchBuild(props.draftKey, {
    nativeLanguageCode: next.nativeLanguageCode,
    nativeLanguageText: next.nativeLanguageText,
    abilities: next.abilities,
  });
}

function onEthnicityUpdate(raw: unknown): void {
  if (raw === '') return;
  if (isCatalogOrClear(raw, ethnicityOptions.value)) {
    patchEthnicity(raw);

    return;
  }
  ethnicityLocal.value = ethnicityPickService.matchOption(raw, ethnicityOptions.value, aliasesOf).text;
}

function onNativeUpdate(raw: unknown): void {
  if (raw === '') return;
  if (isCatalogOrClear(raw, nativeOptions.value)) {
    patchNative(raw);

    return;
  }
  nativeLocal.value = ethnicityPickService.matchOption(raw, nativeOptions.value, aliasesOf).text;
}

function onEthnicityBlur(): void {
  void nextTick(() => {
    if (ethnicityMenu.value) return;
    patchEthnicity(ethnicityLocal.value);
  });
}

function onNativeBlur(): void {
  void nextTick(() => {
    if (nativeMenu.value) return;
    patchNative(nativeLocal.value);
  });
}

function filterCombo(
  _value: string,
  query: string,
  item: { raw?: { title?: string; subtitle?: string; type?: string } },
): boolean {
  if (item.raw?.type === 'divider') return true;

  return ethnicityPickService.matchesQuery(item.raw ?? {}, query);
}
</script>

<template>
  <div>
    <v-combobox
      :model-value="ethnicityLocal"
      :items="ethnicityItems"
      item-title="title"
      item-value="title"
      label="Народность"
      hint="Рекомендуемые для расы сверху. Своя строка — без дерева."
      persistent-hint
      density="compact"
      clearable
      :auto-select-first="false"
      :custom-filter="filterCombo"
      :menu-props="ETHNICITY_COMBO_MENU_PROPS"
      class="mb-3"
      @update:model-value="onEthnicityUpdate"
      @update:menu="ethnicityMenu = $event"
      @blur="onEthnicityBlur"
    >
      <template #item="{ props: itemProps, item }">
        <v-divider v-if="item.raw.type === 'divider'" />
        <v-list-item v-else v-bind="itemProps" :subtitle="item.raw.subtitle" lines="three" />
      </template>
    </v-combobox>
    <v-combobox
      :model-value="nativeLocal"
      :items="nativeItems"
      item-title="title"
      item-value="title"
      label="Родной язык"
      hint="Языки народа сверху. Дар владения 2, письменность не выдаётся."
      persistent-hint
      density="compact"
      clearable
      :auto-select-first="false"
      :custom-filter="filterCombo"
      :menu-props="ETHNICITY_COMBO_MENU_PROPS"
      @update:model-value="onNativeUpdate"
      @update:menu="nativeMenu = $event"
      @blur="onNativeBlur"
    >
      <template #item="{ props: itemProps, item }">
        <v-divider v-if="item.raw.type === 'divider'" />
        <v-list-item v-else v-bind="itemProps" :subtitle="item.raw.subtitle" lines="three" />
      </template>
    </v-combobox>
  </div>
</template>

<style>
.ethnicity-native-menu {
  max-width: min(44rem, calc(100vw - 2rem)) !important;
}
.ethnicity-native-menu .v-list-item-title {
  white-space: normal;
  overflow-wrap: anywhere;
}
.ethnicity-native-menu .v-list-item-subtitle {
  display: block;
  white-space: normal;
  overflow: visible;
  text-overflow: unset;
  -webkit-line-clamp: unset;
  overflow-wrap: anywhere;
}
</style>
