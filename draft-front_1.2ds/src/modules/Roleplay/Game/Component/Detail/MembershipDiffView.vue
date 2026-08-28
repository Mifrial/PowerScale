<script setup lang="ts">
import { computed } from 'vue';
import type {
  MembershipDiff,
  DiffChange,
  CharacteristicDiffDetail,
  ResourceDiffDetail,
} from '@/modules/Roleplay/Game/Utils/membershipDiff';

const props = defineProps<{
  diff: MembershipDiff;
  /** Имена правил (ruleId → name) из ревизии игры; отсутствующие показываются как ruleId. */
  names: Record<string, string>;
}>();

const KIND_LABEL: Record<DiffChange['kind'], string> = {
  added: 'Добавлено',
  removed: 'Удалено',
  changed: 'Изменено',
};

const KIND_COLOR: Record<DiffChange['kind'], string> = {
  added: 'success',
  removed: 'error',
  changed: 'warning',
};

function resolve(ruleId: string): string {
  return props.names[ruleId] ?? `Удалённое правило (id: ${ruleId})`;
}

/** Label строки: ruleId → имя; для multiple-навыков (ruleId|домен) — имя + домен. */
function labelOf(raw: string): string {
  const [ruleId, domain] = raw.split('|');
  const name = resolve(ruleId);

  return domain ? `${name} (${domain})` : name;
}

/** Скалярное значение: ссылка на правило (раса) резолвится в имя. */
function scalarValue(key: string, value: string): string {
  if (key === 'race' && value !== '—') return resolve(value);

  return value;
}

function isCharacteristicDetail(detail: unknown): detail is CharacteristicDiffDetail {
  return typeof detail === 'object' && detail !== null && Array.isArray((detail as CharacteristicDiffDetail).modifiers);
}

function isResourceDetail(detail: unknown): detail is ResourceDiffDetail {
  return typeof detail === 'object' && detail !== null && Array.isArray((detail as ResourceDiffDetail).bonuses);
}

function signed(delta: number): string {
  return delta > 0 ? `+${delta}` : String(delta);
}

const sections = computed(() => props.diff.sections);
</script>

<template>
  <div class="d-flex flex-column ga-3">
    <div v-if="diff.scalars.length > 0" class="diff-block">
      <div class="diff-block-title">Основное</div>
      <div v-for="change in diff.scalars" :key="change.key" class="diff-row">
        <span class="diff-field">{{ change.label }}</span>
        <span class="diff-value diff-value--before">{{ scalarValue(change.key, change.before) }}</span>
        <span class="diff-value diff-value--after">{{ scalarValue(change.key, change.after) }}</span>
      </div>
    </div>

    <div v-for="section in sections" :key="section.key" class="diff-block">
      <div class="diff-block-title">
        <span>{{ section.label }}</span>
        <v-chip size="x-small" variant="tonal" class="ml-2">{{ section.changes.length }}</v-chip>
      </div>
      <div v-for="change in section.changes" :key="change.key" class="diff-row">
        <span class="diff-field diff-field--item">{{ labelOf(change.label) }}</span>
        <span class="diff-value diff-value--before">{{ change.before }}</span>
        <span class="diff-value diff-value--after">
          <v-menu
            v-if="isCharacteristicDetail(change.detail) || isResourceDetail(change.detail)"
            location="bottom"
            :close-on-content-click="false"
          >
            <template #activator="{ props: menuProps }">
              <span v-bind="menuProps" class="diff-clickable">{{ change.after }}</span>
            </template>

            <v-card class="rounded border" elevation="3" style="min-width: 260px; max-width: 420px">
              <v-card-text class="pt-3">
                <!-- Характеристика: итог/база/модификаторы (как в карточке персонажа). -->
                <template v-if="isCharacteristicDetail(change.detail)">
                  <div class="d-flex align-center justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Итог</span>
                    <span class="font-weight-medium">{{ change.detail.value }}</span>
                  </div>
                  <div class="d-flex align-center justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">База</span>
                    <span class="font-weight-medium">{{ change.detail.base }}</span>
                  </div>
                  <template v-if="change.detail.modifiers.length">
                    <div class="text-caption text-medium-emphasis mt-1 mb-1">Модификаторы</div>
                    <div
                      v-for="(modifier, index) in change.detail.modifiers"
                      :key="index"
                      class="d-flex align-center flex-wrap ga-2 py-1 text-body-2"
                    >
                      <span class="font-weight-medium">{{ signed(modifier.delta) }}</span>
                      <span class="text-medium-emphasis">|</span>
                      <span class="text-body-2">{{ modifier.name }}</span>
                      <span v-if="modifier.scope" class="text-caption text-medium-emphasis"
                        >условно: {{ modifier.scope }}</span
                      >
                    </div>
                  </template>
                </template>

                <!-- Ресурс: значение/лимит/база/бонусы (как в карточке персонажа). -->
                <template v-else-if="isResourceDetail(change.detail)">
                  <div class="d-flex align-center justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Значение</span>
                    <span class="font-weight-medium">{{ change.detail.current }}</span>
                  </div>
                  <div class="d-flex align-center justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">Лимит</span>
                    <span class="font-weight-medium">{{ change.detail.limit }}</span>
                  </div>
                  <div class="d-flex align-center justify-space-between py-1 text-body-2">
                    <span class="text-medium-emphasis">База лимита</span>
                    <span class="font-weight-medium">{{ change.detail.base }}</span>
                  </div>
                  <template v-if="change.detail.bonuses.length">
                    <div class="text-caption text-medium-emphasis mt-1 mb-1">Бонусы и штрафы лимита</div>
                    <div
                      v-for="(bonus, index) in change.detail.bonuses"
                      :key="index"
                      class="d-flex align-center flex-wrap ga-2 py-1 text-body-2"
                    >
                      <span class="font-weight-medium">{{ signed(bonus.delta) }}</span>
                      <span class="text-medium-emphasis">|</span>
                      <span class="text-body-2">{{ bonus.name }}</span>
                    </div>
                  </template>
                </template>
              </v-card-text>
            </v-card>
          </v-menu>

          <span v-else>{{ change.after }}</span>
        </span>
      </div>
    </div>

    <div v-if="diff.scalars.length === 0 && sections.length === 0" class="text-medium-emphasis text-body-2">
      Изменений не обнаружено.
    </div>

    <div class="d-flex flex-wrap ga-1">
      <v-chip v-for="kind in ['added', 'removed', 'changed'] as const" :key="kind" size="x-small" variant="tonal">
        <v-icon :color="KIND_COLOR[kind]" size="x-small" class="mr-1">mdi-circle-medium</v-icon>
        {{ KIND_LABEL[kind] }}
      </v-chip>
    </div>
  </div>
</template>

<style scoped>
.diff-block {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  overflow: hidden;
}
.diff-block-title {
  display: flex;
  align-items: center;
  padding: 6px 12px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  color: rgb(var(--v-theme-on-surface-variant));
  font-weight: 600;
  font-size: 0.8125rem;
}
.diff-row {
  display: grid;
  grid-template-columns: 180px 1fr 1fr;
  gap: 12px;
  padding: 6px 12px;
  font-size: 0.8125rem;
}
.diff-row:not(:last-child) {
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.diff-field {
  color: rgb(var(--v-theme-on-surface-variant));
}
.diff-field--item {
  font-weight: 600;
}
.diff-value {
  word-break: break-word;
}
.diff-value--before {
  text-decoration: line-through;
  opacity: 0.6;
}
.diff-value--after {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}
.characteristic-value {
  cursor: help;
  border-bottom: 1px dashed rgba(var(--v-theme-primary), 0.5);
}
.diff-clickable {
  cursor: pointer;
  border-bottom: 1px dashed rgba(var(--v-theme-primary), 0.5);
}
</style>
