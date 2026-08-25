<script setup lang="ts">
import { computed } from 'vue';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { CHECK_HIT_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { formatPreparedMagnitude, HIT_MIN_SUCCESS_SIZE } from '@/modules/Roleplay/Rule/Utils/checkSuccessRating';
import { netSourceDelta } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';
import { resolveAppliedMechanicNames } from '@/modules/Roleplay/Game/Utils/appliedRollMechanics';
import { parseCombatEntityKey } from '@/modules/Roleplay/Game/Utils/combatCardModel';
import { injuryDifficultyDetailRows } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
import { rangedHitDifficultyDetailRows } from '@/modules/Roleplay/Game/Utils/rangedHitDifficultyRows';
import GameEntityChip from '@/modules/Roleplay/Game/Component/Chat/GameEntityChip.vue';

const props = defineProps<{
  attachment: ChatAttachment<DiceRollResult>;
  index: number;
  context?: { openEntity?: (key: string) => void };
}>();

const roll = computed(() => props.attachment.payload);
const sizeSuffix = computed(() => rollService.formatRollSize(roll.value.spec.dieSize || 0));
const netAdv = computed(() => netSourceDelta(roll.value.spec.advantages));
const check = computed(() => roll.value.check);
const checkMinSize = computed(() => (check.value?.check_code === CHECK_HIT_CODE ? HIT_MIN_SUCCESS_SIZE : undefined));
const successesLabel = computed(() =>
  formatPreparedMagnitude(
    { base: roll.value.totalSuccesses, size: roll.value.spec.dieSize || 0 },
    { minSize: checkMinSize.value, signed: true, foldNegative: !roll.value.injury },
  ),
);
const difficultyLabel = computed(() =>
  check.value ? formatPreparedMagnitude(check.value.difficulty, { minSize: checkMinSize.value }) : null,
);
const title = computed(() => roll.value.spec.label || (check.value ? 'Простая проверка' : `Бросок ${props.index + 1}`));
const actorSegment = computed((): Extract<InlineSegment, { kind: 'token' }> | null => {
  const key = roll.value.spec.actorKey;
  if (!key) return null;
  const parsed = parseCombatEntityKey(key);

  return { kind: 'token', type: parsed.kind, params: [String(parsed.id), title.value] };
});
const ratingClass = computed(() => {
  if (roll.value.injury) {
    return roll.value.injury.strength > 0 ? 'text-error' : 'text-medium-emphasis';
  }
  if (!check.value) return roll.value.totalSuccesses >= 0 ? 'text-success' : 'text-error';

  return check.value.passed ? 'text-success' : 'text-error';
});
const ratingText = computed(() => {
  if (roll.value.injury) {
    const injury = roll.value.injury;
    if (injury.strength <= 0) return 'увечья нет';

    return `Увечье: ${injury.strength}`;
  }
  if (check.value) return `${check.value.rating} РУ`;
  const total = roll.value.totalSuccesses;

  return `${total > 0 ? '+' : ''}${total}${sizeSuffix.value}`;
});
const advantageLines = computed(() =>
  roll.value.spec.advantages
    .filter((entry) => entry.delta !== 0)
    .map((entry) => ({
      label: entry.source_label || entry.source_code || 'без источника',
      delta: entry.delta,
    })),
);
const masteryLines = computed(() =>
  (roll.value.spec.masteryAdjustments ?? [])
    .filter((entry) => entry.delta !== 0)
    .map((entry) => ({
      label: entry.source_label || entry.source_code || 'без источника',
      delta: entry.delta,
    })),
);
const droppedNote = computed(() => {
  const n = roll.value.droppedRolls.length;
  if (!n) return null;
  const adj = n === 1 ? 'ий' : 'их';
  if (netAdv.value > 0) return `убрано ${n} худш${adj}`;
  if (netAdv.value < 0) return `убрано ${n} лучш${adj}`;

  return `убрано ${n}`;
});
const keptFaces = computed(() =>
  roll.value.adjustedRolls.map((face, i) => ({ face, success: roll.value.successes[i] ?? 0 })),
);
const appliedMechanicNames = computed(() => resolveAppliedMechanicNames(roll.value));
const injuryDifficultyRows = computed(() =>
  roll.value.injury?.breakdown ? injuryDifficultyDetailRows(roll.value.injury.breakdown) : [],
);
const rangedHitDifficultyRows = computed(() =>
  roll.value.check?.ranged_hit ? rangedHitDifficultyDetailRows(roll.value.check.ranged_hit) : [],
);

function signed(n: number): string {
  return n > 0 ? `+${n}` : String(n);
}

function dieFaceClass(success: number): string {
  if (success > 0) return 'good';
  if (success < 0) return 'bad';

  return '';
}
</script>

<template>
  <div class="chat-roll">
    <div class="chat-roll-header">
      <v-icon icon="mdi-dice-d6" size="16" class="chat-roll-dice" />
      <v-menu location="bottom start" :close-on-content-click="false">
        <template #activator="{ props: menuProps }">
          <v-btn
            v-bind="menuProps"
            class="chat-roll-info"
            icon="mdi-information-outline"
            size="x-small"
            variant="text"
            density="compact"
            aria-label="Подробности броска"
            @click.stop
          />
        </template>
        <v-card class="rounded border chat-roll-popup" elevation="3">
          <v-card-title class="text-body-2 py-3">
            <GameEntityChip v-if="actorSegment" :segment="actorSegment" :context="props.context" />
            <template v-else>{{ title }}</template>
          </v-card-title>
          <v-card-text class="pt-0 text-body-2">
            <div class="chat-roll-row">
              <span class="text-medium-emphasis">Пул</span>
              <span class="font-weight-medium">{{ rollService.formatPoolNotation(roll.spec) }}</span>
            </div>
            <div class="chat-roll-row">
              <span class="text-medium-emphasis">Эффективность</span>
              <span class="font-weight-medium">{{ rollService.formatEfficiencyLabel(roll.spec) }}</span>
            </div>
            <div v-if="check" class="chat-roll-row">
              <span class="text-medium-emphasis">Проверка</span>
              <span class="font-weight-medium">{{ check.check_code }}</span>
            </div>
            <div v-if="difficultyLabel != null" class="chat-roll-row">
              <span class="text-medium-emphasis">Сложность</span>
              <span class="font-weight-medium">{{ difficultyLabel }}</span>
            </div>
            <template v-if="rangedHitDifficultyRows.length">
              <div v-for="row in rangedHitDifficultyRows" :key="row.label" class="chat-roll-row">
                <span class="text-medium-emphasis">{{ row.label }}</span>
                <span class="font-weight-medium">{{ row.value }}</span>
              </div>
            </template>
            <template v-if="injuryDifficultyRows.length">
              <div v-for="row in injuryDifficultyRows" :key="row.label" class="chat-roll-row">
                <span class="text-medium-emphasis">{{ row.label }}</span>
                <span class="font-weight-medium">{{ row.value }}</span>
              </div>
            </template>
            <div v-if="check" class="chat-roll-row">
              <span class="text-medium-emphasis">Исход</span>
              <span class="font-weight-medium" :class="ratingClass">
                {{ check.passed ? 'успех' : 'провал' }} · {{ check.rating }} РУ
              </span>
            </div>
            <div class="chat-roll-row">
              <span class="text-medium-emphasis">Успехи</span>
              <span class="font-weight-medium">{{ successesLabel }}</span>
            </div>
            <div v-if="masteryLines.length" class="mt-2">
              <div class="text-medium-emphasis mb-1">К мастерству</div>
              <div v-for="(line, i) in masteryLines" :key="`m-${line.label}-${i}`" class="chat-roll-row">
                <span>{{ line.label }}</span>
                <span :class="line.delta > 0 ? 'text-success' : 'text-error'">{{ signed(line.delta) }}</span>
              </div>
            </div>
            <div v-if="advantageLines.length" class="mt-2">
              <div class="text-medium-emphasis mb-1">Преимущества / помехи</div>
              <div v-for="(line, i) in advantageLines" :key="`${line.label}-${i}`" class="chat-roll-row">
                <span>{{ line.label }}</span>
                <span :class="line.delta > 0 ? 'text-success' : 'text-error'">{{ signed(line.delta) }}</span>
              </div>
              <div class="chat-roll-row">
                <span class="text-medium-emphasis">Итого к пулу</span>
                <span
                  class="font-weight-medium"
                  :class="netAdv > 0 ? 'text-success' : netAdv < 0 ? 'text-error' : ''"
                  >{{ signed(netAdv) }}</span
                >
              </div>
            </div>
            <div v-if="appliedMechanicNames.length" class="mt-2">
              <div class="text-medium-emphasis mb-1">Применённые правила</div>
              <div v-for="name in appliedMechanicNames" :key="name">{{ name }}</div>
            </div>
            <div class="mt-2">
              <div class="text-medium-emphasis mb-1">Кубы</div>
              <div class="chat-roll-faces">
                <span v-for="(die, i) in keptFaces" :key="i"> {{ die.face }} → {{ signed(die.success) }}</span>
              </div>
              <div v-if="droppedNote" class="mt-1">{{ droppedNote }}: {{ roll.droppedRolls.join(', ') }}</div>
            </div>
          </v-card-text>
        </v-card>
      </v-menu>
      <span class="chat-roll-title">
        <GameEntityChip v-if="actorSegment" :segment="actorSegment" :context="props.context" />
        <template v-else>{{ title }}</template>
      </span>
      <span class="chat-roll-sep">·</span>
      <span class="font-weight-medium" :class="ratingClass">{{ ratingText }}</span>
    </div>
    <div class="chat-roll-detail">
      <span v-for="(s, si) in roll.successes" :key="si" class="roll-die" :class="dieFaceClass(s)">
        {{ roll.adjustedRolls[si] }}
      </span>
      <span
        v-for="(d, di) in roll.droppedRolls"
        :key="`drop-${di}`"
        class="roll-die roll-die-dropped"
        :title="droppedNote ?? undefined"
      >
        {{ d }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.chat-roll {
  background: rgba(var(--v-theme-primaryLight), 0.6);
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  border-radius: 8px;
  padding: 8px 12px;
  margin-top: 6px;
  margin-right: 5px;
  display: inline-block;
  text-align: left;
  max-width: 100%;
}

.chat-roll-header {
  font-size: 13px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2px;
  line-height: 1.3;
}

.chat-roll-dice {
  flex-shrink: 0;
}

.chat-roll-info {
  width: 22px;
  height: 22px;
  min-width: 22px;
  margin-inline: -2px;
}

.chat-roll-title {
  font-weight: 500;
}

.chat-roll-sep {
  margin: 0 2px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.chat-roll-popup {
  min-width: 260px;
  max-width: 360px;
}

.chat-roll-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 2px 0;
}

.chat-roll-faces {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 10px;
}

.chat-roll-detail {
  display: flex;
  gap: 4px;
  margin-top: 6px;
  flex-wrap: wrap;
}

.roll-die {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 600;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}
.roll-die.good {
  background: rgba(var(--v-theme-success), 0.1);
  border-color: rgb(var(--v-theme-success));
  color: rgb(var(--v-theme-success));
}
.roll-die.bad {
  background: rgba(var(--v-theme-error), 0.1);
  border-color: rgb(var(--v-theme-error));
  color: rgb(var(--v-theme-error));
}

.roll-die-dropped {
  opacity: 0.4;
  background: transparent;
  border-color: transparent;
  font-weight: 500;
}
</style>
