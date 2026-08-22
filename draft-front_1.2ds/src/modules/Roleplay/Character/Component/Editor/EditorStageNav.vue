<script setup lang="ts">
import { computed } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { buildEditorStatViews, buildAllEditorStatViews } from '@/modules/Roleplay/Character/Utils/editorStatViews';
import { moneyBreakdownLabel } from '@/modules/Roleplay/Character/Utils/moneyBreakdown';
import EditorCharacteristicPopup from '@/modules/Roleplay/Character/Component/Editor/EditorCharacteristicPopup.vue';
import EditorAllCharacteristicsPopup from '@/modules/Roleplay/Character/Component/Editor/EditorAllCharacteristicsPopup.vue';
import { weaponProficiencyLevels } from '@/modules/Roleplay/Character/Utils/weaponProficiency';

const props = defineProps<{
  model: CharacterEditorModel | null;
  build: CharacterBuild;
  activeTab: string;
  rules: Rule[];
}>();

const emit = defineEmits<{
  'update:activeTab': [value: string];
}>();

const budgets = computed(() => props.model?.budgets ?? null);

/** ОС, потраченные на шаге «Характеристики»: покупка характеристик + врождённые черты (параметры). */
const purchaseOs = computed(() => {
  const purchased = props.build.characteristicPurchases.reduce((sum, purchase) => sum + purchase.cost, 0);
  const innate = (props.model?.abilities ?? [])
    .filter((ability) => ability.characteristic && ability.level > 0)
    .reduce((sum, ability) => {
      const zone = ability.zones.find((entry) => entry.zoneCode === 'os');
      if (!zone) return sum;

      return sum + zone.levelCosts.slice(0, ability.level).reduce((total, cost) => total + cost, 0);
    }, 0);

  return purchased + innate;
});

const stages = computed(() => {
  const race = props.model?.race ?? null;
  const os = budgets.value?.os;
  const ol = budgets.value?.ol;
  const or = budgets.value?.or;
  const hasAgeRule = props.model?.personality.hasAgeRule ?? false;

  const list = [
    {
      key: 'race',
      title: race?.name ?? 'Раса',
      subtitle: race ? `${race.costOs} ОС` : 'не выбрана',
    },
    {
      key: 'characteristics',
      title: 'Характеристики',
      subtitle: race ? `ОС: ${purchaseOs.value}` : 'не выбрана',
    },
    {
      key: 'base',
      title: 'Основа',
      subtitle: os ? `ОС: ${os.total === null ? os.spent : `${os.spent} / ${os.total}`}` : 'ОС: —',
    },
    {
      key: 'personality',
      title: 'Личность',
      subtitle: ol ? `ОЛ: ${ol.total === null ? ol.spent : `${ol.spent} / ${ol.total}`}` : 'ОЛ: —',
    },
    {
      key: 'development',
      title: 'Развитие',
      subtitle: or ? `ОР: ${or.total === null ? or.spent : `${or.spent} / ${or.total}`}` : 'ОР: —',
    },
    {
      key: 'inventory',
      title: 'Инвентарь',
      subtitle: moneyBreakdownLabel(props.build.money),
      warned: budgets.value?.money?.exceeded ?? false,
    },
    {
      key: 'description',
      title: 'Описание',
      subtitle: props.build.name || 'Имя, внешность, история',
    },
  ];

  // ТР §7: если в ревизии нет ОЛ (правила возраста) — этап «Личность» пропускается.
  return list.filter((stage) => stage.key !== 'personality' || hasAgeRule);
});

const stats = computed(() => buildEditorStatViews(props.model?.characteristics ?? [], props.rules));
const allStats = computed(() => buildAllEditorStatViews(props.model?.characteristics ?? [], props.rules));

/** Уровни «Владения оружием» по семьям (для попапа мастерства оружий). */
const proficiencyLevels = computed(() => weaponProficiencyLevels(props.build.abilities, props.rules));

function go(key: string): void {
  emit('update:activeTab', key);
}
</script>

<template>
  <div class="editor-stage-nav">
    <button
      v-for="stage in stages"
      :key="stage.key"
      class="stage-block"
      :class="{ active: activeTab === stage.key, warned: stage.warned }"
      type="button"
      @click="go(stage.key)"
    >
      <span class="stage-title">{{ stage.title }}</span>
      <span class="stage-subtitle">{{ stage.subtitle }}</span>
    </button>

    <div class="stage-spacer" />

    <div v-if="stats.length" class="stats-block">
      <div class="stats-grid">
        <v-menu v-for="stat in stats" :key="stat.characteristic.code" location="bottom">
          <template #activator="{ props: menuProps }">
            <v-chip
              v-bind="menuProps"
              size="small"
              density="compact"
              variant="tonal"
              class="stat-chip"
              :class="{ 'stat-chip-derived': stat.derived }"
            >
              {{ stat.characteristic.name }}: {{ new DimensionalNumber(stat.characteristic.value).toString() }}
            </v-chip>
          </template>
          <EditorCharacteristicPopup
            :stat="stat"
            :rules="rules"
            :senses="props.model?.senses ?? []"
            :proficiency-levels="proficiencyLevels"
          />
        </v-menu>
        <v-menu v-if="allStats.length" location="bottom" close-on-content-click>
          <template #activator="{ props: menuProps }">
            <v-chip
              v-bind="menuProps"
              size="small"
              density="compact"
              variant="outlined"
              class="stat-chip all-stats-chip"
              title="Все характеристики"
            >
              <i class="mdi mdi-menu" aria-hidden="true" />
              <span>Все</span>
            </v-chip>
          </template>
          <EditorAllCharacteristicsPopup
            :stats="allStats"
            :rules="rules"
            :senses="props.model?.senses ?? []"
            :proficiency-levels="proficiencyLevels"
          />
        </v-menu>
      </div>
    </div>
  </div>
</template>

<style scoped>
.editor-stage-nav {
  display: flex;
  align-items: stretch;
  gap: 4px;
  flex-wrap: wrap;
  container-type: inline-size;
  background: rgb(var(--v-theme-surface));
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 6px 10px;
  margin-bottom: 16px;
}

.stage-block {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 2px;
  padding: 5px 14px;
  border-radius: 6px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: transparent;
  cursor: pointer;
  text-align: left;
}

.stage-block:hover {
  background: rgba(var(--v-theme-primary), 0.06);
}

.stage-block.active {
  background: rgba(var(--v-theme-primary), 0.12);
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.stage-block.warned {
  border-color: rgb(var(--v-theme-error));
}

.stage-block.warned .stage-subtitle {
  color: rgb(var(--v-theme-error));
}

.stage-title {
  font-weight: 500;
  font-size: 13px;
  line-height: 1.3;
}

.stage-subtitle {
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.72);
  line-height: 1.3;
}

.stage-spacer {
  flex: 1;
}

.stats-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  padding: 6px 8px;
  align-self: center;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, auto);
  justify-content: center;
  gap: 4px;
}

.stats-grid :deep(.v-chip) {
  height: 22px;
  font-size: 11px;
}

.stats-grid :deep(.v-chip .v-chip__content) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  text-align: center;
}

/* Чип «все» — условно-последний чип блока: как остальные, outlined. Пробел между иконкой и текстом
   задаётся на контенте чипа (gap на корне .v-chip до детей контента не доходит). */
.all-stats-chip :deep(.v-chip__content) {
  gap: 4px;
}

@container (max-width: 960px) {
  .stats-block {
    flex-basis: 100%;
    align-self: auto;
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  }
}

.stat-chip-derived {
  border-color: rgba(var(--v-theme-primary), 0.45) !important;
}
</style>
