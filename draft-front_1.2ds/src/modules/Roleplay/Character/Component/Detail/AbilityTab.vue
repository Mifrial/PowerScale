<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import { useAbilityFavoritesStore } from '@/modules/Roleplay/Character/Store/abilityFavorites';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/init';
import type { AbilityOverview } from '@/modules/Roleplay/Character/Dto/Overview/AbilityOverview';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import DescriptionHtml from '@/modules/Core/UI/Component/DescriptionHtml.vue';

const props = defineProps<{
  version: CharacterVersion;
  rules: Rule[];
  rulesLoading: boolean;
  characterId: number;
  showFavorites?: boolean;
}>();

// Табы быстрого фильтра по типу способности (тегу): по запросу — Навык/Черта/Заклинание.
const QUICK_TYPE_TABS: AbilityType[] = ['skill', 'trait', 'spell'];

const favoritesStore = useAbilityFavoritesStore();
const keywordStore = useKeywordStore();
const { openRule } = useRuleDetailSlider();

const activeTab = ref<'all' | 'favorites' | AbilityType>('all');

const abilities = computed(() => characterOverviewService.build(props.version, props.rules).abilities);

const abilityFilterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  {
    key: 'type',
    label: 'Тип',
    type: 'select',
    options: Object.entries(ABILITY_TYPE_LABELS).map(([value, label]) => ({ label, value })),
  },
];

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => abilities.value as unknown as Record<string, unknown>[],
  fields: abilityFilterFields,
  searchFields: ['name', 'description'],
});

const filteredAbilities = computed(() => {
  let result = filteredRows.value as unknown as AbilityOverview[];
  if (activeTab.value === 'favorites') {
    result = result.filter((ability) => favoritesStore.isFavorite(props.characterId, ability.ruleId));
  } else if (activeTab.value !== 'all') {
    result = result.filter((ability) => ability.type === activeTab.value);
  }

  return result;
});

function abilityTypeLabel(type: AbilityType | null): string | null {
  return type === null ? null : (ABILITY_TYPE_LABELS[type] ?? type);
}

/** Стоимость/значение размерного числа: «3», «3↑». */
function costLabel(amount: DimensionalNumberValue | number | null): string | null {
  if (amount == null) return null;
  if (typeof amount === 'number') return String(amount);

  return new DimensionalNumber(amount).toString();
}

// Признаки способности — ключевые слова правила; грузим справочник лениво, один раз.
function keywordsOf(ability: AbilityOverview): Keyword[] {
  if (ability.keywordIds.length === 0) return [];

  return keywordStore.keywords.filter((keyword) => ability.keywordIds.includes(keyword.id));
}

function isFavorite(ruleId: string): boolean {
  return favoritesStore.isFavorite(props.characterId, ruleId);
}

function toggleFavorite(ruleId: string): void {
  favoritesStore.toggle(props.characterId, ruleId);
}

onMounted(() => {
  if (keywordStore.keywords.length === 0) {
    void keywordStore.fetchTags();
  }
});
</script>

<template>
  <div class="ability-tab">
    <FilterBar
      :fields="abilityFilterFields"
      :model-value="appliedFilters"
      placeholder="Фильтр по способностям"
      settings-key="character-sheet-abilities"
      class="mb-2"
      @update:model-value="onFilterChange"
    />

    <div class="d-flex align-center ga-2 mb-3 flex-wrap">
      <v-tabs v-model="activeTab" density="compact" class="ability-tab__types">
        <v-tab value="all">Все</v-tab>
        <v-tab v-if="showFavorites !== false" value="favorites">Избранное</v-tab>
        <v-tab v-for="type in QUICK_TYPE_TABS" :key="type" :value="type">{{ ABILITY_TYPE_LABELS[type] }}</v-tab>
      </v-tabs>
    </div>

    <div v-if="rulesLoading && rules.length === 0" class="pa-4 text-medium-emphasis">Загружаем правила…</div>
    <v-expansion-panels v-else-if="filteredAbilities.length" multiple variant="accordion" class="ability-panels">
      <v-expansion-panel v-for="ability in filteredAbilities" :key="ability.ruleId">
        <v-expansion-panel-title>
          <div class="d-flex align-center ga-2 w-100 pr-2">
            <span class="font-weight-medium">{{ ability.name }}</span>
            <v-chip v-if="ability.level > 0 && !ability.hasParameters" size="x-small" variant="tonal" color="primary">
              {{ ability.level }} ур.
            </v-chip>
            <v-chip v-if="abilityTypeLabel(ability.type)" size="x-small" variant="tonal">
              {{ abilityTypeLabel(ability.type) }}
            </v-chip>
            <v-chip v-if="ability.type === 'action' && costLabel(ability.actionOdCost)" size="x-small" variant="tonal">
              ОД: {{ costLabel(ability.actionOdCost) }}
            </v-chip>
            <template v-if="ability.type === 'spell'">
              <v-chip v-if="costLabel(ability.spellCastCost)" size="x-small" variant="tonal">
                Сотворение: {{ costLabel(ability.spellCastCost) }}
              </v-chip>
              <v-chip v-if="costLabel(ability.spellDifficulty)" size="x-small" variant="tonal">
                Сложность: {{ costLabel(ability.spellDifficulty) }}
              </v-chip>
              <v-chip v-if="ability.spellDurationLabel" size="x-small" variant="tonal">
                {{ ability.spellDurationLabel }}
              </v-chip>
            </template>

            <v-spacer />
            <v-divider vertical class="mx-1 align-self-stretch" />
            <v-btn icon size="small" variant="text" title="Открыть правило" @click.stop="openRule(ability.ruleId)">
              <v-icon icon="mdi-open-in-new" />
            </v-btn>
            <v-btn
              v-if="showFavorites !== false"
              icon
              size="small"
              variant="text"
              :color="isFavorite(ability.ruleId) ? 'amber' : undefined"
              :title="isFavorite(ability.ruleId) ? 'Убрать из избранного' : 'В избранное'"
              @click.stop="toggleFavorite(ability.ruleId)"
            >
              <v-icon :icon="isFavorite(ability.ruleId) ? 'mdi-star' : 'mdi-star-outline'" />
            </v-btn>
          </div>
        </v-expansion-panel-title>

        <v-expansion-panel-text>
          <DescriptionHtml v-if="ability.description" :html="ability.description" @open-rule="openRule" />
          <div v-else class="text-body-2 ability-tab__desc">—</div>
          <v-divider class="my-3" />
          <div class="d-flex align-center ga-2 flex-wrap">
            <span class="text-caption text-medium-emphasis">Признаки:</span>
            <template v-if="keywordsOf(ability).length">
              <v-chip v-for="keyword in keywordsOf(ability)" :key="keyword.id" size="x-small" variant="outlined">
                {{ keyword.name }}
              </v-chip>
            </template>
            <span v-else class="text-caption text-medium-emphasis">нет</span>
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
    <div v-else class="text-medium-emphasis pa-4">
      {{ abilities.length === 0 ? 'У персонажа нет способностей' : 'Способности не найдены' }}
    </div>
  </div>
</template>

<style scoped>
.ability-tab__types {
  max-width: 100%;
  overflow-x: auto;
}
.ability-panels :deep(.v-expansion-panel-title) {
  min-height: 36px;
  padding-top: 2px;
  padding-bottom: 2px;
}
.ability-tab__desc {
  white-space: pre-line;
}
</style>
