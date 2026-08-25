<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { buildChatRulesContext } from '@/modules/Roleplay/Game/Utils/chatRulesContext';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { canViewGame, canEditGame, canModerateGame, canAddGameMember } from '@/modules/Roleplay/Game/Utils/access';
import OverviewTab from '@/modules/Roleplay/Game/Component/Detail/OverviewTab.vue';
import MembersTab from '@/modules/Roleplay/Game/Component/Detail/MembersTab.vue';
import CharactersTab from '@/modules/Roleplay/Game/Component/Detail/CharactersTab.vue';
import ModerateTab from '@/modules/Roleplay/Game/Component/Detail/ModerateTab.vue';
import NpcsTab from '@/modules/Roleplay/Game/Component/Detail/NpcsTab.vue';
import LootTab from '@/modules/Roleplay/Game/Component/Detail/LootTab.vue';
import ChronicleTab from '@/modules/Roleplay/Game/Component/Detail/ChronicleTab.vue';
import GameChatTab from '@/modules/Roleplay/Game/Component/Detail/GameChatTab.vue';
import ChatThread from '@/modules/Messages/Chat/Component/ChatThread.vue';
import UserProfileSlider from '@/modules/Core/User/Component/UserProfileSlider.vue';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import OwnerNotesDialog from '@/modules/Roleplay/Character/Component/OwnerNotesDialog.vue';

const route = useRoute();
const router = useRouter();
const store = useGameStore();
const userStore = useUserStore();
const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const activeTab = ref('overview');

const ownerSliderOpen = ref(false);
const ownerSliderUserId = ref<number | null>(null);

function openOwnerProfile(): void {
  ownerSliderUserId.value = detail.value?.game.ownerId ?? null;
  ownerSliderOpen.value = true;
}

const detail = computed(() => store.currentGame);
const detailError = computed(() => store.detailError);
const detailLoading = computed(() => store.detailLoading);

const spaceName = ref<string | null>(null);

// Контекст правил обсуждения игры (ревизия игры): чипы, «Вставить ссылку», броски.
const discussionRules = ref<Rule[]>([]);
const discussionMechanics = ref<Mechanic[]>([]);
const discussionContext = computed<ChatRulesContext>(() =>
  buildChatRulesContext(
    discussionRules.value,
    discussionMechanics.value,
    detail.value?.game.spaceId,
    detail.value?.game.rulesRevision,
  ),
);

const gameId = computed(() => {
  const raw = route.params.id;
  if (typeof raw !== 'string') return Number.NaN;

  return Number(raw);
});

const memberIds = computed(() => detail.value?.members.map((member) => member.userId) ?? []);

// Заявки на вступление: текущий пользователь и его статус (для не-участников).
const joinRequests = ref<GameJoinRequest[]>([]);
const requesting = ref(false);
const joinError = ref<string | null>(null);
const notesOpen = ref(false);
const notesSaving = ref(false);
const notesError = ref<string | null>(null);

async function savePersonalNotes(text: string): Promise<void> {
  const current = detail.value;
  if (!current) return;
  notesSaving.value = true;
  notesError.value = null;
  try {
    const updated = await getGameApi().updatePersonalNotes(current.game.id, text, signal.value);
    store.applyGameUpdate(updated);
    notesOpen.value = false;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    notesError.value = 'Не удалось сохранить заметки';
  } finally {
    notesSaving.value = false;
  }
}

const myJoinRequest = computed(() => {
  const user = userStore.currentUser;
  if (!user) return null;

  return joinRequests.value.find((request) => request.userId === user.id) ?? null;
});

/** Активная (на рассмотрении) заявка текущего пользователя. */
const myPendingJoinRequest = computed(() => (myJoinRequest.value?.status === 'pending' ? myJoinRequest.value : null));

const isMember = computed(() => {
  const user = userStore.currentUser;
  if (!user) return false;
  const current = detail.value;
  if (!current) return false;
  if (current.game.ownerId === user.id) return true;

  return memberIds.value.includes(user.id);
});

const canRequestJoin = computed(() => {
  const current = detail.value;
  const user = userStore.currentUser;
  if (!current || !user || isMember.value) return false;
  if (myPendingJoinRequest.value) return false;
  const policy = current.game.joinPolicy;

  return policy === 'anyone' || policy === 'friends';
});

const canView = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return canViewGame(userStore.currentUser, current.game, memberIds.value);
});

const canManageMembers = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return canEditGame(userStore.currentUser, current);
});

const canEdit = computed(() => canManageMembers.value);

const canModerate = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return canModerateGame(userStore.currentUser, current);
});

// Прямое добавление участника — только владелец игры / глобальный админ (ведущие — по приглашению).
const canAddMembers = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return canAddGameMember(userStore.currentUser, current);
});

// Участник игры (для подачи персонажа): владелец/ведущий/игрок.
const canSubmitCharacter = computed(() => {
  const current = userStore.currentUser;
  if (!current) return false;

  return memberIds.value.includes(current.id);
});

async function load(): Promise<void> {
  const id = gameId.value;
  if (!Number.isFinite(id) || id <= 0) {
    router.replace({ name: 'NotFound' });

    return;
  }
  store.clearCurrent();
  joinRequests.value = [];
  joinError.value = null;
  const loaded = await store.fetchGame(id, signal.value);
  if (
    loaded &&
    !canViewGame(
      userStore.currentUser,
      loaded.game,
      loaded.members.map((member) => member.userId),
    )
  ) {
    router.replace({ name: 'NotFound' });
  }
  if (loaded) {
    try {
      joinRequests.value = await getGameApi().getJoinRequests(id);
    } catch {
      joinRequests.value = [];
    }
  }
}

/** Заявка на вступление в игру (по политике «любой желающий/друзья»). */
async function requestJoin(): Promise<void> {
  const id = gameId.value;
  if (!Number.isFinite(id)) return;
  requesting.value = true;
  joinError.value = null;
  try {
    const created = await getGameApi().requestJoinGame(id);
    joinRequests.value = [...joinRequests.value, created];
  } catch (e) {
    joinError.value = e instanceof Error ? e.message : 'Не удалось подать заявку';
  } finally {
    requesting.value = false;
  }
}

// Имя пространства для ссылки «Правила» берём из загруженной ревизии (fetchRevision, кэш без syncFromContext).
async function loadSpaceName(spaceId: number, revision: number, abortSignal: AbortSignal): Promise<void> {
  try {
    const revisionResult = await spaceRevisionStore.fetchRevision(spaceId, revision, abortSignal);
    spaceName.value = revisionResult.spaceName;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    spaceName.value = null;
  }
}

function retry(): void {
  void load();
}

/** Контекст правил обсуждения игры: ревизия игры + механики (чипы/ссылки/броски). */
async function loadDiscussionContext(): Promise<void> {
  const current = detail.value;
  if (!current) return;
  try {
    const revision = await spaceRevisionStore.fetchRevision(
      current.game.spaceId,
      current.game.rulesRevision,
      signal.value,
    );
    discussionRules.value = revision.rules;
    discussionMechanics.value = await getRuleApi().getMechanics(signal.value);
  } catch {
    discussionRules.value = [];
    discussionMechanics.value = [];
  }
}

watch(() => route.params.id, load, { immediate: true });

watch(activeTab, (tab) => {
  if (tab === 'discussion') void loadDiscussionContext();
});

watch(detail, (value) => {
  if (!value) {
    spaceName.value = null;

    return;
  }
  void loadSpaceName(value.game.spaceId, value.game.rulesRevision, signal.value);
});
</script>

<template>
  <v-container :fluid="activeTab === 'game-chat'">
    <div v-if="detailError" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ detailError }}</p>
      <v-btn color="primary" @click="retry">Попробовать снова</v-btn>
    </div>

    <div v-else-if="detailLoading || !detail" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-else-if="canView && detail">
      <Teleport to="#editor-actions">
        <v-btn variant="tonal" size="small" prepend-icon="mdi-note-text-outline" @click="notesOpen = true">
          Заметки
        </v-btn>
        <v-btn
          v-if="canRequestJoin"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-account-arrow-right"
          :loading="requesting"
          @click="requestJoin"
        >
          Подать заявку
        </v-btn>
        <v-chip v-else-if="myPendingJoinRequest" color="info" variant="tonal" size="small">
          Заявка на рассмотрении
        </v-chip>
        <v-btn
          v-if="canEdit"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-pencil"
          :to="`/games/${detail.game.id}/edit`"
        >
          Настройки
        </v-btn>
      </Teleport>

      <v-alert v-if="joinError" type="error" variant="tonal" density="compact" class="mb-4">{{ joinError }}</v-alert>

      <v-tabs v-model="activeTab" color="primary" class="mb-2" density="compact">
        <v-tab value="overview">Обзор</v-tab>
        <v-tab value="members">Участники</v-tab>
        <v-tab value="characters">Персонажи</v-tab>
        <v-tab v-if="canModerate" value="moderate">Модерация</v-tab>
        <v-tab value="npcs">НПС</v-tab>
        <v-tab value="loot">Добыча</v-tab>
        <v-tab value="chronicle">Летопись</v-tab>
        <v-tab value="game-chat">Чат игры</v-tab>
        <v-tab value="discussion">Обсуждение</v-tab>
      </v-tabs>

      <v-window v-model="activeTab">
        <v-window-item value="overview">
          <OverviewTab :detail="detail" :space-name="spaceName" @open-owner="openOwnerProfile" />
        </v-window-item>
        <v-window-item value="members">
          <MembersTab
            :detail="detail"
            :can-manage="canManageMembers"
            :can-add-members="canAddMembers"
            :active="activeTab === 'members'"
          />
        </v-window-item>
        <v-window-item value="characters">
          <CharactersTab
            :active="activeTab === 'characters'"
            :game-id="detail.game.id"
            :game-space-id="detail.game.spaceId"
            :game-space-code="detail.game.spaceCode"
            :game-rules-revision="detail.game.rulesRevision"
            :game-os-limit="detail.osPointsLimit"
            :game-or-limit="detail.orPointsLimit"
            :game-money-limit="detail.moneyLimit"
            :can-submit="canSubmitCharacter"
            :can-manage="canManageMembers"
            :members="detail.members"
            :space-id="detail.game.spaceId"
            :rules-revision="detail.game.rulesRevision"
          />
        </v-window-item>
        <v-window-item v-if="canModerate" value="moderate">
          <ModerateTab
            :active="activeTab === 'moderate'"
            :game-id="detail.game.id"
            :space-id="detail.game.spaceId"
            :rules-revision="detail.game.rulesRevision"
          />
        </v-window-item>
        <v-window-item value="npcs">
          <NpcsTab
            :active="activeTab === 'npcs'"
            :game-id="detail.game.id"
            :can-manage="canManageMembers"
            :is-member="canSubmitCharacter"
            :members="detail.members"
            :space-id="detail.game.spaceId"
            :space-code="detail.game.spaceCode"
            :rules-revision="detail.game.rulesRevision"
          />
        </v-window-item>
        <v-window-item value="loot">
          <LootTab
            :active="activeTab === 'loot'"
            :game-id="detail.game.id"
            :can-manage="canManageMembers"
            :is-member="canSubmitCharacter"
            :space-id="detail.game.spaceId"
            :rules-revision="detail.game.rulesRevision"
          />
        </v-window-item>
        <v-window-item value="chronicle">
          <ChronicleTab
            :active="activeTab === 'chronicle'"
            :game-id="detail.game.id"
            :can-manage="canManageMembers"
            :space-id="detail.game.spaceId"
            :rules-revision="detail.game.rulesRevision"
          />
        </v-window-item>
        <v-window-item value="game-chat">
          <!-- Живой игровой чат; монтируем только при открытой вкладке (D7) -->
          <GameChatTab
            v-if="activeTab === 'game-chat'"
            :active="activeTab === 'game-chat'"
            :detail="detail"
            :can-edit="canManageMembers"
          />
        </v-window-item>
        <v-window-item value="discussion">
          <!-- Чат живёт в глобальном чат-сторе; монтируем вкладку только при открытии, чтобы освобождать чат при уходе (D7) -->
          <ChatThread
            v-if="activeTab === 'discussion'"
            :chat-id="detail.discussionChatId"
            :rule-names="discussionContext.ruleNames"
            :space-id="discussionContext.spaceId"
            :rules-revision="discussionContext.rulesRevision"
            :token-sources="discussionContext.tokenSources"
            :process-attachments="discussionContext.processAttachments"
            empty-label="Обсуждение доступно в мессенджере"
          />
        </v-window-item>
      </v-window>
    </template>
  </v-container>

  <OwnerNotesDialog
    v-model="notesOpen"
    :text="detail?.personalNotes"
    :saving="notesSaving"
    :error="notesError"
    @save="savePersonalNotes"
  />

  <UserProfileSlider v-model:open="ownerSliderOpen" :user-id="ownerSliderUserId" />
</template>

<style scoped>
.v-window {
  min-width: 0;
}
:deep(.v-window-item) {
  width: 100%;
  min-width: 0;
}

/* Лёгкий бордер вокруг всех блоков карточки игры (описание, правила, теги, прочее, участники и т.д.) */
:deep(.v-card) {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
</style>
