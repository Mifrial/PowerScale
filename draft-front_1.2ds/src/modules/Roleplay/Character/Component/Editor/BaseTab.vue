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
import LightChip from '@/modules/Core/UI/Component/light/LightChip.vue';
import type { EditorAbilityGroup } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityGroup';

type BaseFilter = 'all' | 'available' | 'unavailable' | 'racial' | 'public';

/** Верхний уровень виртуализированного каталога: секция-группа либо одиночная способность. */
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
const activeFilter = ref<BaseFilter>('all');
const chosenOnly = ref(false);
/** Раскрытые панели способностей/групп (переживают ремаунты строк виртуализации). */
const openSet = ref<Set<string>>(new Set());

function osZoneOf(ability: EditorAbility): EditorAbilityZone | null {
  return ability.zones.find((zone) => zone.zoneCode === 'os') ?? null;
}

const osAbilities = computed(() =>
  props.model.abilities.filter((ability) => osZoneOf(ability) !== null && ability.visible && !ability.characteristic),
);

const abilityFilterFields: FilterField[] = [{ key: 'name', label: 'Название', type: 'string' }];

const {
  filteredRows: abilityRows,
  appliedFilters,
  onFilterChange,
} = useFilteredRows({
  getItems: () => osAbilities.value as unknown as Record<string, unknown>[],
  fields: abilityFilterFields,
  searchFields: ['name'],
});

const abilitiesByName = computed<EditorAbility[]>(() => abilityRows.value as unknown as EditorAbility[]);

function applyCategory(list: EditorAbility[]): EditorAbility[] {
  let result = list;
  switch (activeFilter.value) {
    case 'available':
      result = result.filter((ability) => ability.levels[0]?.met ?? false);
      break;
    case 'unavailable':
      result = result.filter((ability) => !(ability.levels[0]?.met ?? false));
      break;
    case 'racial':
      result = result.filter((ability) => ability.racial);
      break;
    case 'public':
      result = result.filter((ability) => !ability.racial);
      break;
  }

  return result;
}

/** Способности после поиска, категории и чипа «Выбранные». */
const allAbilities = computed(() => {
  let list = abilitiesByName.value;
  if (chosenOnly.value) list = list.filter((ability) => ability.level > 0 || ability.automatic);

  return applyCategory(list);
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
const resetKey = computed(() => JSON.stringify([appliedFilters.value, activeFilter.value, chosenOnly.value]));

/** Высота скролл-области каталога: почти весь вьюпорт под шапкой/фильтрами. */
const catalogHeight = 'calc(100vh - 295px)';

/** Сколько способностей группы взято (уровень > 0 или бесплатная). */
function chosenInGroup(group: EditorAbilityGroup): number {
  return group.members.filter((member) => member.level > 0 || member.automatic).length;
}

/** Группы с лимитом N>1, где выбор исчерпан — участники (не взятые) блокируются. */
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

  return locked;
});

function setOpen(ruleId: string, open: boolean): void {
  if (open) openSet.value.add(ruleId);
  else openSet.value.delete(ruleId);
}

function itemKey(item: CatalogItem): string {
  return item.kind === 'group' ? item.group.ruleId : item.ability.ruleId;
}

function setLevel(ruleId: string, level: number): void {
  const next = characterBuildService.setAbilityLevel(props.build, ruleId, level, props.rules, { zone: 'os' });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setParameter(ruleId: string, code: string, value: number | { base: number; size: number }): void {
  const next = characterBuildService.setAbilityParameter(props.build, ruleId, code, value, props.rules);
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function addInstance(ruleId: string, domain: string, domainCode: string | null): void {
  const next = characterBuildService.addAbilityInstance(props.build, ruleId, domain, props.rules, {
    zone: 'os',
    domainCode,
  });
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}

function setInstanceLevel(ruleId: string, domain: string, level: number): void {
  const next = characterBuildService.setAbilityInstanceLevel(props.build, ruleId, domain, level, props.rules, {
    zone: 'os',
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

/** Правило механики прогрессивной доплаты (объяснение на вкладке). */
const surchargeRule = computed<Rule | undefined>(() =>
  props.rules.find((rule) => {
    const payload = rule.mechanic_payload as { type?: string } | null | undefined;

    return payload?.type === 'purchase_surcharge';
  }),
);

/** Активная доплата механики «Общие черты» (из бюджета модели). */
const osSurcharge = computed(() => props.model.budgets.osSurcharge);

/** Доплата по ruleId способности: код из ui-аннотации → ruleId через правила ревизии. */
const surchargeByRuleId = computed<Map<string, number>>(() => {
  const map = new Map<string, number>();
  const byCode = new Map((osSurcharge.value?.items ?? []).map((item) => [item.abilityCode, item.amount]));
  for (const rule of props.rules) {
    const amount = byCode.get(rule.code);
    if (amount !== undefined) map.set(rule.id, amount);
  }

  return map;
});
</script>

<template>
  <div>
    <FilterBar
      :fields="abilityFilterFields"
      :model-value="appliedFilters"
      placeholder="Фильтр по способностям"
      settings-key="character-editor-base"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

    <div class="d-flex align-center ga-2 mb-3 flex-wrap">
      <v-tabs v-model="activeFilter" density="compact" class="category-tabs">
        <v-tab value="all">Все</v-tab>
        <v-tab value="available">Доступные</v-tab>
        <v-tab value="unavailable">Недоступные</v-tab>
        <v-tab value="racial">Расовые</v-tab>
        <v-tab value="public">Общедоступные</v-tab>
      </v-tabs>
      <v-chip
        size="small"
        variant="tonal"
        :color="chosenOnly ? 'primary' : undefined"
        @click="chosenOnly = !chosenOnly"
      >
        Выбранные
      </v-chip>
    </div>

    <div v-if="surchargeRule" class="d-flex align-center ga-2 flex-wrap mb-2">
      <span class="text-caption text-medium-emphasis">{{ surchargeRule.description }}</span>
      <LightChip v-if="osSurcharge" color="warning">
        Доплата: {{ osSurcharge.total }} ОС за {{ osSurcharge.items.length }} черт
      </LightChip>
    </div>

    <VirtualList
      :items="catalogItems"
      :estimate-size="56"
      :get-item-key="itemKey"
      :reset-key="resetKey"
      :height="catalogHeight"
      empty-text="Черты не найдены."
    >
      <template #default="{ item }">
        <EditorAbilityGroupNode
          v-if="item.kind === 'group'"
          :group="item.group"
          :members="item.members"
          :keywords="keywords"
          :rules="rules"
          :locked-rule-ids="lockedRuleIds"
          zone-code="os"
          zone-label="ОС"
          :surcharge-by-rule-id="surchargeByRuleId"
          :open-set="effectiveOpenSet"
          @update:open="setOpen"
          @set-level="setLevel"
          @set-parameter="setParameter"
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
          zone-code="os"
          zone-label="ОС"
          :surcharge-amount="surchargeByRuleId.get(item.ability.ruleId)"
          :open="effectiveOpenSet.has(item.ability.ruleId)"
          @update:open="setOpen(item.ability.ruleId, $event)"
          @set-level="setLevel"
          @set-parameter="setParameter"
          @add-instance="addInstance"
          @set-instance-level="setInstanceLevel"
          @set-instance-domain="setInstanceDomain"
          @remove-instance="removeInstance"
          @set-ability-domain="setAbilityDomain"
        />
      </template>
    </VirtualList>
  </div>
</template>

<style scoped>
.category-tabs {
  max-width: 100%;
  overflow-x: auto;
}

:deep(.virtual-list .expandable-item__trigger) {
  min-height: 48px;
}

@media (max-width: 960px) {
  .category-tabs {
    width: 100%;
  }
}
</style>
