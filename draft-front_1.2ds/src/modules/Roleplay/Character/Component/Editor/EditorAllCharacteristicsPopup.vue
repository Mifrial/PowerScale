<script setup lang="ts">
import { computed, ref } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import type { EditorStatView } from '@/modules/Roleplay/Character/Dto/Editor/EditorStatView';
import EditorCharacteristicPopup from '@/modules/Roleplay/Character/Component/Editor/EditorCharacteristicPopup.vue';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { EditorResourceView } from '@/modules/Roleplay/Character/Dto/Editor/EditorResourceView';
import EditorResourcePopup from '@/modules/Roleplay/Character/Component/Editor/EditorResourcePopup.vue';
import { editorResourceViewsService } from '@/modules/Roleplay/Character/Service/Instance/editorResourceViewsService';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { EditorCheckBonus } from '@/modules/Roleplay/Character/Dto/Editor/EditorCheckBonus';
import EditorCheckBonusPopup from '@/modules/Roleplay/Character/Component/Editor/EditorCheckBonusPopup.vue';
import { editorCheckBonusesService } from '@/modules/Roleplay/Character/Service/Instance/editorCheckBonusesService';

const props = defineProps<{
  /** Все характеристики (включая базовые), уже с resolved-правилами. */
  stats: EditorStatView[];
  rules: Rule[];
  senses: CharacterSenseValue[];
  resources: ResourceValue[];
  build: CharacterBuild;
  /** Уровни «Владения оружием» по семьям (для попапа мастерства оружий). */
  proficiencyLevels?: Map<string, number>;
}>();

const GROUP_ORDER: CharacteristicGroup[] = ['primary', 'combat', 'important', 'secondary', 'base'];

const GROUP_LABELS: Record<CharacteristicGroup, string> = {
  primary: 'Основные',
  combat: 'Боевые',
  important: 'Важные',
  secondary: 'Вторичные',
  base: 'Базовые',
};

function groupOf(stat: EditorStatView): CharacteristicGroup {
  return (stat.rule?.spec as CharacteristicSpec | undefined)?.group ?? 'primary';
}

const groups = computed(() =>
  GROUP_ORDER.map((group) => ({
    group,
    label: GROUP_LABELS[group],
    stats: props.stats.filter((s) => groupOf(s) === group),
  })).filter((entry) => entry.stats.length > 0),
);

const resourceViews = computed<EditorResourceView[]>(() =>
  editorResourceViewsService.build(props.resources, props.rules),
);

const checkBonuses = computed<EditorCheckBonus[]>(() => editorCheckBonusesService.build(props.build, props.rules));
const checksExpanded = ref(false);

function label(value: { base: number; size: number }): string {
  return new DimensionalNumber(value).toString();
}
</script>

<template>
  <v-card class="all-characteristics-card rounded border" elevation="3" :class="{ 'checks-expanded': checksExpanded }">
    <v-card-title class="d-flex align-center justify-space-between text-body-1">
      <span>Все характеристики</span>
      <v-btn
        icon
        size="x-small"
        variant="text"
        :aria-label="checksExpanded ? 'Скрыть бонусы к проверкам' : 'Показать бонусы к проверкам'"
        @click.stop="checksExpanded = !checksExpanded"
      >
        <v-icon :icon="checksExpanded ? 'mdi-chevron-left' : 'mdi-chevron-right'" size="22" />
      </v-btn>
    </v-card-title>
    <v-card-text class="pt-0 all-content">
      <div class="characteristics-column">
        <template v-for="entry in groups" :key="entry.group">
          <div class="text-caption text-medium-emphasis stat-group-label">{{ entry.label }}</div>
          <div class="stat-group-grid">
            <v-menu
              v-for="stat in entry.stats"
              :key="stat.characteristic.code"
              location="right top"
              open-on-hover
              :close-on-content-click="false"
            >
              <template #activator="{ props: menuProps }">
                <div v-bind="menuProps" class="d-flex align-center justify-space-between ga-3 stat-row">
                  <span class="text-body-2 text-medium-emphasis text-truncate">{{ stat.characteristic.name }}</span>
                  <span class="text-body-2 font-weight-medium">{{ label(stat.characteristic.value) }}</span>
                </div>
              </template>
              <EditorCharacteristicPopup
                :stat="stat"
                :rules="rules"
                :senses="props.senses"
                :proficiency-levels="props.proficiencyLevels"
              />
            </v-menu>
          </div>
        </template>
        <div v-if="!groups.length" class="text-medium-emphasis">Характеристик нет</div>

        <v-divider class="my-3" />
        <div class="text-caption text-medium-emphasis mb-1">Ресурсы</div>
        <div v-if="resourceViews.length" class="resource-grid">
          <v-menu
            v-for="resource in resourceViews"
            :key="resource.ruleCode"
            location="right top"
            open-on-hover
            :close-on-content-click="false"
          >
            <template #activator="{ props: menuProps }">
              <div v-bind="menuProps" class="d-flex align-center justify-space-between ga-3 resource-row">
                <span class="text-body-2 text-medium-emphasis text-truncate">{{ resource.name }}</span>
                <span class="text-body-2 font-weight-medium">
                  {{ label(resource.current) }} / {{ label(resource.max) }}
                </span>
              </div>
            </template>
            <EditorResourcePopup :resource="resource" />
          </v-menu>
        </div>
        <div v-else class="text-medium-emphasis">Ресурсов нет</div>
      </div>
      <div v-if="checksExpanded" class="checks-column">
        <div class="text-caption text-medium-emphasis mb-1">Бонусы к проверкам</div>
        <div v-if="checkBonuses.length" class="check-grid">
          <v-menu
            v-for="bonus in checkBonuses"
            :key="bonus.checkCode"
            location="right top"
            open-on-hover
            :close-on-content-click="false"
          >
            <template #activator="{ props: menuProps }">
              <div v-bind="menuProps" class="d-flex align-center justify-space-between ga-3 check-row">
                <span class="text-body-2 text-medium-emphasis text-truncate">{{ bonus.checkName }}</span>
                <span class="text-body-2 font-weight-medium">{{
                  bonus.delta > 0 ? `+${bonus.delta}` : bonus.delta
                }}</span>
              </div>
            </template>
            <EditorCheckBonusPopup :bonus="bonus" :rules="rules" />
          </v-menu>
        </div>
        <div v-else class="text-medium-emphasis">Бонусов нет</div>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.all-characteristics-card {
  width: min(560px, calc(100vw - 32px));
  min-width: 300px;
  max-width: 860px;
  transition: width 180ms ease;
}

.all-content {
  display: flex;
  gap: 16px;
}

.characteristics-column {
  min-width: 300px;
}

.checks-column {
  min-width: 220px;
  padding-left: 16px;
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.checks-expanded {
  width: min(760px, calc(100vw - 32px));
}

.stat-group-label {
  margin-top: 8px;
}

.stat-group-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(150px, 1fr));
  gap: 2px 16px;
}

.stat-row {
  cursor: pointer;
  border-radius: 6px;
  padding: 2px 6px;
  margin: 0 -6px;
}

.stat-row:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.resource-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(150px, 1fr));
  gap: 2px 16px;
}

.resource-row {
  cursor: pointer;
  border-radius: 6px;
  padding: 2px 6px;
  margin: 0 -6px;
}

.resource-row:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.check-grid {
  display: grid;
  gap: 2px;
}

.check-row {
  cursor: pointer;
  border-radius: 6px;
  padding: 2px 6px;
  margin: 0 -6px;
}

.check-row:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}
</style>
