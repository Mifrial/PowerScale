<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { getUserApi } from '@/modules/Core/User/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { GAME_INVITATION_STATUS_LABEL } from '@/modules/Roleplay/Game/Constant/GameInvitationStatus/GAME_INVITATION_STATUS';
import { GAME_INVITATION_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameInvitationStatus/GAME_INVITATION_STATUS';
import type { GameInvitation } from '@/modules/Roleplay/Game/Dto/GameInvitation';
import type { User } from '@/modules/Core/User/Dto/User';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  canManage: boolean;
  memberIds: number[];
}>();

const { userId } = useCurrentUser();
const store = useGameStore();

const invitations = ref<GameInvitation[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

const sendOpen = ref(false);
const userOptions = ref<User[]>([]);
const inviteeId = ref<number | null>(null);
const sending = ref(false);

// ГМ видит все приглашения игры; обычный участник — только свои (с возможностью ответить).
const visibleInvitations = computed(() => {
  if (props.canManage) return invitations.value;

  return invitations.value.filter((invitation) => invitation.inviteeId === userId.value);
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    invitations.value = await getGameApi().getGameInvitations(props.gameId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить приглашения';
  } finally {
    loading.value = false;
  }
}

function invitationStatusLabel(status: GameInvitation['status']): string {
  return GAME_INVITATION_STATUS_LABEL[status];
}

function invitationStatusColor(status: GameInvitation['status']): string {
  return GAME_INVITATION_STATUS_COLOR[status];
}

async function respond(invitation: GameInvitation, action: 'accept' | 'decline'): Promise<void> {
  try {
    await getGameApi().respondInvitation(invitation.id, action);
    // Мок добавляет участника в gameDetails; синхронизируем открытую карточку (snapshot стора).
    if (action === 'accept') {
      store.addMember({
        userId: invitation.inviteeId,
        userName: invitation.inviteeName,
        role: 'player',
        permissions: [],
      });
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось ответить на приглашение';
  }
  await load();
}

async function openSendDialog(): Promise<void> {
  sendOpen.value = true;
  inviteeId.value = null;
  userOptions.value = await getUserApi().getUsers();
}

const inviteeOptions = computed(() => {
  const memberSet = new Set(props.memberIds);
  const invitedSet = new Set(invitations.value.map((invitation) => invitation.inviteeId));

  return userOptions.value.filter((user) => !memberSet.has(user.id) && !invitedSet.has(user.id));
});

async function send(): Promise<void> {
  if (inviteeId.value === null) return;
  sending.value = true;
  error.value = null;
  try {
    await getGameApi().createInvitation(props.gameId, inviteeId.value);
    sendOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось отправить приглашение';
  } finally {
    sending.value = false;
  }
  await load();
}

// Перезагрузка при активации вкладки (приглашения меняются и на других экранах).
watch(
  () => props.active,
  (value) => {
    if (value) void load();
  },
  { immediate: true },
);
</script>

<template>
  <v-card>
    <v-card-title class="text-subtitle-1 d-flex align-center">
      <span>Приглашения</span>
      <v-spacer />
      <v-btn
        v-if="canManage"
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="mdi-plus"
        @click="openSendDialog"
      >
        Пригласить
      </v-btn>
    </v-card-title>
    <v-card-text>
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
      <div v-if="loading" class="d-flex justify-center pa-4">
        <v-progress-circular indeterminate width="2" size="28" color="primary" />
      </div>
      <v-list v-else-if="visibleInvitations.length > 0" density="compact" class="pa-0">
        <v-list-item v-for="invitation in visibleInvitations" :key="invitation.id">
          <div class="d-flex align-center ga-2 py-1">
            <v-avatar color="primary" size="28" variant="tonal" class="flex-shrink-0">
              <span class="text-body-2">{{ invitation.inviteeName.charAt(0) }}</span>
            </v-avatar>
            <span class="text-body-2">{{ invitation.inviteeName }}</span>
            <span class="text-caption text-medium-emphasis">от {{ invitation.inviterName }}</span>
            <v-spacer />
            <v-chip :color="invitationStatusColor(invitation.status)" variant="tonal" size="x-small">
              {{ invitationStatusLabel(invitation.status) }}
            </v-chip>
            <template
              v-if="invitation.inviteeId === userId && (invitation.status === 'sent' || invitation.status === 'viewed')"
            >
              <v-btn size="x-small" color="success" variant="tonal" @click="respond(invitation, 'accept')"
                >Принять</v-btn
              >
              <v-btn size="x-small" color="error" variant="tonal" @click="respond(invitation, 'decline')"
                >Отклонить</v-btn
              >
            </template>
          </div>
        </v-list-item>
      </v-list>
      <div v-else class="text-center text-medium-emphasis pa-4">Приглашений нет</div>
    </v-card-text>
  </v-card>

  <v-dialog v-model="sendOpen" max-width="480">
    <v-card>
      <v-card-title>Пригласить участника</v-card-title>
      <v-card-text>
        <v-select
          v-model="inviteeId"
          label="Пользователь"
          :items="inviteeOptions"
          item-title="name"
          item-value="id"
          hint="Пользователи, ещё не в игре и не приглашённые"
          persistent-hint
        />
      </v-card-text>
      <v-card-actions>
        <v-btn color="primary" :disabled="inviteeId === null || sending" :loading="sending" @click="send">
          Отправить
        </v-btn>
        <v-btn variant="text" @click="sendOpen = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
