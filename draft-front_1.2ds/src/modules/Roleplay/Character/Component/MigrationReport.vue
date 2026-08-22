<script setup lang="ts">
import type { MigrationResult } from '@/modules/Roleplay/Character/Service/CharacterMigrationService';

const props = defineProps<{
  result: MigrationResult;
  /** Разрешение ruleId → имя правила (из целевой ревизии); fallback — ruleId. */
  resolve?: (ruleId: string) => string;
}>();

const KIND_LABEL: Record<MigrationResult['kind'], string> = {
  ok: 'Без изменений',
  resolved: 'Авто-резолв',
  conflicts: 'Требуется разрешение',
};

const KIND_COLOR: Record<MigrationResult['kind'], string> = {
  ok: 'success',
  resolved: 'info',
  conflicts: 'warning',
};

function labelOf(raw: string): string {
  if (!props.resolve) return raw;

  return props.resolve(raw);
}

const ZONE_LABEL: Record<string, string> = { os: 'ОС', or: 'ОР', ol: 'ОЛ' };

function costText(cost: number | null, zone: string | null): string {
  if (cost === null || zone === null) return '—';

  return `${cost} ${ZONE_LABEL[zone] ?? zone}`;
}

function abilityKindLabel(kind: string): string {
  if (kind === 'removed') return 'Сброшена';
  if (kind === 'added') return 'Добавлена';

  return 'Пересчитана';
}
</script>

<template>
  <div class="d-flex flex-column ga-3">
    <div v-if="result.convertedItems > 0" class="text-body-2">
      <v-icon icon="mdi-hammer-wrench" size="small" class="mr-1" />
      {{ result.convertedItems }} предмет(ов) превращено в кастомные «предметы мастера» (правило удалено из новой
      версии).
    </div>

    <div v-if="result.problems.filter((problem) => problem.kind !== 'lostCharacteristic').length > 0" class="mig-block">
      <div class="mig-block-title">Проблемы</div>
      <div
        v-for="(problem, index) in result.problems.filter((problem) => problem.kind !== 'lostCharacteristic')"
        :key="index"
        class="mig-row"
      >
        <v-icon icon="mdi-alert-circle-outline" size="small" color="error" class="mr-1" />
        <span class="text-body-2">{{ labelOf(problem.label) }}</span>
        <span class="text-caption text-medium-emphasis">— {{ problem.detail }}</span>
      </div>
    </div>

    <div v-if="result.problems.filter((problem) => problem.kind === 'lostCharacteristic').length > 0" class="mig-block">
      <div class="mig-block-title">Исчезнут</div>
      <div
        v-for="(problem, index) in result.problems.filter((problem) => problem.kind === 'lostCharacteristic')"
        :key="index"
        class="mig-row"
      >
        <v-icon icon="mdi-minus-circle-outline" size="small" color="warning" class="mr-1" />
        <span class="text-body-2">{{ labelOf(problem.label) }}</span>
        <span class="text-caption text-medium-emphasis">— {{ problem.detail }}</span>
      </div>
    </div>

    <div v-if="result.abilities.length > 0" class="mig-block">
      <div class="mig-block-title">Способности</div>
      <div v-for="(ability, index) in result.abilities" :key="index" class="mig-row">
        <v-icon
          icon="mdi-close-circle-outline"
          size="small"
          :color="ability.kind === 'removed' ? 'error' : ability.kind === 'added' ? 'success' : 'warning'"
          class="mr-1"
        />
        <span class="text-body-2 mig-label">{{ labelOf(ability.label) }}</span>
        <v-chip
          size="x-small"
          variant="tonal"
          :color="ability.kind === 'removed' ? 'error' : ability.kind === 'added' ? 'success' : 'warning'"
        >
          {{ abilityKindLabel(ability.kind) }}
        </v-chip>
        <span class="text-caption text-medium-emphasis">
          {{ costText(ability.costBefore, ability.zone) }} → {{ costText(ability.costAfter, ability.zone) }}
        </span>
      </div>
    </div>

    <div v-if="result.diffs.length > 0" class="mig-block">
      <div class="mig-block-title">Изменения (до → после)</div>
      <div v-for="(diff, index) in result.diffs" :key="index" class="mig-row">
        <span class="text-body-2 mig-label">{{ labelOf(diff.label) }}</span>
        <span class="text-body-2 text-medium-emphasis mig-strike">{{ diff.before }}</span>
        <span
          class="text-body-2"
          :class="diff.tone === 'red' ? 'text-error' : diff.tone === 'green' ? 'text-success' : 'text-primary'"
        >
          {{ diff.after }}
        </span>
        <span v-if="diff.explanation" class="text-caption text-medium-emphasis">— {{ diff.explanation }}</span>
      </div>
    </div>

    <div
      v-if="
        result.problems.length === 0 &&
        result.diffs.length === 0 &&
        result.convertedItems === 0 &&
        result.abilities.length === 0
      "
      class="text-medium-emphasis text-body-2"
    >
      Чистый перенос: ничего не изменилось.
    </div>

    <div class="d-flex align-center ga-2">
      <v-chip :color="KIND_COLOR[result.kind]" variant="tonal" size="small">
        {{ KIND_LABEL[result.kind] }}
      </v-chip>
    </div>
  </div>
</template>

<style scoped>
.mig-block {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  overflow: hidden;
}
.mig-block-title {
  padding: 6px 12px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  color: rgb(var(--v-theme-on-surface-variant));
  font-weight: 600;
  font-size: 0.8125rem;
}
.mig-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 6px 12px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.mig-label {
  min-width: 140px;
}
.mig-strike {
  text-decoration: line-through;
  opacity: 0.6;
}
</style>
