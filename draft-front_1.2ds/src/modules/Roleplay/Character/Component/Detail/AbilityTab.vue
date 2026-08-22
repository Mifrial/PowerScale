<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import { useAbilityFavoritesStore } from '@/modules/Roleplay/Character/Store/abilityFavorites';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/init';
import type { AbilityOverview } from '@/modules/Roleplay/Character/Dto/Overview/AbilityOverview';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';

const props = defineProps<{
  version: CharacterVersion;
  rules: Rule[];
  rulesLoading: boolean;
  characterId: number;
}>();

// Табы быстрого фильтра по типу способности (тегу): по запросу — Навык/Черта/Заклинание.
const QUICK_TYPE_TABS: AbilityType[] = ['skill', 'trait', 'spell'];

const favoritesStore = useAbilityFavoritesStore();
const keywordStore = useKeywordStore();
const { openRule } = useRuleDetailSlider();

const searchQuery = ref('');
const activeTab = ref<'all' | 'favorites' | AbilityType>('all');

const abilities = computed(() => characterOverviewService.build(props.version, props.rules).abilities);

// Быстрый фильтр табами (Все / Избранное / типы) + поиск по имени/описанию — как в списке правил пространства.
const filteredAbilities = computed(() => {
  let result = abilities.value;
  if (activeTab.value === 'favorites') {
    result = result.filter((ability) => favoritesStore.isFavorite(props.characterId, ability.ruleId));
  } else if (activeTab.value !== 'all') {
    result = result.filter((ability) => ability.type === activeTab.value);
  }
  const query = searchQuery.value?.toLowerCase();
  if (query) {
    result = result.filter(
      (ability) => ability.name.toLowerCase().includes(query) || ability.description.toLowerCase().includes(query),
    );
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
  <v-card>
    <v-text-field v-model="searchQuery" label="Поиск" prepend-inner-icon="mdi-magnify" clearable class="mb-4 px-4" />

    <v-tabs v-model="activeTab" class="mb-4 px-4">
      <v-tab value="all">Все</v-tab>
      <v-tab value="favorites">Избранное</v-tab>
      <v-tab v-for="type in QUICK_TYPE_TABS" :key="type" :value="type">{{ ABILITY_TYPE_LABELS[type] }}</v-tab>
    </v-tabs>

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
          <div class="text-body-2 ability-tab__desc">{{ ability.description || '—' }}</div>
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
    <v-card-text v-else class="text-medium-emphasis">
      {{ abilities.length === 0 ? 'У персонажа нет способностей' : 'Способности не найдены' }}
    </v-card-text>
  </v-card>
</template>

<style scoped>
/* Компактные шапки панелей: меньше дефолтной высоты (48px) и вертикальных отступов. */
.ability-panels :deep(.v-expansion-panel-title) {
  min-height: 36px;
  padding-top: 2px;
  padding-bottom: 2px;
}

/* Переносы строк внутри описания способности. */
.ability-tab__desc {
  white-space: pre-line;
}
</style>
