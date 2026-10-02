<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { computed, onUnmounted, ref, watch } from 'vue';
import { getGameApi, getGameRealtimePort } from '@/modules/Roleplay/Game/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { GAME_MEMBERSHIP_STATUS_LABEL } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { GAME_MEMBERSHIP_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import { membershipMatchesGameRevision } from '@/modules/Roleplay/Game/Utils/membershipRevision';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { CharacterModerationProjection } from '@/modules/Roleplay/Game/Dto/CharacterModerationProjection';
import type { GameCharacterModerationAction } from '@/modules/Roleplay/Game/Enum/GameCharacterModerationAction';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';
import MembershipDiffView from '@/modules/Roleplay/Game/Component/Detail/MembershipDiffView.vue';
import CharacterSubmissionSlider from '@/modules/Roleplay/Game/Component/Detail/CharacterSubmissionSlider.vue';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  spaceId: number | null;
  rulesRevision: number | null;
}>();

const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();

const allMemberships = ref<GameCharacterMembership[]>([]);
const actualById = ref<Record<number, CharacterVersion>>({});
const moderationById = ref<Record<number, CharacterModerationProjection>>({});
const loading = ref(false);
const error = ref<string | null>(null);
let loadSequence = 0;
let stopRealtimeSubscription: (() => void) | null = null;
let realtimeCursor = 0;
let realtimeApplyChain = Promise.resolve();

const names = ref<Map<string, string>>(new Map());

const namesRecord = computed<Record<string, string>>(() => Object.fromEntries(names.value));

const sliderOpen = ref(false);
const sliderTarget = ref<GameCharacterMembership | null>(null);

const queueMemberships = computed(() =>
  allMemberships.value.filter((membership) => {
    if (membership.membershipStatus === 'submitted') return true;
    if (membership.membershipStatus !== 'active') return false;
    if (membership.reviewState === 'returned') return true;

    return moderationById.value[membership.characterId]?.diff?.hasChanges ?? false;
  }),
);

function resolve(ruleCode: string): string {
  return names.value.get(ruleCode) ?? `Удалённое правило (id: ${ruleCode})`;
}

function actualOf(membership: GameCharacterMembership): CharacterVersion | null {
  return actualById.value[membership.characterId] ?? null;
}

async function loadRules(): Promise<void> {
  const merged = new Map<string, string>();
  if (props.spaceId !== null && props.rulesRevision !== null) {
    try {
      const revision = await spaceRevision.fetchRevision(props.spaceId, props.rulesRevision, signal.value);
      for (const rule of revision.rules) merged.set(rule.code, rule.name);
    } catch {
      // ревизия игры недоступна
    }
  }
  for (const membership of allMemberships.value) {
    const approved = membership.approvedCharacterVersion;
    if (!approved || props.spaceId === null) continue;
    try {
      const revision = await spaceRevision.fetchRevision(props.spaceId, approved.rulesRevision, signal.value);
      for (const rule of revision.rules) {
        if (!merged.has(rule.code)) merged.set(rule.code, rule.name);
      }
    } catch {
      // ревизия approved недоступна
    }
  }
  names.value = merged;
}

async function load(): Promise<void> {
  const requestSequence = ++loadSequence;
  loading.value = true;
  error.value = null;
  try {
    const membershipResult = await getGameApi().getGameCharacters(props.gameId);
    if (requestSequence !== loadSequence) return;
    const moderationProjections = await getGameApi().getCharacterModerationProjections(
      props.gameId,
      membershipResult.map((membership) => membership.characterId),
      signal.value,
    );
    if (requestSequence !== loadSequence) return;
    allMemberships.value = membershipResult;
    const actuals: Record<number, CharacterVersion> = Object.fromEntries(
      moderationProjections
        .filter(
          (projection): projection is CharacterModerationProjection & { actualCharacterVersion: CharacterVersion } =>
            projection.actualCharacterVersion !== null,
        )
        .map((projection) => [projection.characterId, projection.actualCharacterVersion]),
    );
    actualById.value = actuals;
    moderationById.value = Object.fromEntries(
      moderationProjections.map((projection) => [projection.characterId, projection]),
    );
    await loadRules();
    connectRealtime();
  } catch (e) {
    if (requestSequence === loadSequence) {
      error.value = e instanceof Error ? e.message : 'Не удалось загрузить модерацию';
    }
  } finally {
    if (requestSequence === loadSequence) loading.value = false;
  }
}

async function moderate(membership: GameCharacterMembership, action: GameCharacterModerationAction): Promise<void> {
  error.value = null;
  try {
    const actualVersion = moderationById.value[membership.characterId]?.actualVersion;
    if (actualVersion === null || actualVersion === undefined) {
      throw new Error('Не удалось определить актуальную версию персонажа');
    }
    const result = await getGameApi().moderateCharacterCommand({
      commandId: createRandomId(),
      gameId: props.gameId,
      characterId: membership.characterId,
      action,
      expectedActualVersion: actualVersion,
      expectedMembershipRevision: membership.membershipRevision,
    });
    if (result.kind === 'conflict') {
      throw new Error(`Модерация не применена: ${result.conflict.code}`);
    }
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось применить решение';
  }
}

async function refreshModerationProjection(characterId: number): Promise<boolean> {
  const projections = await getGameApi().getCharacterModerationProjections(props.gameId, [characterId], signal.value);
  const projection = projections[0];
  if (!projection) return false;

  moderationById.value = { ...moderationById.value, [characterId]: projection };
  if (projection.actualCharacterVersion) {
    actualById.value = { ...actualById.value, [characterId]: projection.actualCharacterVersion };
  }
  allMemberships.value = allMemberships.value.map((membership) =>
    membership.characterId === characterId && membership.reviewState !== 'returned'
      ? { ...membership, reviewState: projection.diff?.hasChanges ? 'changes_pending' : 'clean' }
      : membership,
  );

  return true;
}

function onRealtimeEvent(event: GameRealtimeEvent): void {
  if (event.gameId !== props.gameId || event.eventKind !== 'character.changed') return;
  const [kind, rawId] = event.entityKey.split(':');
  const characterId = Number(rawId);
  if (kind !== 'character' || !Number.isInteger(characterId)) return;
  realtimeApplyChain = realtimeApplyChain
    .then(async () => {
      if (event.cursor <= realtimeCursor) return;
      const refreshed = await refreshModerationProjection(characterId);
      if (!refreshed) {
        realtimeCursor = 0;
        await load();

        return;
      }
      realtimeCursor = event.cursor;
    })
    .catch(async (reason: unknown) => {
      error.value = reason instanceof Error ? reason.message : 'Не удалось обновить модерацию персонажа';
      realtimeCursor = 0;
      await load();
    });
}

function connectRealtime(): void {
  stopRealtimeSubscription?.();
  stopRealtimeSubscription = props.active
    ? getGameRealtimePort().subscribe(props.gameId, onRealtimeEvent, (realtimeError) => {
        error.value = realtimeError.message;
      })
    : null;
}

function membershipStatusLabel(status: GameMembershipStatus): string {
  return GAME_MEMBERSHIP_STATUS_LABEL[status];
}

function membershipStatusColor(status: GameMembershipStatus): string {
  return GAME_MEMBERSHIP_STATUS_COLOR[status];
}

function reviewChip(membership: GameCharacterMembership): { color: string; label: string } | null {
  if (membership.reviewState === 'returned') return { color: 'warning', label: 'На доработку' };
  if (membership.reviewState === 'changes_pending') return { color: 'info', label: 'Есть изменения' };

  return null;
}

function canApprove(membership: GameCharacterMembership): boolean {
  return props.rulesRevision !== null && membershipMatchesGameRevision(actualOf(membership), props.rulesRevision);
}

function openSlider(membership: GameCharacterMembership): void {
  sliderTarget.value = membership;
  sliderOpen.value = true;
}

watch(
  () => props.active,
  (value) => {
    if (value) {
      void load();
    } else {
      stopRealtimeSubscription?.();
      stopRealtimeSubscription = null;
    }
  },
  { immediate: true },
);

watch(
  () => props.gameId,
  () => {
    realtimeCursor = 0;
    realtimeApplyChain = Promise.resolve();
    stopRealtimeSubscription?.();
    stopRealtimeSubscription = null;
    if (props.active) void load();
  },
);

onUnmounted(() => {
  stopRealtimeSubscription?.();
  stopRealtimeSubscription = null;
});
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="d-flex justify-center pa-4">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <v-card v-for="membership in queueMemberships" :key="membership.characterId">
      <v-card-text>
        <div class="d-flex align-center ga-2 mb-3">
          <v-avatar color="primary" size="32" variant="tonal" class="flex-shrink-0">
            <span class="text-body-2">{{ membership.characterName.charAt(0) }}</span>
          </v-avatar>
          <div class="d-flex flex-column">
            <router-link :to="`/characters/${membership.characterId}`" class="text-body-2 text-decoration-none">
              {{ membership.characterName }}
            </router-link>
            <span class="text-caption text-medium-emphasis">владелец: {{ membership.characterOwnerName }}</span>
          </div>
          <v-spacer />
          <v-chip v-if="reviewChip(membership)" :color="reviewChip(membership)!.color" variant="tonal" size="x-small">
            {{ reviewChip(membership)!.label }}
          </v-chip>
          <v-chip :color="membershipStatusColor(membership.membershipStatus)" variant="tonal" size="x-small">
            {{ membershipStatusLabel(membership.membershipStatus) }}
          </v-chip>
        </div>

        <MembershipDiffView
          v-if="membership.approvedCharacterVersion && actualOf(membership)"
          :diff="membershipDiff(membership.approvedCharacterVersion, actualOf(membership), resolve)"
          :names="namesRecord"
        />

        <div v-if="!membership.approvedCharacterVersion" class="first-submit" @click="openSlider(membership)">
          <v-icon icon="mdi-book-open-page-variant-outline" size="20" class="text-medium-emphasis" />
          <span class="text-body-2">Первая подача — полный лист</span>
          <v-spacer />
          <v-icon icon="mdi-chevron-right" size="20" class="text-medium-emphasis" />
        </div>

        <v-alert v-if="!canApprove(membership)" type="warning" variant="tonal" density="compact" class="mb-3">
          Ревизия персонажа не совпадает с ревизией игры — принять нельзя. Переведите лист или отклоните заявку.
        </v-alert>
        <div class="d-flex justify-end ga-2 mt-3">
          <v-btn
            color="success"
            variant="tonal"
            size="small"
            :disabled="!canApprove(membership)"
            @click="moderate(membership, 'approve')"
          >
            Принять
          </v-btn>
          <v-btn color="warning" variant="tonal" size="small" @click="moderate(membership, 'returnForRework')">
            Вернуть
          </v-btn>
          <v-btn
            v-if="membership.membershipStatus === 'submitted'"
            color="error"
            variant="tonal"
            size="small"
            @click="moderate(membership, 'rejectApplication')"
          >
            Отклонить
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <div v-if="!loading && queueMemberships.length === 0" class="text-center text-medium-emphasis pa-6">
      Персонажей на модерации нет
    </div>
  </div>

  <CharacterSubmissionSlider
    v-model:open="sliderOpen"
    :membership="sliderTarget"
    :actual="sliderTarget ? actualOf(sliderTarget) : null"
    :names="namesRecord"
  />
</template>

<style scoped>
.first-submit {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  cursor: pointer;
}
.first-submit:hover {
  background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
