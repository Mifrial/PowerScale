<script setup lang="ts">
import { computed, ref } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import VirtualList from '@/modules/Core/UI/Component/VirtualList.vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityZone } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityZone';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import EditorAbilityGroupNode from '@/modules/Roleplay/Character/Component/Editor/EditorAbilityGroupNode.vue';
import EditorAbilityRow from '@/modules/Roleplay/Character/Component/Editor/EditorAbilityRow.vue';
import type { EditorAbilityGroup } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityGroup';

/** Верхний уровень виртуализированного каталога: секция-группа либо одиночная особенность. */
type CatalogItem =
  { kind: 'group'; group: EditorAbilityGroup; members: EditorAbility[] } | { kind: 'ability'; ability: EditorAbility };

const props = defineProps<{
  build: CharacterBuild;
  model: CharacterEditorModel;
  draftKey: string | null;
  keywords: Keyword[];
  rules: Rule[];
}>();

const draftStore = useCharacterDraftStore();
const chosenOnly = ref(false);
/** Раскрытые панели особенностей/групп (переживают ремаунты строк виртуализации). */
const openSet = ref<Set<string>>(new Set());

const personality = computed(() => props.model.personality);

function olZoneOf(ability: EditorAbility): EditorAbilityZone | null {
  return ability.zones.find((zone) => zone.zoneCode === 'ol') ?? null;
}

/** Все особенности личности (зона ol), включая ещё не взятые. */
const olAbilities = computed(() => props.model.abilities.filter((ability) => olZoneOf(ability) !== null));

const wealthRuleIds = computed(() => new Set(personality.value.wealthRuleIds));

/** Взятые особенности (уровень > 0). */
const chosenAbilities = computed(() => olAbilities.value.filter((ability) => ability.level > 0));

/** Взятые особенности без богатства — считаются против лимита числа особенностей. */
const takenNonWealth = computed(() =>
  chosenAbilities.value.filter((ability) => !wealthRuleIds.value.has(ability.ruleId)),
);

const featureLimitReached = computed(
  () => personality.value.featureLimit !== null && takenNonWealth.value.length >= personality.value.featureLimit,
);

/** Сколько особенностей группы взято (уровень > 0 или бесплатная). */
function chosenInGroup(group: EditorAbilityGroup): number {
  return group.members.filter((member) => member.level > 0 || member.automatic).length;
}

/** Заблокированные правила: исчерпанная группа N>1, лимит числа особенностей, богатство при edit. */
const lockedRuleIds = computed<Set<string>>(() => {
  const locked = new Set<string>();
  for (const group of props.model.groups) {
    if (group.selectLimit <= 1) continue;
    if (chosenInGroup(group) >= group.selectLimit) {
      for (const member of group.members) {
        if (member.level === 0 && !member.automatic) locked.add(member.ruleId);
      }
    }
  }
  if (featureLimitReached.value) {
    for (const ability of olAbilities.value) {
      if (ability.level > 0 || ability.automatic) continue;
      if (!wealthRuleIds.value.has(ability.ruleId)) locked.add(ability.ruleId);
    }
  }
  if (props.draftKey !== null) {
    for (const ruleId of wealthRuleIds.value) locked.add(ruleId);
  }

  return locked;
});

const abilityFilterFields: FilterField[] = [{ key: 'name', label: 'Название', type: 'string' }];

const {
  filteredRows: abilityRows,
  appliedFilters,
  onFilterChange,
} = useFilteredRows({
  getItems: () => olAbilities.value as unknown as Record<string, unknown>[],
  fields: abilityFilterFields,
  searchFields: ['name'],
});

const abilitiesByName = computed<EditorAbility[]>(() => abilityRows.value as unknown as EditorAbility[]);

/** Особенности после поиска и чипа «Выбранные». */
const allAbilities = computed(() => {
  let list = abilitiesByName.value;
  if (chosenOnly.value) list = list.filter((ability) => ability.level > 0);

  return list;
});

/** Верхний уровень каталога: секции-группы (с отфильтрованными участниками) и одиночные. */
const catalogItems = computed<CatalogItem[]>(() => {
  const visibleIds = new Set(allAbilities.value.map((ability) => ability.ruleId));
  const items: CatalogItem[] = [];
  for (const group of props.model.groups) {
    const members = group.members.filter((member) => visibleIds.has(member.ruleId));
    if (members.length) items.push({ kind: 'group', group, members });
  }
  for (const ability of allAbilities.value) {
    if (!ability.groupCode) items.push({ kind: 'ability', ability });
  }

  return items;
});

/**
 * Авто-раскрытие групп при поиске: группа совпавшего участника раскрывается
 * (оверлей к openSet, исчезает при очистке поиска).
 */
const autoOpenSet = computed(() => {
  const result = new Set<string>();
  const q = typeof appliedFilters.value.q === 'string' ? appliedFilters.value.q.trim() : '';
  if (!q) return result;
  for (const ability of allAbilities.value) {
    if (!ability.groupCode) continue;
    const group = props.model.groups.find((entry) => entry.code === ability.groupCode);
    if (group) result.add(group.ruleId);
  }

  return result;
});

/** Раскрытые панели: пользовательские + авто-раскрытие групп по поиску. */
const effectiveOpenSet = computed(() => new Set([...openSet.value, ...autoOpenSet.value]));

/** Сброс скролла каталога наверх при смене любого фильтра. */
const resetKey = computed(() => JSON.stringify([appliedFilters.value, chosenOnly.value]));

/** Высота скролл-области каталога: вьюпорт под шапкой, карточкой возраста и фильтрами. */
const catalogHeight = 'calc(100vh - 355px)';

/** Выбор ступени на шкале возраста: годы = минимум её диапазона (уточняется на этапе «Описание»). */
function pickAge(step: { name: string; min: number; max: number | null }): void {
  draftStore.patchBuild(props.draftKey, { ageYears: step.min });
}

/** Подпись диапазона ступени: «до N» (Младенец) / «от N» (Старый) / «от N до M». */
function rangeLabel(step: { name: string; min: number; max: number | null }): string {
  if (step.min === 0) return `до ${step.max}`;
  if (step.max === null) return `от ${step.min}`;

  return `от ${step.min} до ${step.max}`;
}

function setOpen(ruleId: string, open: boolean): void {
  if (open) openSet.value.add(ruleId);
  else openSet.value.delete(ruleId);
}

function itemKey(item: CatalogItem): string {
  return item.kind === 'group' ? item.group.ruleId : item.ability.ruleId;
}

function setLevel(ruleId: string, level: number): void {
  const next = characterBuildService.setAbilityLevel(props.build, ruleId, level, props.rules, {
    zone: 'ol',
    featureLimit: personality.value.featureLimit,
    wealthLocked: props.draftKey !== null,
    wealthRuleIds: new Set(personality.value.wealthRuleIds),
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function addInstance(ruleId: string, domain: string, domainCode: string | null): void {
  const next = characterBuildService.addAbilityInstance(props.build, ruleId, domain, props.rules, {
    zone: 'ol',
    domainCode,
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setInstanceLevel(ruleId: string, domain: string, level: number): void {
  const next = characterBuildService.setAbilityInstanceLevel(props.build, ruleId, domain, level, props.rules, {
    zone: 'ol',
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
    <div v-if="!model.race.ruleId" class="text-medium-emphasis pa-4">
      Сначала выберите расу — возраст и особенности личности зависят от вида.
    </div>
    <div v-else-if="!personality.hasAgeRule" class="text-medium-emphasis pa-4">
      В этой ревизии правил нет возраста — этап «Личность» недоступен.
    </div>

    <template v-else>
      <v-card variant="outlined" class="mb-4 pa-4">
        <div class="d-flex align-center justify-space-between ga-2 mb-2">
          <span class="text-subtitle-2">Возраст</span>
          <span class="text-caption text-medium-emphasis">
            От выбранного возраста зависит число ОЛ и предел числа особенностей
          </span>
        </div>
        <div v-if="personality.ageScale.length" class="age-scale">
          <button
            v-for="step in personality.ageScale"
            :key="step.name"
            type="button"
            class="age-scale-cell"
            :class="{ active: personality.ageName === step.name }"
            @click="pickAge(step)"
          >
            <span class="age-scale-name">{{ step.name }}</span>
            <span class="age-scale-range">{{ rangeLabel(step) }}</span>
          </button>
        </div>
        <div v-else class="text-body-2 text-medium-emphasis">
          Сначала выберите расу — возрастная шкала зависит от вида.
        </div>
      </v-card>

      <div class="d-flex align-center ga-2 mb-3 flex-wrap">
        <span class="text-subtitle-2">Особенности личности</span>
        <v-chip size="small" variant="tonal">
          ОЛ: {{ model.budgets.ol.spent }}
          <template v-if="personality.ol !== null"> / {{ personality.ol }}</template>
        </v-chip>
        <v-chip
          v-if="personality.featureLimit !== null"
          size="small"
          variant="tonal"
          :color="featureLimitReached ? 'primary' : undefined"
        >
          особенности: {{ takenNonWealth.length }} / {{ personality.featureLimit }}
        </v-chip>
        <v-chip v-if="personality.wealthRuleIds.length" size="small" variant="tonal">
          Богатство не считается в лимите
        </v-chip>
        <v-chip
          size="small"
          variant="tonal"
          :color="chosenOnly ? 'primary' : undefined"
          @click="chosenOnly = !chosenOnly"
        >
          Выбранные
        </v-chip>
      </div>

      <FilterBar
        :fields="abilityFilterFields"
        :model-value="appliedFilters"
        placeholder="Фильтр по особенностям"
        settings-key="character-editor-personality"
        class="mb-2"
        @update:model-value="onFilterChange"
      />

      <VirtualList
        :items="catalogItems"
        :estimate-size="56"
        :get-item-key="itemKey"
        :reset-key="resetKey"
        :height="catalogHeight"
        empty-text="Особенности не найдены."
      >
        <template #default="{ item }">
          <EditorAbilityGroupNode
            v-if="item.kind === 'group'"
            :group="item.group"
            :members="item.members"
            :keywords="keywords"
            :rules="rules"
            :locked-rule-ids="lockedRuleIds"
            zone-code="ol"
            zone-label="ОЛ"
            :open-set="effectiveOpenSet"
            @update:open="setOpen"
            @set-level="setLevel"
            @add-instance="addInstance"
            @set-instance-level="setInstanceLevel"
            @set-instance-domain="setInstanceDomain"
            @remove-instance="removeInstance"
            @set-ability-domain="setAbilityDomain"
          />
          <EditorAbilityRow
            v-else
            :ability="item.ability"
            :keywords="keywords"
            :rules="rules"
            :locked-rule-ids="lockedRuleIds"
            zone-code="ol"
            zone-label="ОЛ"
            :open="effectiveOpenSet.has(item.ability.ruleId)"
            @update:open="setOpen(item.ability.ruleId, $event)"
            @set-level="setLevel"
            @add-instance="addInstance"
            @set-instance-level="setInstanceLevel"
            @set-instance-domain="setInstanceDomain"
            @remove-instance="removeInstance"
            @set-ability-domain="setAbilityDomain"
          />
        </template>
      </VirtualList>
    </template>
  </div>
</template>

<style scoped>
/* Шкала возраста: ячейки в одну строку с переносом, две строки текста в каждой. */
.age-scale {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.age-scale-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 6px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  text-align: left;
}

.age-scale-cell:hover {
  background: rgba(var(--v-theme-primary), 0.06);
}

.age-scale-cell.active {
  background: rgba(var(--v-theme-primary), 0.12);
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.age-scale-name {
  font-weight: 500;
  font-size: 13px;
  line-height: 1.3;
}

.age-scale-range {
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.72);
  line-height: 1.3;
}

:deep(.virtual-list .expandable-item__trigger) {
  min-height: 48px;
}
</style>
