<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import EditorCharacteristicBar from '@/modules/Roleplay/Character/Component/Editor/EditorCharacteristicBar.vue';
import EditorStageRail from '@/modules/Roleplay/Character/Component/Editor/EditorStageRail.vue';
import { moneyBreakdownLabel } from '@/modules/Roleplay/Character/Utils/moneyBreakdown';

const props = defineProps<{
  model: CharacterEditorModel | null;
  build: CharacterBuild;
  activeTab: string;
  rules: Rule[];
  keywords?: Keyword[];
}>();

const emit = defineEmits<{
  'update:activeTab': [value: string];
}>();

const stageMainRef = ref<HTMLElement | null>(null);
const stageRailCollapsed = ref(false);

const budgets = computed(() => props.model?.budgets ?? null);

/** ОС, потраченные на шаге «Характеристики»: покупка характеристик + врождённые черты. */
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

  return list.filter((stage) => stage.key !== 'personality' || hasAgeRule);
});

function go(key: string): void {
  emit('update:activeTab', key);
}

function setStageRailCollapsed(collapsed: boolean): void {
  stageRailCollapsed.value = collapsed;
}

function updateInitialCollapse(): void {
  const width = stageMainRef.value?.clientWidth ?? 0;
  if (width > 0) stageRailCollapsed.value = width < 720;
}

onMounted(() => {
  updateInitialCollapse();
});
</script>

<template>
  <div class="editor-stage-nav">
    <EditorStageRail
      :stages="stages"
      :active-tab="activeTab"
      :collapsed="stageRailCollapsed"
      @update:collapsed="setStageRailCollapsed"
      @select="go"
    />

    <div ref="stageMainRef" class="editor-stage-main">
      <EditorCharacteristicBar :model="model" :build="build" :rules="rules" :keywords="keywords" />
      <slot />
    </div>
  </div>
</template>

<style scoped>
.editor-stage-nav {
  display: flex;
  align-items: stretch;
  min-height: calc(100dvh - var(--v-layout-top));
  min-width: 0;
  background: rgb(var(--v-theme-surface));
}

.editor-stage-main {
  display: flex;
  flex: 1 1 auto;
  min-width: 0;
  flex-direction: column;
}
</style>
