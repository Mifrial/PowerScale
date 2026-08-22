<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { buildChatRulesContext } from '@/modules/Roleplay/Game/Utils/chatRulesContext';
import { canStartGame, canStopSession } from '@/modules/Roleplay/Game/Utils/gameStatusTransitions';
import { toCreateGameData } from '@/modules/Roleplay/Game/Utils/toCreateGameData';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { ChatSpeakerOption } from '@/modules/Messages/Chat/Dto/ChatSpeakerOption';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import ChatThread from '@/modules/Messages/Chat/Component/ChatThread.vue';
import InitiativeTrack from '@/modules/Roleplay/Game/Component/InitiativeTrack.vue';
import CombatQuickRolls from '@/modules/Roleplay/Game/Component/CombatQuickRolls.vue';
import CombatCardPanel from '@/modules/Roleplay/Game/Component/Detail/CombatCardPanel.vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

const props = defineProps<{
  /** Активна ли вкладка: чат монтируется только при открытии (D7 — освобождает глобальный чат при уходе). */
  active: boolean;
  detail: GameDetail;
  canEdit: boolean;
}>();

const userStore = useUserStore();
const store = useGameStore();
const spaceRevisionStore = useSpaceRevisionStore();

const chatId = computed(() => props.detail.gameChatId);
const gameId = computed(() => props.detail.game.id);

// Кнопки статуса — в глобальный топбар (#editor-actions), видны только на этой вкладке.
const statusUpdating = ref(false);
const statusError = ref<string | null>(null);

const showStartGame = computed(() => props.canEdit && canStartGame(props.detail.game.status));
const showStopSession = computed(() => props.canEdit && canStopSession(props.detail.game.status));

// «Начать сессию» → playing, «Остановить сессию» → in_process (межсессионный период). Сессия не
// трогает терминальный статус игры (completed ставится отдельно — селектором в форме игры).
// При остановке сессии боевые изменения (оверлей) персонажей собираются в pendingVersion на модерацию (CD-2).
async function changeStatus(target: GameStatus): Promise<void> {
  statusUpdating.value = true;
  statusError.value = null;
  try {
    const updated = await getGameApi().updateGame(gameId.value, { ...toCreateGameData(props.detail), status: target });
    store.applyGameUpdate(updated);
    if (target === 'in_process' || target === 'completed') {
      await getGameApi().submitCombatChanges(gameId.value);
    }
  } catch (e) {
    statusError.value = e instanceof Error ? e.message : 'Не удалось изменить статус';
  } finally {
    statusUpdating.value = false;
  }
}

// Ревизия игры: правила и механики → контекст чата (чипы [[rule:...]], «Вставить ссылку»,
// броски через RollEngine). Собирается общим buildChatRulesContext.
const revisionRules = ref<Rule[]>([]);
const mechanics = ref<Mechanic[]>([]);

const rulesContext = computed(() =>
  buildChatRulesContext(
    revisionRules.value,
    mechanics.value,
    props.detail?.game.spaceId,
    props.detail?.game.rulesRevision,
  ),
);

const tokenSources = computed<ITokenSource[]>(() => rulesContext.value.tokenSources);

const processAttachments = (attachments: ChatAttachment[]): ChatAttachment[] =>
  rulesContext.value.processAttachments(attachments);

const memberships = ref<GameCharacterMembership[]>([]);
const npcs = ref<GameNpc[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);

// Макросы быстрых бросков per entityKey (CD-8): звёздочка в карточке и блок в сайдбаре.
const quickRolls = ref<Record<string, string[]>>({});

// «От лица кого» писать: игрок — только свои approved-персонажи; ведущий — роль ведущего,
// свои approved-персонажи и НПС игры (ТР §8 «Чат игры»).
const speakerOptions = computed<ChatSpeakerOption[]>(() => {
  const user = userStore.currentUser;
  if (!user) return [];
  const ownCharacters = memberships.value
    .filter((membership) => membership.membershipStatus === 'approved' && membership.characterOwnerId === user.id)
    .map<ChatSpeakerOption>((membership) => ({
      key: `character:${membership.characterId}`,
      label: membership.characterName,
      speaker: { kind: 'character', characterId: membership.characterId, characterName: membership.characterName },
    }));

  if (!props.canEdit) return ownCharacters;

  return [
    { key: 'gm', label: 'Ведущий', speaker: { kind: 'gm' } },
    ...ownCharacters,
    ...npcs.value
      .filter((npc) => npc.status === 'active')
      .map<ChatSpeakerOption>((npc) => ({
        key: `npc:${npc.id}`,
        label: npc.name,
        speaker: { kind: 'npc', npcId: npc.id, npcName: npc.name },
      })),
  ];
});

async function load(): Promise<void> {
  loading.value = true;
  loadError.value = null;
  try {
    memberships.value = await getGameApi().getGameCharacters(gameId.value);
    npcs.value = await getGameApi().getNpcs(gameId.value);
    quickRolls.value = await getGameApi().getQuickRolls(gameId.value);
    mechanics.value = await getRuleApi().getMechanics();
    await loadRevision();
  } catch (e) {
    loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить данные чата';
  } finally {
    loading.value = false;
  }
}

// Правила ревизии игры: чипы и источники «Вставить ссылку» резолвятся из неё (D72).
async function loadRevision(): Promise<void> {
  const detail = props.detail;
  try {
    const revision = await spaceRevisionStore.fetchRevision(detail.game.spaceId, detail.game.rulesRevision);
    revisionRules.value = revision.rules;
  } catch {
    revisionRules.value = [];
  }
}

watch(
  () => props.active,
  (value) => {
    if (value) void load();
  },
  { immediate: true },
);

// Авто-переключение селектора «от лица кого» на активного персонажа инициативы:
// только если этот источник речи доступен текущему пользователю (его персонаж/НПС/роль).
// Канонический выбор живёт здесь (ChatInput эмитит ручной выбор обратно) — его же
// использует блок быстрых бросков вместо собственного селектора.
const activeSpeakerKey = ref<string | null>(null);
const lastTurnId = ref<string | null>(null);

function applySpeakerKey(): void {
  const id = lastTurnId.value;
  if (id !== null && speakerOptions.value.some((item) => item.key === id)) {
    activeSpeakerKey.value = id;
  }
  // Ход недоступен/нет хода — сохраняем текущий выбор (ручной/прежний авто).
}

function onSpeakerKeyChange(key: string | null): void {
  activeSpeakerKey.value = key;
}

function onTurn(participantId: string | null): void {
  lastTurnId.value = participantId;
  applySpeakerKey();
}

// Боевая карточка: слайд-овер по клику на участника шкалы инициативы (CD-5).
const cardOpen = ref(false);
const cardKey = ref<CombatEntityKey | null>(null);

// Ревизия оверлеев — счётчик мутаций боевых изменений: шкала инициативы перечитывает
// оверлеи (Истощение) после правок в боевой карточке.
const overlayRevision = ref(0);

function onOverlayChanged(): void {
  overlayRevision.value += 1;
}

function onOpenCard(entityKey: string): void {
  cardKey.value = entityKey as CombatEntityKey;
  cardOpen.value = true;
}

function onCloseCard(): void {
  cardOpen.value = false;
  cardKey.value = null;
}

// Действие у селектора «от лица кого»: открыть карточку выбранного участника (CD-5).
const speakerAction = computed<{ icon: string; title: string; onClick: () => void } | null>(() => {
  const key = activeSpeakerKey.value;
  if (key === null || key === 'gm') return null;

  return { icon: 'mdi-card-account-details-outline', title: 'Открыть карточку', onClick: () => onOpenCard(key) };
});

// Добавить/убрать макрос быстрого броска (CD-8): результат хранилища — актуальный список.
async function toggleQuickRoll(entityKey: string, ruleId: string): Promise<void> {
  const key = entityKey as CombatEntityKey;
  try {
    const current = quickRolls.value[key] ?? [];
    const next = current.includes(ruleId)
      ? await getGameApi().removeQuickRoll(gameId.value, key, ruleId)
      : await getGameApi().addQuickRoll(gameId.value, key, ruleId);
    quickRolls.value = { ...quickRolls.value, [key]: next };
  } catch {
    // Прототип: сбой тумблера не блокирует интерфейс (запись остаётся прежней).
  }
}

watch(speakerOptions, () => applySpeakerKey());
</script>

<template>
  <Teleport to="#editor-actions">
    <v-btn
      v-if="showStartGame"
      variant="tonal"
      color="success"
      size="small"
      prepend-icon="mdi-play"
      :loading="statusUpdating"
      @click="changeStatus('playing')"
    >
      Начать сессию
    </v-btn>
    <v-btn
      v-if="showStopSession"
      variant="tonal"
      color="warning"
      size="small"
      prepend-icon="mdi-stop"
      :loading="statusUpdating"
      @click="changeStatus('in_process')"
    >
      Остановить сессию
    </v-btn>
  </Teleport>

  <div class="game-chat-tab">
    <v-alert v-if="statusError" type="error" variant="tonal" density="compact" class="mb-3">{{ statusError }}</v-alert>
    <v-alert v-if="loadError" type="error" variant="tonal" density="compact" class="mb-3">{{ loadError }}</v-alert>

    <div class="game-chat-body">
      <div class="game-chat-sidebar">
        <InitiativeTrack
          :game-id="gameId"
          :space-id="detail.game.spaceId"
          :chat-id="chatId"
          :can-edit="canEdit"
          :characters="memberships"
          :npcs="npcs"
          :rules="revisionRules"
          :mechanics="mechanics"
          :overlay-revision="overlayRevision"
          class="game-chat-sidebar__initiative"
          @turn="onTurn"
          @open-card="onOpenCard"
        />

        <CombatQuickRolls
          :game-id="gameId"
          :chat-id="chatId"
          :can-edit="canEdit"
          :current-user-id="userStore.currentUser?.id ?? null"
          :memberships="memberships"
          :npcs="npcs"
          :rules="revisionRules"
          :mechanics="mechanics"
          :quick-rolls="quickRolls"
          :active-entity-key="activeSpeakerKey"
          class="game-chat-sidebar__quickrolls"
          @toggle-quick-roll="toggleQuickRoll"
        />
      </div>

      <ChatThread
        v-if="active && chatId !== null"
        :chat-id="chatId"
        :speakers="speakerOptions"
        :active-speaker-key="activeSpeakerKey"
        :speaker-action="speakerAction"
        :rule-names="rulesContext.ruleNames"
        :space-id="rulesContext.spaceId"
        :rules-revision="rulesContext.rulesRevision"
        :token-sources="tokenSources"
        :process-attachments="processAttachments"
        empty-label="Чат игры доступен в мессенджере"
        class="game-chat-thread"
        @update:active-speaker-key="onSpeakerKeyChange"
      />
      <v-card v-else-if="chatId === null" class="game-chat-empty">
        <v-card-text class="text-medium-emphasis text-center pa-8">Игровой чат ещё не создан</v-card-text>
      </v-card>
    </div>

    <CombatCardPanel
      v-model:open="cardOpen"
      :entity-key="cardKey"
      :game-id="gameId"
      :chat-id="chatId"
      :memberships="memberships"
      :npcs="npcs"
      :rules="revisionRules"
      :mechanics="mechanics"
      :can-edit="canEdit"
      :current-user-id="userStore.currentUser?.id ?? null"
      :quick-rolls="quickRolls"
      @update:open="onCloseCard"
      @toggle-quick-roll="toggleQuickRoll"
      @overlay-changed="onOverlayChanged"
    />
  </div>
</template>

<style scoped>
.game-chat-body {
  display: flex;
  align-items: stretch;
  gap: 12px;
}
.game-chat-sidebar {
  width: 210px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  /* Высота всегда ровно как у блока чата (тот же calc, что у ChatThread). */
  height: calc(100vh - var(--v-layout-top) - 70px);
  min-height: 360px;
}
.game-chat-sidebar__initiative {
  flex: 1 1 55%;
  min-height: 0;
}
.game-chat-sidebar__quickrolls {
  flex: 1 1;
  min-height: 0;
}
.game-chat-thread {
  flex: 1;
  min-width: 0;
  height: calc(100vh - var(--v-layout-top) - 70px);
}
.game-chat-empty {
  border: 1px dashed rgba(var(--v-theme-divider), var(--v-border-opacity));
}
</style>
