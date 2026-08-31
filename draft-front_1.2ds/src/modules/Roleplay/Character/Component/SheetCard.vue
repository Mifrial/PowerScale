<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/Space/init';
import { computed, ref, watch } from 'vue';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { SHEET_SECTION_LABELS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';

/**
 * Просмотр листа в контексте игры по видимым секциям (общий для персонажей-в-игре и НПС).
 * `visibleSections` вычисляет вызывающий через `visibleSheetSections` (зоны + роли).
 * Имена правил резолвятся из ревизии (`spaceId`/`rulesRevision`).
 */
const props = withDefaults(
  defineProps<{
    name: string;
    version: CharacterVersion | null;
    visibleSections: SheetSection[];
    spaceId: number | null;
    rulesRevision: number | null;
    /** Описания вне листа (НПС без version); приоритет — у version. */
    shortDescription?: string | null;
    fullDescription?: string | null;
  }>(),
  {
    shortDescription: null,
    fullDescription: null,
  },
);

const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();
const rules = ref<Rule[]>([]);

const rulesById = computed(() => new Map(rules.value.map((rule) => [rule.id, rule])));

const sheetAbilities = computed(() => {
  if (!props.version) return [];

  return characterOverviewService.build(props.version, rules.value).abilities;
});

function ruleName(ruleId: string | null): string | null {
  if (!ruleId) return null;

  return rulesById.value.get(ruleId)?.name ?? ruleId;
}

/** Имя предмета: правило или кастомный «предмет мастера» (ruleId null → name). */
function itemName(item: { ruleId: string | null; name?: string | null }): string {
  if (item.ruleId === null) return item.name ?? 'Предмет мастера';

  return ruleName(item.ruleId) ?? item.ruleId;
}

function sectionVisible(section: SheetSection): boolean {
  return props.visibleSections.includes(section);
}

function characteristicValue(ruleId: string): string {
  const value = props.version?.characteristics.find((characteristic) => characteristic.ruleId === ruleId);
  if (!value) return '';

  return DimensionalNumber.from(value.base).toString();
}

watch(
  () => [props.spaceId, props.rulesRevision, props.version] as const,
  ([spaceId, revision, version]) => {
    if (spaceId === null || revision === null || !version) {
      rules.value = [];

      return;
    }
    void spaceRevision
      .fetchRevision(spaceId, revision, signal.value)
      .then((revisionResult) => {
        rules.value = revisionResult.rules;
      })
      .catch(() => {
        rules.value = [];
      });
  },
  { immediate: true },
);
</script>

<template>
  <div class="d-flex flex-column ga-3">
    <h3 class="text-h6">{{ name }}</h3>

    <div v-if="sectionVisible('shortDescription')" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.shortDescription }}</div>
      <div class="text-body-2">{{ version?.shortDescription ?? props.shortDescription }}</div>
    </div>
    <div v-if="sectionVisible('fullDescription')" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.fullDescription }}</div>
      <div class="text-body-2">{{ version?.fullDescription ?? props.fullDescription }}</div>
    </div>

    <div v-if="sectionVisible('race') && version?.raceRuleId" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.race }}</div>
      <div class="text-body-2">{{ ruleName(version.raceRuleId) }}</div>
    </div>

    <div v-if="sectionVisible('characteristics') && version?.characteristics.length" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.characteristics }}</div>
      <div class="d-flex flex-column">
        <div v-for="characteristic in version.characteristics" :key="characteristic.ruleId" class="sheet-row">
          <span class="text-body-2">{{ ruleName(characteristic.ruleId) }}</span>
          <v-spacer />
          <span class="text-body-2">{{ characteristicValue(characteristic.ruleId) }}</span>
        </div>
      </div>
    </div>

    <div v-if="sectionVisible('resources') && version?.resources.length" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.resources }}</div>
      <div class="d-flex flex-column">
        <div v-for="resource in version.resources" :key="resource.ruleId" class="sheet-row">
          <span class="text-body-2">{{ ruleName(resource.ruleId) }}</span>
          <v-spacer />
          <span class="text-body-2">
            {{ DimensionalNumber.from(resource.current).toString() }} /
            {{ DimensionalNumber.from(resource.base).toString() }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="sectionVisible('abilities') && sheetAbilities.length" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.abilities }}</div>
      <div class="d-flex flex-column">
        <div v-for="ability in sheetAbilities" :key="ability.instanceKey" class="sheet-row">
          <span class="text-body-2">
            {{ ability.name }}
            <template v-if="ability.domainLabel"> · {{ ability.domainLabel }}</template>
          </span>
          <v-spacer />
          <span class="text-body-2">{{ ability.level > 0 ? `уровень ${ability.level}` : '' }}</span>
        </div>
      </div>
    </div>

    <div v-if="sectionVisible('inventory') && version?.inventory.length" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.inventory }}</div>
      <div class="d-flex flex-column">
        <div v-for="item in version.inventory" :key="item.id" class="sheet-row">
          <span class="text-body-2">{{ itemName(item) }}</span>
          <v-spacer />
          <span class="text-body-2">×{{ item.quantity }}</span>
        </div>
      </div>
    </div>

    <div v-if="sectionVisible('states') && version?.states.length" class="sheet-block">
      <div class="sheet-label">{{ SHEET_SECTION_LABELS.states }}</div>
      <div class="d-flex ga-1 flex-wrap">
        <v-chip v-for="state in version.states" :key="state.stateRuleId" size="x-small" variant="tonal">
          {{ ruleName(state.stateRuleId) }}
        </v-chip>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sheet-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sheet-label {
  font-size: 0.75rem;
  color: rgb(var(--v-theme-on-surface-variant));
}
.sheet-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>
