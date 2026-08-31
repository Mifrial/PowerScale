<script setup lang="ts">
import { computed, ref } from 'vue';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { getUserApi } from '@/modules/Core/User/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { GAME_MEMBER_ROLE_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameMemberRole/GAME_MEMBER_ROLE_OPTIONS';
import { GAME_MEMBER_ROLE_COLOR } from '@/modules/Roleplay/Game/Constant/GameMemberRole/GAME_MEMBER_ROLE_COLOR';
import { GAME_MEMBER_PERMISSION_OPTIONS } from '@/modules/Roleplay/Game/Constant/GAME_MEMBER_PERMISSION_OPTIONS';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';
import type { User } from '@/modules/Core/User/Dto/User';
import { UserProfileSlider } from '@/modules/Core/User/init';
import InvitationsPanel from '@/modules/Roleplay/Game/Component/Detail/InvitationsPanel.vue';
import JoinRequestsPanel from '@/modules/Roleplay/Game/Component/Detail/JoinRequestsPanel.vue';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  detail: GameDetail;
  canManage: boolean;
  /** Прямое добавление участника — только владелец/админ (canAddGameMember). */
  canAddMembers: boolean;
}>();

const store = useGameStore();

const sliderOpen = ref(false);
const sliderUserId = ref<number | null>(null);

const addOpen = ref(false);
const userOptions = ref<User[]>([]);
const newUserId = ref<number | null>(null);
const newRole = ref<GameMemberRole>('player');
const removeTarget = ref<GameMember | null>(null);
const removeDialogOpen = ref(false);
const actionError = ref<string | null>(null);

// Роль ведущего — только player/gm; owner не изменяется.
const roleOptions = GAME_MEMBER_ROLE_OPTIONS.filter((option) => option.value !== 'owner');

const candidateUsers = computed(() =>
  userOptions.value.filter((user) => !props.detail.members.some((member) => member.userId === user.id)),
);

function openProfile(member: GameMember): void {
  sliderUserId.value = member.userId;
  sliderOpen.value = true;
}

function roleLabel(role: GameMemberRole): string {
  return GAME_MEMBER_ROLE_OPTIONS.find((option) => option.value === role)?.label ?? role;
}

function roleColor(role: GameMemberRole): string {
  return GAME_MEMBER_ROLE_COLOR[role];
}

async function run(action: () => Promise<void>): Promise<void> {
  actionError.value = null;
  try {
    await action();
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : 'Не удалось выполнить действие';
  }
}

function changeRole(member: GameMember, role: GameMemberRole): void {
  void run(async () => {
    const updated = await getGameApi().updateGameMember(props.detail.game.id, member.userId, {
      role,
      permissions: member.permissions,
    });
    store.updateMember(updated);
  });
}

function togglePermission(member: GameMember, key: string): void {
  const permissions = member.permissions.includes(key)
    ? member.permissions.filter((permission) => permission !== key)
    : [...member.permissions, key];
  void run(async () => {
    const updated = await getGameApi().updateGameMember(props.detail.game.id, member.userId, {
      role: member.role,
      permissions,
    });
    store.updateMember(updated);
  });
}

async function openAddDialog(): Promise<void> {
  addOpen.value = true;
  newUserId.value = null;
  newRole.value = 'player';
  userOptions.value = await getUserApi().getUsers();
}

function addMember(): void {
  const userId = newUserId.value;
  if (userId === null) return;
  void run(async () => {
    const added = await getGameApi().addGameMember(props.detail.game.id, userId, newRole.value);
    store.addMember(added);
    addOpen.value = false;
  });
}

function confirmRemove(): void {
  const target = removeTarget.value;
  if (!target) return;
  void run(async () => {
    await getGameApi().removeGameMember(props.detail.game.id, target.userId);
    store.removeMember(target.userId);
    removeTarget.value = null;
    removeDialogOpen.value = false;
  });
}
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center">
        <span>Участники</span>
        <v-spacer />
        <v-btn
          v-if="canAddMembers"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-plus"
          @click="openAddDialog"
        >
          Добавить
        </v-btn>
        <span v-if="canManage && !canAddMembers" class="text-caption text-medium-emphasis">
          Добавление напрямую — только у владельца
        </span>
      </v-card-title>
      <v-card-text class="pa-0">
        <v-alert v-if="actionError" type="error" variant="tonal" density="compact" class="ma-3">{{
          actionError
        }}</v-alert>
        <v-list density="compact" lines="two">
          <v-list-item
            v-for="member in detail.members"
            :key="member.userId"
            class="member-row"
            @click="openProfile(member)"
          >
            <div class="d-flex align-center ga-2 py-1">
              <v-avatar color="primary" size="28" variant="tonal" class="member-avatar">
                <span class="text-body-2">{{ member.userName.charAt(0) }}</span>
              </v-avatar>
              <span class="text-body-2">{{ member.userName }}</span>
              <v-spacer />
              <v-select
                v-if="canManage && member.role !== 'owner'"
                :model-value="member.role"
                :items="roleOptions"
                item-title="label"
                item-value="value"
                density="compact"
                hide-details
                class="member-role-select"
                @click.stop
                @update:model-value="(role) => changeRole(member, role as GameMemberRole)"
              />
              <v-chip v-else :color="roleColor(member.role)" variant="tonal" size="x-small">
                {{ roleLabel(member.role) }}
              </v-chip>
              <template v-for="option in GAME_MEMBER_PERMISSION_OPTIONS" :key="option.key">
                <v-chip
                  v-if="canManage && member.role !== 'owner'"
                  :variant="member.permissions.includes(option.key) ? 'tonal' : 'text'"
                  :color="member.permissions.includes(option.key) ? 'info' : undefined"
                  size="x-small"
                  class="member-permission-chip"
                  @click.stop="togglePermission(member, option.key)"
                >
                  {{ option.label }}
                </v-chip>
                <v-chip
                  v-else-if="member.permissions.includes(option.key)"
                  variant="tonal"
                  color="info"
                  size="x-small"
                  class="member-permission-chip"
                >
                  {{ option.label }}
                </v-chip>
              </template>
              <v-btn
                v-if="canManage && member.role !== 'owner'"
                icon
                variant="text"
                size="x-small"
                class="member-remove-btn"
                @click.stop="
                  removeTarget = member;
                  removeDialogOpen = true;
                "
              >
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </div>
          </v-list-item>
        </v-list>
        <div v-if="detail.members.length === 0" class="text-center text-medium-emphasis pa-6">Участников пока нет</div>
      </v-card-text>
    </v-card>

    <InvitationsPanel
      :active="props.active"
      :game-id="detail.game.id"
      :can-manage="canManage"
      :member-ids="detail.members.map((member) => member.userId)"
    />

    <JoinRequestsPanel
      v-if="canManage"
      :active="props.active"
      :game-id="detail.game.id"
      :member-ids="detail.members.map((member) => member.userId)"
    />
  </div>

  <UserProfileSlider v-model:open="sliderOpen" :user-id="sliderUserId" />

  <v-dialog v-model="addOpen" max-width="480">
    <v-card>
      <v-card-title>Добавить участника</v-card-title>
      <v-card-text>
        <v-select
          v-model="newUserId"
          label="Пользователь"
          :items="candidateUsers"
          item-title="name"
          item-value="id"
          hint="Пользователи, ещё не в игре"
          persistent-hint
        />
        <v-select v-model="newRole" label="Роль" :items="roleOptions" item-title="label" item-value="value" />
      </v-card-text>
      <v-card-actions>
        <v-btn color="primary" :disabled="newUserId === null" @click="addMember">Добавить</v-btn>
        <v-btn variant="text" @click="addOpen = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="removeDialogOpen" max-width="400">
    <v-card>
      <v-card-title>Удалить участника</v-card-title>
      <v-card-text>Убрать {{ removeTarget?.userName }} из участников игры?</v-card-text>
      <v-card-actions>
        <v-btn color="error" @click="confirmRemove">Удалить</v-btn>
        <v-btn variant="text" @click="removeDialogOpen = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.member-row {
  min-height: 44px;
  cursor: pointer;
}
.member-avatar {
  flex-shrink: 0;
}
.member-role-select {
  width: 132px;
}
.member-permission-chip {
  flex-shrink: 0;
}
.member-remove-btn {
  flex-shrink: 0;
}
</style>
