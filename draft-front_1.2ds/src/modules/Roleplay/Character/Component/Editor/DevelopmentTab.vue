<script setup lang="ts">
import { computed, ref } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import DevelopmentAbilityNode from '@/modules/Roleplay/Character/Component/Editor/DevelopmentAbilityNode.vue';
import {
  isAcquiredAbility,
  isAttackAbility,
  isPhysicalDevelopmentAbility,
} from '@/modules/Roleplay/Character/Utils/developmentCategory';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import { ABILITY_SECTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SECTIONS';
import { abilitySectionService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySectionService';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  build: CharacterBuild;
  model: CharacterEditorModel;
  draftKey: string | null;
  keywords: Keyword[];
  rules: Rule[];
}>();

const draftStore = useCharacterDraftStore();

type DevelopmentCategory = 'all' | 'attack' | 'physical' | 'weapons' | 'shields';

const categoryFilter = ref<DevelopmentCategory>('all');
const availableOnly = ref(false);
const acquiredOnly = ref(false);
/** Раскрытые панели навыков (переживают ремаунты строк виртуализации). */
const openSet = ref<Set<string>>(new Set());

const keywordCodeById = computed(() => new Map(props.keywords.map((keyword) => [keyword.id, keyword.code])));

const SECTIONS = ABILITY_SECTIONS.map((section) => section.code);

function sectionOf(ability: EditorAbility): string | null {
  const rule = props.rules.find((entry) => entry.id === ability.ruleId);
  const fromSpec = abilitySectionService.fromSpec(rule?.spec as AbilitySpecBase | undefined);
  if (fromSpec) return fromSpec;
  const keywordCodes = ability.keywordIds
    .map((keywordId) => keywordCodeById.value.get(keywordId))
    .filter((code): code is string => Boolean(code));
  const fromKeywords = abilitySectionService.fromKeywordCodes(keywordCodes);
  if (fromKeywords) return fromKeywords;

  return skillSectionByRequirements(ability.ruleId);
}

function skillSectionByRequirements(ruleId: string): 'weapon-skill' | 'shield-skill' | null {
  const rule = props.rules.find((r) => r.id === ruleId);
  if (!rule?.spec) return null;
  const spec = rule.spec as { requirements?: unknown[] };
  const requirements = spec.requirements;
  if (!Array.isArray(requirements)) return null;
  for (const req of requirements) {
    const inner = (req as { requirements?: unknown[] } | undefined)?.requirements;
    if (!Array.isArray(inner)) continue;
    for (const r of inner) {
      const type = (r as { type?: string } | undefined)?.type;
      if (type === 'min_weapon_mastery') {
        const keywordCode = (r as { keyword_code?: string } | undefined)?.keyword_code ?? '';
        if (keywordCode.includes('shield')) return 'shield-skill';

        return 'weapon-skill';
      }
    }
  }

  return null;
}

/** Покупаемые за ОР (зона `or`) + информационные (агрегаты/производные) каталога «Развития».
 *  Способность входит во вкладку, если у неё есть зона `or` (даже пустая у агрегатов/производных)
 *  или она получена даром-навыком особенности. Легаси-способности старого мока (spell/process
 *  вне каталога) сюда не попадают. Строки дополняются полем `section` для фильтра «Раздел»
 *  (вкл. «Навыки оружия/щитов»). */
const devAbilities = computed<(EditorAbility & { section: string | null })[]>(() => {
  const result: (EditorAbility & { section: string | null })[] = [];
  for (const ability of props.model.abilities) {
    const section = sectionOf(ability);
    const inOrZone = ability.zones.some((zone) => zone.zoneCode === 'or');
    if (inOrZone || ability.gifted) {
      result.push({ ...ability, section });
    }
  }

  return result;
});

const abilityFilterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  {
    key: 'section',
    label: 'Раздел',
    type: 'select',
    options: SECTIONS.map((code) => ({ label: abilitySectionService.label(code), value: code })),
  },
];

const {
  filteredRows: abilityRows,
  appliedFilters,
  onFilterChange,
} = useFilteredRows({
  getItems: () => devAbilities.value as unknown as Record<string, unknown>[],
  fields: abilityFilterFields,
  searchFields: ['name'],
});

/**
 * Строки после фильтра поиска/раздела. При поиске улучшения в результат включаются и его
 * родители (по привязке), чтобы дерево раскрывалось внутри родителя, а не «сиротой» сверху.
 */
const abilitiesByName = computed<(EditorAbility & { section: string | null })[]>(() => {
  const rows = abilityRows.value as unknown as (EditorAbility & {
    section: string | null;
  })[];
  const byCode = new Map(devAbilities.value.map((ability) => [ability.code, ability]));
  const result: (EditorAbility & { section: string | null })[] = [];
  const seen = new Set<string>();
  for (const row of rows) {
    if (seen.has(row.ruleId)) continue;
    seen.add(row.ruleId);
    result.push(row);
    let node = row.parentCode ? (byCode.get(row.parentCode) ?? null) : null;
    while (node) {
      if (seen.has(node.ruleId)) break;
      seen.add(node.ruleId);
      result.push(node);
      node = node.parentCode ? (byCode.get(node.parentCode) ?? null) : null;
    }
  }

  return result;
});

/** Фильтр по категории (Все/Атаки/Физич./Оружие/Щиты) и чипам; раздел — в фильтр-баре. */
function passesFilters(ability: EditorAbility): boolean {
  if (categoryFilter.value === 'attack' && !isAttackAbility(ability)) return false;
  if (categoryFilter.value === 'physical' && !isPhysicalDevelopmentAbility(ability)) return false;
  if (availableOnly.value && !(ability.levels[0]?.met ?? false)) return false;
  if (acquiredOnly.value && !isAcquiredAbility(ability)) return false;

  return true;
}

const allAbilities = computed(() => abilitiesByName.value.filter(passesFilters));

/** Дерево улучшений: «код способности → её улучшения» (рекурсивно для цепочек улучшений). */
const childrenByCode = computed(() => {
  const map = new Map<string, EditorAbility[]>();
  for (const ability of allAbilities.value) {
    if (!ability.parentCode) continue;
    const list = map.get(ability.parentCode) ?? [];
    list.push(ability);
    map.set(ability.parentCode, list);
  }

  return map;
});

/**
 * Верхний уровень дерева: способности без родителя. Сироты-улучшения (родитель отфильтрован
 * или отсутствует) тоже попадают наверх, чтобы не терялись при поиске/фильтрах.
 */
const rootAbilities = computed(() =>
  allAbilities.value.filter((ability) => !ability.parentCode || !childrenByCode.value.has(ability.parentCode)),
);

/**
 * Авто-раскрытие родителей при поиске: строка, совпавшая по имени, показывается внутри
 * раскрытого родителя (оверлей к openSet, не сохраняется при смене поиска).
 */
const autoOpenSet = computed(() => {
  const result = new Set<string>();
  const q = typeof appliedFilters.value.q === 'string' ? appliedFilters.value.q.trim().toLowerCase() : '';
  if (!q) return result;

  const byCode = new Map(allAbilities.value.map((ability) => [ability.code, ability]));
  for (const ability of allAbilities.value) {
    if (!ability.name.toLowerCase().includes(q)) continue;
    let node: EditorAbility | undefined = ability;
    while (node.parentCode) {
      node = byCode.get(node.parentCode);
      if (!node) break;
      result.add(node.ruleId);
    }
  }

  return result;
});

/** Раскрытые панели: пользовательские + авто-раскрытие родителей по поиску. */
const effectiveOpenSet = computed(() => new Set([...openSet.value, ...autoOpenSet.value]));

/** Получено от особенностей (дары-навыки D100): не покупаются, не снимаются. */
const giftedAbilities = computed(() =>
  abilitiesByName.value.filter((ability) => ability.gifted && passesFilters(ability)),
);

/** Сброс скролла каталога наверх при смене любого фильтра. */
const resetKey = computed(() =>
  JSON.stringify([
    appliedFilters.value.section ?? '',
    appliedFilters.value.q ?? '',
    categoryFilter.value,
    availableOnly.value,
    acquiredOnly.value,
  ]),
);

/** Высота скролл-области каталога: почти весь вьюпорт под шапкой/фильтрами. */
const catalogHeight = 'calc(100vh - 295px)';

function setOpen(ruleId: string, open: boolean): void {
  if (open) openSet.value.add(ruleId);
  else openSet.value.delete(ruleId);
}

function abilityKey(ability: EditorAbility): string {
  return ability.ruleId;
}

function setLevel(ruleId: string, level: number): void {
  const next = characterBuildService.setAbilityLevel(props.build, ruleId, level, props.rules, { zone: 'or' });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setParameter(ruleId: string, code: string, value: number | { base: number; size: number }): void {
  const next = characterBuildService.setAbilityParameter(props.build, ruleId, code, value, props.rules);
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function addInstance(ruleId: string, domain: string, domainCode: string | null): void {
  const next = characterBuildService.addAbilityInstance(props.build, ruleId, domain, props.rules, {
    zone: 'or',
    domainCode,
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setInstanceLevel(ruleId: string, domain: string, level: number): void {
  const next = characterBuildService.setAbilityInstanceLevel(props.build, ruleId, domain, level, props.rules, {
    zone: 'or',
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setInstanceDomain(ruleId: string, oldDomain: string, newDomain: string, domainCode: string | null): void {
  const next = characterBuildService.setAbilityInstanceDomain(
    props.build,
    ruleId,
    oldDomain,
    newDomain,
    {
      domainCode,
    },
    props.rules,
  );
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function removeInstance(ruleId: string, domain: string): void {
  const next = characterBuildService.removeAbilityInstance(props.build, ruleId, domain, props.rules);
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setAbilityDomain(ruleId: string, domain: string, domainCode: string | null): void {
  const next = characterBuildService.setAbilityDomain(props.build, ruleId, domain, { domainCode });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}
</script>

<template>
  <div>
    <FilterBar
      :fields="abilityFilterFields"
      :model-value="appliedFilters"
      placeholder="Фильтр по навыкам"
      settings-key="character-editor-development"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

    <div class="d-flex align-center ga-2 mb-3 flex-wrap">
      <v-tabs v-model="categoryFilter" density="compact" class="category-tabs">
        <v-tab value="all">Все</v-tab>
        <v-tab value="attack">Атаки</v-tab>
        <v-tab value="physical">Физическое развитие</v-tab>
      </v-tabs>
      <v-chip
        size="small"
        variant="tonal"
        :color="availableOnly ? 'primary' : undefined"
        @click="availableOnly = !availableOnly"
      >
        Доступные
      </v-chip>
      <v-chip
        size="small"
        variant="tonal"
        :color="acquiredOnly ? 'primary' : undefined"
        @click="acquiredOnly = !acquiredOnly"
      >
        Приобретённые
      </v-chip>
    </div>

    <div v-if="giftedAbilities.length" class="mb-4">
      <v-card variant="outlined" class="pa-4">
        <div class="text-subtitle-2 mb-2">Получено от особенностей</div>
        <div class="d-flex flex-wrap ga-2">
          <v-chip
            v-for="ability in giftedAbilities"
            :key="ability.ruleId"
            size="small"
            variant="tonal"
            color="secondary"
          >
            {{ ability.name }} · уровень {{ ability.level }}
          </v-chip>
        </div>
      </v-card>
    </div>

    <VirtualList
      :items="rootAbilities"
      :estimate-size="56"
      :get-item-key="abilityKey"
      :reset-key="resetKey"
      :height="catalogHeight"
      empty-text="Навыки не найдены."
      v-slot="{ item }"
    >
      <DevelopmentAbilityNode
        :ability="item"
        :children-by-code="childrenByCode"
        :keywords="keywords"
        :rules="rules"
        :open-set="effectiveOpenSet"
        @update:open="setOpen"
        @set-parameter="setParameter"
        @set-level="setLevel"
        @add-instance="addInstance"
        @set-instance-level="setInstanceLevel"
        @set-instance-domain="setInstanceDomain"
        @remove-instance="removeInstance"
        @set-ability-domain="setAbilityDomain"
      />
    </VirtualList>
  </div>
</template>

<style scoped>
.category-tabs {
  max-width: 100%;
  overflow-x: auto;
}

:deep(.virtual-list .v-expansion-panel-title) {
  min-height: 48px;
}

@media (max-width: 960px) {
  .category-tabs {
    width: 100%;
  }
}
</style>
