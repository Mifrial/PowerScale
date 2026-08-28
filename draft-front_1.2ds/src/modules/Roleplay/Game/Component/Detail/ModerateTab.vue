<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { GAME_MEMBERSHIP_STATUS_LABEL } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { GAME_MEMBERSHIP_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_COLOR';
import { membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import { membershipMatchesGameRevision } from '@/modules/Roleplay/Game/Utils/membershipRevision';
import {
  versionConflicts,
  type ConflictChoices,
  type VersionConflict,
} from '@/modules/Roleplay/Game/Utils/reconcileVersion';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameModerationAction } from '@/modules/Roleplay/Game/Enum/GameModerationAction';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import MembershipDiffView from '@/modules/Roleplay/Game/Component/Detail/MembershipDiffView.vue';
import CharacterSubmissionSlider from '@/modules/Roleplay/Game/Component/Detail/CharacterSubmissionSlider.vue';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  spaceId: number | null;
  rulesRevision: number | null;
}>();

const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const allMemberships = ref<GameCharacterMembership[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

const names = ref<Map<string, string>>(new Map());

const namesRecord = computed<Record<string, string>>(() => Object.fromEntries(names.value));

const sliderOpen = ref(false);
const sliderTarget = ref<GameCharacterMembership | null>(null);

/** Выборы ведущего по конфликтным полям (key: characterId → choices). */
const conflictChoices = ref<Record<number, ConflictChoices>>({});

const pendingMemberships = computed(() =>
  allMemberships.value.filter((membership) => membership.membershipStatus === 'pending'),
);

function conflictsOf(
  membership: GameCharacterMembership,
  active: CharacterVersion | null,
  latest: CharacterVersion | null,
  overlay: GameCombatOverlay | null,
): VersionConflict[] {
  if (!latest || !active) return [];

  return versionConflicts(active, latest, overlay, conflictChoices.value[membership.characterId] ?? {}, resolve);
}

function setChoice(membership: GameCharacterMembership, field: string, choice: 'latest' | 'overlay'): void {
  const characterId = membership.characterId;
  const current = conflictChoices.value[characterId] ?? {};
  current[field] = choice;
  conflictChoices.value = { ...conflictChoices.value, [characterId]: { ...current } };
}

function resolve(ruleId: string): string {
  return names.value.get(ruleId) ?? `Удалённое правило (id: ${ruleId})`;
}

async function loadRules(): Promise<void> {
  const merged = new Map<string, string>();
  // Имена из ревизии игры (новой) — приоритетны.
  if (props.spaceId !== null && props.rulesRevision !== null) {
    try {
      const revision = await spaceRevisionStore.fetchRevision(props.spaceId, props.rulesRevision, signal.value);
      for (const rule of revision.rules) merged.set(rule.id, rule.name);
    } catch {
      // ревизия игры недоступна — остаёмся с именами из активных версий
    }
  }
  // Имена из ревизий активных версий (старых) — для правил, выведенных из новой ревизии.
  for (const membership of allMemberships.value) {
    if (!membership.activeVersion || props.spaceId === null) continue;
    try {
      const revision = await spaceRevisionStore.fetchRevision(
        props.spaceId,
        membership.activeVersion.rulesRevision,
        signal.value,
      );
      for (const rule of revision.rules) {
        if (!merged.has(rule.id)) merged.set(rule.id, rule.name);
      }
    } catch {
      // ревизия активной версии недоступна
    }
  }
  names.value = merged;
}

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    allMemberships.value = await getGameApi().getGameCharacters(props.gameId);
    await loadRules();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить модерацию';
  } finally {
    loading.value = false;
  }
}

async function moderate(membership: GameCharacterMembership, action: GameModerationAction): Promise<void> {
  error.value = null;
  try {
    await getGameApi().moderateCharacter(
      props.gameId,
      membership.characterId,
      action,
      conflictChoices.value[membership.characterId] ?? {},
    );
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось применить решение';
  }
}

function membershipStatusLabel(status: GameMembershipStatus): string {
  return GAME_MEMBERSHIP_STATUS_LABEL[status];
}

function membershipStatusColor(status: GameMembershipStatus): string {
  return GAME_MEMBERSHIP_STATUS_COLOR[status];
}

function characterStatusLabel(status: CharacterStatus): string {
  return CHARACTER_STATUS_OPTIONS.find((option) => option.value === status)?.label ?? status;
}

function characterStatusColor(status: CharacterStatus): string {
  return CHARACTER_STATUS_COLOR[status];
}

function canApprove(membership: GameCharacterMembership): boolean {
  return props.rulesRevision !== null && membershipMatchesGameRevision(membership, props.rulesRevision);
}

function openSlider(membership: GameCharacterMembership): void {
  sliderTarget.value = membership;
  sliderOpen.value = true;
}

// Перезагрузка при активации вкладки: подача персонажа происходит во вкладке «Персонажи».
watch(
  () => props.active,
  (value) => {
    if (value) void load();
  },
  { immediate: true },
);
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="d-flex justify-center pa-4">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <v-card v-for="membership in pendingMemberships" :key="membership.characterId">
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
          <v-chip :color="characterStatusColor(membership.characterStatus)" variant="tonal" size="x-small">
            {{ characterStatusLabel(membership.characterStatus) }}
          </v-chip>
          <v-chip :color="membershipStatusColor(membership.membershipStatus)" variant="tonal" size="x-small">
            {{ membershipStatusLabel(membership.membershipStatus) }}
          </v-chip>
        </div>

        <!-- Изменения листа: сгруппированный diff (по-элементный, с именами правил). -->
        <MembershipDiffView
          v-if="membership.activeVersion && membership.pendingVersion"
          :diff="membershipDiff(membership.activeVersion, membership.pendingVersion, resolve)"
          :names="namesRecord"
        />

        <!-- Конфликты карточка ↔ игра: поле изменили и latest, и оверлей → выбор ведущего (по умолчанию игра). -->
        <div
          v-if="conflictsOf(membership, membership.activeVersion, membership.latestVersion, membership.overlay).length"
          class="conflicts mt-3"
        >
          <div class="text-caption text-medium-emphasis mb-1">Конфликты «карточка ↔ игра»</div>
          <div
            v-for="conflict in conflictsOf(
              membership,
              membership.activeVersion,
              membership.latestVersion,
              membership.overlay,
            )"
            :key="conflict.field"
            class="conflict-row"
          >
            <span class="text-body-2">{{ conflict.label }}</span>
            <v-radio-group
              :model-value="conflict.choice"
              density="compact"
              hide-details
              class="conflict-options"
              @update:model-value="setChoice(membership, conflict.field, $event as 'latest' | 'overlay')"
            >
              <v-radio label="Карточка" value="latest" color="primary" />
              <v-radio label="Игра" value="overlay" color="primary" />
            </v-radio-group>
          </div>
        </div>

        <!-- Первая подача: лист открывается в слайдере как «добавленные» секции.
             Только при реально первой подаче (activeVersion === null); у одобренного персонажа
             после сессии activeVersion сохранён — там показывается дифф изменений, а не полный лист. -->
        <div v-if="!membership.activeVersion" class="first-submit" @click="openSlider(membership)">
          <v-icon icon="mdi-book-open-page-variant-outline" size="20" class="text-medium-emphasis" />
          <span class="text-body-2">Первая подача — полный лист</span>
          <v-spacer />
          <v-icon icon="mdi-chevron-right" size="20" class="text-medium-emphasis" />
        </div>

        <v-alert v-if="!canApprove(membership)" type="warning" variant="tonal" density="compact" class="mb-3">
          Ревизия персонажа не совпадает с ревизией игры — принять нельзя. Переведите лист или отклоните.
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
          <v-btn color="error" variant="tonal" size="small" @click="moderate(membership, 'reject')"> Отклонить </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <div v-if="!loading && pendingMemberships.length === 0" class="text-center text-medium-emphasis pa-6">
      Персонажей на модерации нет
    </div>
  </div>

  <CharacterSubmissionSlider v-model:open="sliderOpen" :membership="sliderTarget" :names="namesRecord" />
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
.conflicts {
  border: 1px solid rgba(var(--v-theme-warning), 0.35);
  border-radius: 8px;
  padding: 8px 12px;
  background: rgba(var(--v-theme-warning), 0.06);
}
.conflict-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.conflict-options {
  flex-direction: row;
  margin: 0;
  min-width: 200px;
}
</style>
