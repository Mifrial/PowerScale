<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { raceSpecService, parameterLimitName } from '@/modules/Roleplay/Rule/init';
import { buildRaceCharacteristicLabels } from '@/modules/Roleplay/Character/Utils/raceCharacteristicLabels';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec';
import DescriptionHtml from '@/modules/Core/UI/Component/DescriptionHtml.vue';

const props = defineProps<{
  build: CharacterBuild;
  rules: Rule[];
  model: CharacterEditorModel;
  draftKey: string | null;
  keywords: Keyword[];
  config: CharacterCreationConfig;
}>();

const draftStore = useCharacterDraftStore();
const confirmDialog = ref(false);
const collapsedKeys = ref<Set<string>>(new Set());

function toggleCollapse(key: string): void {
  const next = new Set(collapsedKeys.value);
  if (next.has(key)) next.delete(key);
  else next.add(key);
  collapsedKeys.value = next;
}

const byCode = computed(() => new Map(props.rules.map((rule) => [rule.code, rule])));

const raceFilterFields: FilterField[] = [{ key: 'name', label: 'Название', type: 'string' }];

type RaceView = { rule: Rule; name: string } & Record<string, unknown>;

const raceViews = computed<RaceView[]>(() =>
  props.rules
    .filter((rule) => rule.type === 'race')
    .map((rule) => {
      return { rule, name: rule.name };
    }),
);

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => raceViews.value,
  fields: raceFilterFields,
  searchFields: ['name'],
});

const allowedRaceIds = computed(() => new Set(filteredRows.value.map((view) => view.rule.code)));

interface TreeRow {
  key: string;
  name: string;
  kind: 'species' | 'race';
  ruleCode: string | null;
  depth: number;
  expandable: boolean;
}

const treeRows = computed<TreeRow[]>(() => {
  const speciesByCode = new Map<string, Rule>();
  const racesByCode = new Map<string, Rule>();
  for (const rule of props.rules) {
    if (rule.type === 'species') speciesByCode.set(rule.code, rule);
    else if (rule.type === 'race') racesByCode.set(rule.code, rule);
  }

  const speciesChildren = (code: string): Rule[] =>
    [...speciesByCode.values()]
      .filter((rule) => (rule.spec as SpeciesSpec | undefined)?.parent_race_code === code)
      .sort((a, b) => a.name.localeCompare(b.name, 'ru'));

  const racesOf = (code: string): Rule[] =>
    [...racesByCode.values()]
      .filter((rule) => (rule.spec as RaceSpec | undefined)?.parent_race_code === code)
      .sort((a, b) => a.name.localeCompare(b.name, 'ru'));

  const rows: TreeRow[] = [];
  const hasVisible = (code: string): boolean => {
    if (racesOf(code).some((race) => allowedRaceIds.value.has(race.code))) return true;

    return speciesChildren(code).some((child) => hasVisible(child.code));
  };
  const walkSpecies = (rule: Rule, depth: number): void => {
    if (!hasVisible(rule.code)) return;
    rows.push({ key: rule.code, name: rule.name, kind: 'species', ruleCode: null, depth, expandable: true });
    if (collapsedKeys.value.has(rule.code)) return;
    for (const race of racesOf(rule.code)) {
      if (allowedRaceIds.value.has(race.code)) {
        rows.push({
          key: race.code,
          name: race.name,
          kind: 'race',
          ruleCode: race.code,
          depth: depth + 1,
          expandable: false,
        });
      }
    }
    for (const child of speciesChildren(rule.code)) walkSpecies(child, depth + 1);
  };

  const orphanRaces = [...racesByCode.values()]
    .filter((rule) => {
      const parent = (rule.spec as RaceSpec | undefined)?.parent_race_code ?? null;

      return (parent === null || !speciesByCode.has(parent)) && allowedRaceIds.value.has(rule.code);
    })
    .sort((a, b) => a.name.localeCompare(b.name, 'ru'));
  for (const race of orphanRaces) {
    rows.push({ key: race.code, name: race.name, kind: 'race', ruleCode: race.code, depth: 0, expandable: false });
  }

  const roots = [...speciesByCode.values()]
    .filter((rule) => {
      const parent = (rule.spec as SpeciesSpec | undefined)?.parent_race_code ?? null;

      return parent === null || !speciesByCode.has(parent);
    })
    .sort((a, b) => a.name.localeCompare(b.name, 'ru'));
  for (const root of roots) walkSpecies(root, 0);

  return rows;
});

const selectedRaceId = ref<string | null>(props.build.raceRuleCode ?? null);

watch(
  treeRows,
  (rows) => {
    const raceIds = rows.filter((row) => row.kind === 'race').map((row) => row.ruleCode);
    // Не сбрасываем молча выбранную расу, если её нет в списке листьев (напр. вид как раса) —
    // показываем её с предупреждением, чтобы пользователь выбрал другую.
    if (selectedRaceId.value === null || !raceIds.includes(selectedRaceId.value)) {
      if (selectedRaceId.value === null || props.build.raceRuleCode === null) {
        selectedRaceId.value = raceIds[0] ?? null;
      }
    }
  },
  { immediate: true },
);

/** Выбранная раса (из черновика) отсутствует среди выбираемых листьев (напр. вид как раса). */
const selectedRaceUnavailable = computed(
  () =>
    props.build.raceRuleCode !== null &&
    !treeRows.value.some((row) => row.kind === 'race' && row.ruleCode === props.build.raceRuleCode),
);

const selectedRule = computed(() =>
  selectedRaceId.value ? props.rules.find((rule) => rule.code === selectedRaceId.value) : null,
);
const selectedSpec = computed(() =>
  selectedRule.value?.type === 'race' ? (selectedRule.value.spec as RaceSpec | undefined) : undefined,
);

const selectedKeywords = computed(() => {
  const ids = selectedRule.value?.keywordIds ?? [];

  return props.keywords.filter((keyword) => ids.includes(keyword.id));
});

const selectedCharacteristics = computed(() => {
  const spec = selectedSpec.value;
  if (!spec) return [];

  return buildRaceCharacteristicLabels(spec, props.rules);
});

interface AbilityEntry {
  name: string;
  inheritedFrom: string | null;
  automatic: boolean;
}

const selectedFreeAbilities = computed<AbilityEntry[]>(() => {
  if (!selectedSpec.value) return [];
  const inherited = selectedSpec.value.parent_race_code
    ? raceSpecService.collectInheritedAbilities(selectedSpec.value.parent_race_code, byCode.value)
    : [];
  const entries: AbilityEntry[] = [];
  for (const ref of selectedSpec.value.abilities ?? []) {
    if (!ref.automatic) continue;
    entries.push({
      name: parameterLimitName(byCode.value.get(ref.ability_code)?.name ?? ref.ability_code, ref.parameters),
      inheritedFrom: null,
      automatic: true,
    });
  }
  for (const ref of inherited) {
    if (!ref.automatic) continue;
    entries.push({
      name: parameterLimitName(byCode.value.get(ref.ability_code)?.name ?? ref.ability_code, ref.parameters),
      inheritedFrom: ref.fromName,
      automatic: true,
    });
  }

  return entries;
});

const selectedAccessAbilities = computed<AbilityEntry[]>(() => {
  if (!selectedSpec.value) return [];
  const inherited = selectedSpec.value.parent_race_code
    ? raceSpecService.collectInheritedAbilities(selectedSpec.value.parent_race_code, byCode.value)
    : [];
  const entries: AbilityEntry[] = [];
  for (const ref of selectedSpec.value.abilities ?? []) {
    if (ref.automatic) continue;
    entries.push({
      name: parameterLimitName(byCode.value.get(ref.ability_code)?.name ?? ref.ability_code, ref.parameters),
      inheritedFrom: null,
      automatic: false,
    });
  }
  for (const ref of inherited) {
    if (ref.automatic) continue;
    entries.push({
      name: parameterLimitName(byCode.value.get(ref.ability_code)?.name ?? ref.ability_code, ref.parameters),
      inheritedFrom: ref.fromName,
      automatic: false,
    });
  }

  return entries;
});

const isSelected = computed(() => props.build.raceRuleCode === selectedRaceId.value);

function chooseRace(): void {
  if (!selectedRaceId.value || isSelected.value) return;
  const hasChoices = props.build.abilities.length > 0 || props.build.characteristicPurchases.length > 0;
  if (hasChoices) {
    confirmDialog.value = true;

    return;
  }
  applyRace(selectedRaceId.value);
}

function confirmRaceChange(): void {
  if (selectedRaceId.value) applyRace(selectedRaceId.value);
  confirmDialog.value = false;
}

function applyRace(ruleCode: string): void {
  const next = characterBuildService.applyRace(props.build, ruleCode, props.rules, props.config, props.keywords);
  draftStore.patchBuild(props.draftKey, {
    raceRuleCode: next.raceRuleCode,
    abilities: next.abilities,
    characteristicPurchases: next.characteristicPurchases,
    inventory: next.inventory,
  });
}
</script>

<template>
  <div class="race-layout">
    <div class="race-tree-column">
      <v-alert v-if="selectedRaceUnavailable" type="warning" variant="tonal" density="compact" class="mb-2">
        Выбранная раса недоступна в списке — выберите другую.
      </v-alert>
      <FilterBar
        :fields="raceFilterFields"
        :model-value="appliedFilters"
        placeholder="Фильтр рас"
        settings-key="character-editor-race"
        :menu-width="'min(700px, calc(100vw - 200px))'"
        @update:model-value="onFilterChange"
      />

      <div class="race-tree">
        <div v-if="treeRows.length === 0" class="text-medium-emphasis pa-3">Расы не найдены.</div>
        <template v-for="row in treeRows" :key="row.key">
          <button
            v-if="row.kind === 'species'"
            class="tree-species"
            :style="{ paddingLeft: `${row.depth * 16 + 8}px` }"
            type="button"
            @click="toggleCollapse(row.key)"
          >
            <v-icon
              :icon="collapsedKeys.has(row.key) ? 'mdi-chevron-right' : 'mdi-chevron-down'"
              size="small"
              class="mr-1"
            />
            {{ row.name }}
          </button>
          <button
            v-else
            class="tree-race"
            :class="{ chosen: build.raceRuleCode === row.ruleCode, selected: selectedRaceId === row.ruleCode }"
            :style="{ paddingLeft: `${row.depth * 16 + 8}px` }"
            type="button"
            @click="selectedRaceId = row.ruleCode"
          >
            {{ row.name }}
          </button>
        </template>
      </div>
    </div>

    <div class="race-card-column">
      <v-card v-if="selectedRule" class="race-card">
        <v-card-text>
          <div class="d-flex align-center ga-3 mb-2">
            <h2 class="text-h6">{{ selectedRule.name }}</h2>
            <v-chip size="x-small" variant="tonal">{{ selectedSpec?.cost_os ?? 0 }} ОС</v-chip>
            <v-spacer />
            <v-btn color="primary" :disabled="isSelected" :variant="isSelected ? 'tonal' : 'flat'" @click="chooseRace">
              {{ isSelected ? 'Выбрана' : 'Выбрать расу' }}
            </v-btn>
          </div>

          <DescriptionHtml :html="selectedRule.description" class="text-body-2 mb-3" />

          <template v-if="selectedCharacteristics.length">
            <div class="text-caption muted-text mb-1">Характеристики</div>
            <div class="d-flex ga-2 flex-wrap mb-3">
              <v-chip
                v-for="characteristic in selectedCharacteristics"
                :key="characteristic.name"
                size="small"
                variant="outlined"
              >
                {{ characteristic.name }} {{ characteristic.label }}
              </v-chip>
            </div>
          </template>

          <template v-if="selectedFreeAbilities.length">
            <div class="text-caption muted-text mb-1">Даёт способности</div>
            <div class="d-flex ga-2 flex-wrap mb-3">
              <v-chip v-for="ability in selectedFreeAbilities" :key="ability.name" size="small" variant="outlined">
                {{ ability.name }}<template v-if="ability.inheritedFrom"> (от {{ ability.inheritedFrom }})</template>
              </v-chip>
            </div>
          </template>

          <template v-if="selectedAccessAbilities.length">
            <div class="text-caption muted-text mb-1">Даёт приобрести способности</div>
            <div class="d-flex ga-2 flex-wrap mb-3">
              <v-chip v-for="ability in selectedAccessAbilities" :key="ability.name" size="small" variant="outlined">
                {{ ability.name }}<template v-if="ability.inheritedFrom"> (от {{ ability.inheritedFrom }})</template>
              </v-chip>
            </div>
          </template>

          <template v-if="selectedKeywords.length">
            <div class="text-caption muted-text mb-1">Признаки</div>
            <div class="d-flex ga-2 flex-wrap">
              <v-chip v-for="keyword in selectedKeywords" :key="keyword.id" size="small" variant="outlined">
                {{ keyword.name }}
              </v-chip>
            </div>
          </template>
        </v-card-text>
      </v-card>
    </div>

    <v-dialog v-model="confirmDialog" max-width="420">
      <v-card>
        <v-card-title>Смена расы</v-card-title>
        <v-card-text>
          При смене расы будут сброшены несовместимые покупки характеристик и способности. Продолжить?
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="confirmDialog = false">Отмена</v-btn>
          <v-btn color="primary" @click="confirmRaceChange">Продолжить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.race-layout {
  display: flex;
  align-items: flex-start;
  gap: 16px;
}

.race-tree-column {
  width: 300px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.race-card-column {
  flex: 1 1 auto;
  min-width: 0;
}

.race-tree {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  height: calc(100vh - 220px);
  min-height: 220px;
  overflow: auto;
  padding: 4px 0;
  margin-bottom: 8px;
}

.race-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  height: calc(100vh - 172px);
}

.tree-species {
  display: flex;
  align-items: center;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  cursor: pointer;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgb(var(--v-theme-on-surface-variant));
  padding-top: 8px;
  padding-bottom: 2px;
  padding-right: 8px;
}

.tree-species:hover {
  color: rgb(var(--v-theme-on-surface));
}

.tree-race {
  display: block;
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  padding: 5px 8px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}

.tree-race:hover {
  background: rgba(var(--v-theme-primary), 0.06);
}

.tree-race.selected {
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
}

.tree-race.chosen {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  font-weight: 600;
}

.tree-race.chosen:hover {
  filter: brightness(1.12);
}

.muted-text {
  color: rgba(var(--v-theme-on-surface), 0.72);
}

@media (max-width: 960px) {
  .race-layout {
    flex-direction: column;
  }

  .race-tree-column {
    width: 100%;
  }

  .race-tree {
    height: 320px;
  }
}
</style>
