<script setup lang="ts">
import { computed, ref } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import type { AbilityInstanceAddPayload } from '@/modules/Roleplay/Character/Dto/Editor/AbilityInstanceAddPayload';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import TreeSelectFilter from '@/modules/Core/UI/Component/FilterBar/handlers/TreeSelectFilter.vue';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import DevelopmentAbilityNode from '@/modules/Roleplay/Character/Component/Editor/DevelopmentAbilityNode.vue';
import { isAcquiredAbility } from '@/modules/Roleplay/Character/Utils/developmentCategory';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterOptionValue';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { DevelopmentAbilityRow } from '@/modules/Roleplay/Character/Dto/Editor/DevelopmentAbilityRow';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RuleCatalogArea } from '@/modules/Roleplay/RuleSpace/Enum/RuleCatalogArea';
import { developmentListService } from '@/modules/Roleplay/Character/Service/Instance/developmentListService';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/init';

const props = defineProps<{
  build: CharacterBuild;
  model: CharacterEditorModel;
  draftKey: string | null;
  keywords: Keyword[];
  rules: Rule[];
  sections: AbilitySection[];
}>();

const draftStore = useCharacterDraftStore();

const availableOnly = ref(false);
const acquiredOnly = ref(false);
/** Раскрытые панели навыков (переживают ремаунты строк виртуализации). */
const openSet = ref<Set<string>>(new Set());

const developmentArea: RuleCatalogArea = 'development';
const sectionTreeOptions = abilitySectionTreeService.flatten(
  abilitySectionTreeService.subtreeForArea(developmentArea, props.sections),
);

function sectionOf(ability: EditorAbility): string | null {
  const rule = props.rules.find((entry) => entry.code === ability.ruleCode);

  return rule?.catalogSection ?? null;
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

const sectionFilterField: FilterField = {
  key: 'section',
  label: 'Раздел',
  type: 'tree-select',
  treeOptions: sectionTreeOptions.map((section) => ({
    label: section.name,
    value: section.code,
    path: section.path,
    depth: section.depth,
    parentValue: section.parentCode,
  })),
};
const abilityFilterFields: FilterField[] = [{ key: 'name', label: 'Название', type: 'string' }];
const filterFields: FilterField[] = [...abilityFilterFields, sectionFilterField];

const {
  filteredRows: abilityRows,
  appliedFilters,
  onFilterChange,
} = useFilteredRows({
  getItems: () => devAbilities.value as unknown as Record<string, unknown>[],
  fields: filterFields,
  searchFields: ['name'],
});
const sectionFilterValue = computed<FilterOptionValue | null>(() => {
  const value = appliedFilters.value.section;

  return typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? value : null;
});

function onSectionFilterChange(value: FilterOptionValue | null | undefined): void {
  const nextFilters = { ...appliedFilters.value };
  if (value === null || value === undefined || value === '') delete nextFilters.section;
  else nextFilters.section = value;
  onFilterChange(nextFilters);
}

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
    if (seen.has(row.ruleCode)) continue;
    seen.add(row.ruleCode);
    result.push(row);
    let node = row.parentCode ? (byCode.get(row.parentCode) ?? null) : null;
    while (node) {
      if (appliedFilters.value.section && sectionOf(node) !== row.section) break;
      if (seen.has(node.ruleCode)) break;
      seen.add(node.ruleCode);
      result.push(node);
      node = node.parentCode ? (byCode.get(node.parentCode) ?? null) : null;
    }
  }

  return result;
});

function passesFilters(ability: EditorAbility): boolean {
  if (availableOnly.value && !(ability.levels[0]?.met ?? false)) return false;
  if (acquiredOnly.value && !isAcquiredAbility(ability)) return false;

  return true;
}

const sectionOrderByCode = new Map(sectionTreeOptions.map((section, index) => [section.code, index]));

function abilitySortOrder(ability: EditorAbility & { section: string | null }): number {
  const rule = props.rules.find((entry) => entry.code === ability.ruleCode);
  const sortOrder = typeof rule?.catalogSortOrder === 'number' ? rule.catalogSortOrder : Number.MAX_SAFE_INTEGER;

  return sortOrder;
}

const allAbilities = computed(() =>
  abilitiesByName.value
    .filter(passesFilters)
    .sort(
      (left, right) =>
        (sectionOrderByCode.get(left.section ?? '') ?? Number.MAX_SAFE_INTEGER) -
          (sectionOrderByCode.get(right.section ?? '') ?? Number.MAX_SAFE_INTEGER) ||
        abilitySortOrder(left) - abilitySortOrder(right) ||
        left.name.localeCompare(right.name) ||
        left.code.localeCompare(right.code),
    ),
);

/** Дерево улучшений: «код способности → её улучшения» заменяется строками списка после expand. */
const listRows = computed(() => developmentListService.expand(allAbilities.value));
const childrenByParentKey = computed(() => developmentListService.childrenByParentKey(listRows.value));
const rootRows = computed(() => developmentListService.roots(listRows.value, childrenByParentKey.value));

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
      result.add(node.ruleCode);
      result.add(`${node.ruleCode}:catalog`);
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
    availableOnly.value,
    acquiredOnly.value,
  ]),
);

/** Высота скролл-области каталога: почти весь вьюпорт под шапкой/фильтрами. */
const catalogHeight = 'calc(100dvh - 220px)';

function setOpen(ruleCode: string, open: boolean): void {
  if (open) openSet.value.add(ruleCode);
  else openSet.value.delete(ruleCode);
}

function abilityKey(row: DevelopmentAbilityRow): string {
  return row.key;
}

function setLevel(ruleCode: string, level: number): void {
  const next = characterBuildService.setAbilityLevel(props.build, ruleCode, level, props.rules, { zone: 'or' });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setParameter(ruleCode: string, code: string, value: number | { base: number; size: number }): void {
  const next = characterBuildService.setAbilityParameter(props.build, ruleCode, code, value, props.rules);
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function addInstance(
  ruleCode: string,
  domain: string,
  domainCode: string | null,
  extras?: AbilityInstanceAddPayload,
): void {
  const next = characterBuildService.addAbilityInstance(props.build, ruleCode, domain, props.rules, {
    zone: 'or',
    domainCode,
    fieldCode: extras?.fieldCode,
    slots: extras?.slots,
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setInstanceLevel(ruleCode: string, domain: string, level: number): void {
  const next = characterBuildService.setAbilityInstanceLevel(props.build, ruleCode, domain, level, props.rules, {
    zone: 'or',
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setInstanceDomain(ruleCode: string, oldDomain: string, newDomain: string, domainCode: string | null): void {
  const next = characterBuildService.setAbilityInstanceDomain(
    props.build,
    ruleCode,
    oldDomain,
    newDomain,
    {
      domainCode,
    },
    props.rules,
  );
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function removeInstance(ruleCode: string, domain: string, domainCode?: string | null): void {
  const next = characterBuildService.removeAbilityInstance(props.build, ruleCode, domain, props.rules, {
    domainCode,
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setAbilityDomain(ruleCode: string, domain: string, domainCode: string | null): void {
  const next = characterBuildService.setAbilityDomain(props.build, ruleCode, domain, { domainCode });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}
</script>

<template>
  <div>
    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      placeholder="Фильтр по навыкам"
      settings-key="character-editor-development"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

    <div class="development-filter-row d-flex align-center mb-3">
      <TreeSelectFilter
        :field="sectionFilterField"
        :model-value="sectionFilterValue"
        eager
        class="section-filter"
        @update:model-value="onSectionFilterChange"
      />
      <v-chip
        size="small"
        variant="tonal"
        class="status-filter-chip status-filter-chip--first"
        :color="availableOnly ? 'primary' : undefined"
        @click="availableOnly = !availableOnly"
      >
        Доступные
      </v-chip>
      <v-chip
        size="small"
        variant="tonal"
        class="status-filter-chip"
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
            :key="ability.ruleCode"
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
      :items="rootRows"
      :estimate-size="56"
      :get-item-key="abilityKey"
      :reset-key="resetKey"
      :height="catalogHeight"
      empty-text="Навыки не найдены."
      v-slot="{ item }"
    >
      <DevelopmentAbilityNode
        :row="item"
        :children-by-parent-key="childrenByParentKey"
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
.section-filter {
  flex: 1 1 auto;
  min-width: 0;
}

.development-filter-row {
  gap: 10px;
}

.status-filter-chip--first {
  margin-left: auto;
}

:deep(.virtual-list .v-expansion-panel-title) {
  min-height: 48px;
}
</style>
