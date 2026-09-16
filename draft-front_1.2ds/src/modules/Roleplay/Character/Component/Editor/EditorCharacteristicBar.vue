<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { editorStatViewsService } from '@/modules/Roleplay/Character/Service/Instance/editorStatViewsService';
import EditorCharacteristicPopup from '@/modules/Roleplay/Character/Component/Editor/EditorCharacteristicPopup.vue';
import EditorAllCharacteristicsPopup from '@/modules/Roleplay/Character/Component/Editor/EditorAllCharacteristicsPopup.vue';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';

const props = defineProps<{
  model: CharacterEditorModel | null;
  build: CharacterBuild;
  rules: Rule[];
  keywords?: Keyword[];
}>();

const containerRef = ref<HTMLElement | null>(null);
const measurementRef = ref<HTMLElement | null>(null);
const visibleCount = ref(0);
let resizeObserver: ResizeObserver | null = null;

const stats = computed(() =>
  editorStatViewsService.buildEditorStatViews(props.model?.characteristics ?? [], props.rules),
);
const allStats = computed(() =>
  editorStatViewsService.buildAllEditorStatViews(props.model?.characteristics ?? [], props.rules),
);
const visibleStats = computed(() => stats.value.slice(0, visibleCount.value));
const proficiencyLevels = computed(() =>
  weaponProficiencyService.weaponProficiencyLevels(props.build.abilities, props.rules),
);

function recalculateVisibleStats(): void {
  void nextTick(() => {
    const container = containerRef.value;
    const measurement = measurementRef.value;
    if (!container || !measurement) return;

    const chips = [...measurement.querySelectorAll<HTMLElement>('[data-stat-measure]')];
    const allButton = measurement.querySelector<HTMLElement>('[data-all-stat-measure]');
    if (!allButton) {
      visibleCount.value = 0;

      return;
    }

    const availableWidth = Math.max(0, container.clientWidth - allButton.offsetWidth - 4);
    let usedWidth = 0;
    let count = 0;
    for (const chip of chips) {
      const nextWidth = usedWidth + (count > 0 ? 4 : 0) + chip.offsetWidth;
      if (nextWidth > availableWidth) break;

      usedWidth = nextWidth;
      count += 1;
    }
    visibleCount.value = count;
  });
}

onMounted(() => {
  resizeObserver = new ResizeObserver(recalculateVisibleStats);
  if (containerRef.value) resizeObserver.observe(containerRef.value);
  recalculateVisibleStats();
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  resizeObserver = null;
});

watch([stats, allStats], recalculateVisibleStats, { deep: true });
</script>

<template>
  <section v-if="allStats.length" ref="containerRef" class="editor-characteristic-bar" aria-label="Характеристики">
    <div class="editor-characteristic-bar__row">
      <div class="editor-characteristic-bar__visible">
        <v-menu v-for="stat in visibleStats" :key="stat.characteristic.code" location="bottom">
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
            :senses="model?.senses ?? []"
            :proficiency-levels="proficiencyLevels"
          />
        </v-menu>
      </div>

      <v-menu location="bottom" close-on-content-click>
        <template #activator="{ props: menuProps }">
          <v-chip
            v-bind="menuProps"
            size="small"
            density="compact"
            variant="outlined"
            class="all-stats-chip"
            title="Все характеристики"
          >
            <i class="mdi mdi-menu" aria-hidden="true" />
            <span>Все</span>
          </v-chip>
        </template>
        <EditorAllCharacteristicsPopup
          :stats="allStats"
          :rules="rules"
          :senses="model?.senses ?? []"
          :resources="model?.resources ?? []"
          :build="build"
          :keywords="keywords ?? []"
          :proficiency-levels="proficiencyLevels"
        />
      </v-menu>
    </div>

    <div ref="measurementRef" class="editor-characteristic-bar__measurement" aria-hidden="true">
      <v-chip
        v-for="stat in stats"
        :key="stat.characteristic.code"
        data-stat-measure
        size="small"
        density="compact"
        variant="tonal"
        class="stat-chip"
        :class="{ 'stat-chip-derived': stat.derived }"
      >
        {{ stat.characteristic.name }}: {{ new DimensionalNumber(stat.characteristic.value).toString() }}
      </v-chip>
      <v-chip data-all-stat-measure size="small" density="compact" variant="outlined" class="all-stats-chip">
        <i class="mdi mdi-menu" aria-hidden="true" />
        <span>Все</span>
      </v-chip>
    </div>
  </section>
</template>

<style scoped>
.editor-characteristic-bar {
  position: relative;
  min-width: 0;
  padding: 4px 8px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.editor-characteristic-bar__row,
.editor-characteristic-bar__visible,
.editor-characteristic-bar__measurement {
  display: flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
}

.editor-characteristic-bar__row {
  width: 100%;
  overflow: hidden;
}

.editor-characteristic-bar__visible {
  min-width: 0;
  overflow: hidden;
}

.editor-characteristic-bar__visible :deep(.v-chip),
.editor-characteristic-bar__row > :deep(.v-menu) {
  flex: 0 0 auto;
}

.editor-characteristic-bar__row > :deep(.v-menu:last-child) {
  margin-left: auto;
}

.editor-characteristic-bar__measurement {
  position: absolute;
  top: 4px;
  left: 8px;
  visibility: hidden;
  pointer-events: none;
  white-space: nowrap;
}

.editor-characteristic-bar :deep(.v-chip) {
  height: 22px;
  flex: 0 0 auto;
  font-size: 11px;
}

.editor-characteristic-bar :deep(.v-chip__content) {
  align-items: center;
  line-height: 1;
}

.all-stats-chip :deep(.v-chip__content) {
  gap: 4px;
}

.stat-chip-derived {
  border-color: rgba(var(--v-theme-primary), 0.45) !important;
}
</style>
