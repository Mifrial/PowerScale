<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { canSeeSheet } from '@/modules/Roleplay/Character/Utils/sheetAccess';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { User } from '@/modules/Core/User/Dto/User';
import NpcCard, { type NpcCardData } from '@/modules/Roleplay/Game/Component/Detail/NpcCard.vue';
import SheetVisibilityDialog from '@/modules/Roleplay/Game/Component/Detail/SheetVisibilityDialog.vue';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  canManage: boolean;
  isMember: boolean;
  members: GameMember[];
  spaceId: number | null;
  rulesRevision: number | null;
}>();

const userStore = useUserStore();

const npcs = ref<GameNpc[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const actionError = ref<string | null>(null);
const query = ref('');

const adding = ref(false);
const newName = ref('');
const creating = ref(false);
const proposing = ref(false);
const proposalName = ref('');
const proposalDescription = ref('');

const cardOpen = ref(false);
const selectedNpc = ref<GameNpc | null>(null);

const visibilityOpen = ref(false);
const visibilityTarget = ref<GameNpc | null>(null);

const currentUser = computed<User | null>(() => userStore.currentUser);

const activeNpcs = computed(() => npcs.value.filter((npc) => npc.status === 'active'));
const proposedNpcs = computed(() => npcs.value.filter((npc) => npc.status === 'proposed'));

// У НПС нет владельца (ownerId null); видимость для игроков — по зонам, ведущие — через роль 'gm'.
function ctxFor(user: User, npc: GameNpc): SheetAccessContext {
  return { user, ownerId: null, characterId: npc.id, gameId: props.gameId };
}

// Игрок видит только НПС по зонам видимости (+ свои предложения); ведущий — все.
const visibleNpcs = computed(() => {
  const user = currentUser.value;
  if (!user) return [];

  return activeNpcs.value.filter((npc) => canSeeSheet(user, npc.visibility, ctxFor(user, npc)));
});

const myProposals = computed(() => {
  const user = currentUser.value;

  return proposedNpcs.value.filter((npc) => npc.proposedBy?.userId === user?.id);
});

const moderationQueue = computed(() => (props.canManage ? proposedNpcs.value : []));

// Поиск по имени и описательным тегам НПС.
const filteredNpcs = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return visibleNpcs.value;

  return visibleNpcs.value.filter(
    (npc) => npc.name.toLowerCase().includes(q) || npc.tags.some((tag) => tag.toLowerCase().includes(q)),
  );
});

const filteredMyProposals = computed(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return myProposals.value;

  return myProposals.value.filter(
    (npc) => npc.name.toLowerCase().includes(q) || npc.tags.some((tag) => tag.toLowerCase().includes(q)),
  );
});

function briefVisibility(): SheetVisibility {
  return [{ audience: 'all', sections: ['shortDescription'] }];
}

/** Виден ли НПС всем участникам (есть зона all). */
function isVisibleToAll(npc: GameNpc): boolean {
  return npc.visibility.some((rule) => rule.audience === 'all');
}

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    npcs.value = await getGameApi().getNpcs(props.gameId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить НПС';
  } finally {
    loading.value = false;
  }
}

async function run(action: () => Promise<void>): Promise<void> {
  actionError.value = null;
  try {
    await action();
  } catch (e) {
    actionError.value = e instanceof Error ? e.message : 'Не удалось выполнить действие';
  }
}

function openAdd(): void {
  adding.value = true;
  newName.value = '';
}

async function create(): Promise<void> {
  const name = newName.value.trim();
  if (!name) return;
  creating.value = true;
  await run(async () => {
    await getGameApi().createNpc(props.gameId, {
      name,
      shortDescription: null,
      fullDescription: null,
      tags: [],
      visibility: briefVisibility(),
    });
    adding.value = false;
    await load();
  });
  creating.value = false;
}

function openPropose(): void {
  proposing.value = true;
  proposalName.value = '';
  proposalDescription.value = '';
}

async function propose(): Promise<void> {
  const name = proposalName.value.trim();
  if (!name) return;
  creating.value = true;
  await run(async () => {
    await getGameApi().proposeNpc(props.gameId, {
      name,
      shortDescription: proposalDescription.value.trim() || null,
      fullDescription: null,
      tags: [],
      visibility: briefVisibility(),
    });
    proposing.value = false;
    await load();
  });
  creating.value = false;
}

async function moderate(npcId: number, action: 'approve' | 'reject'): Promise<void> {
  await run(async () => {
    await getGameApi().moderateNpc(npcId, action);
    await load();
  });
}

function openCard(npc: GameNpc): void {
  selectedNpc.value = npc;
  cardOpen.value = true;
}

async function handleSave(npcId: number, data: NpcCardData): Promise<void> {
  const target = npcs.value.find((npc) => npc.id === npcId);
  await run(async () => {
    await getGameApi().updateNpc(npcId, { ...data, version: target?.version ?? null });
    await load();
    if (selectedNpc.value?.id === npcId) {
      selectedNpc.value = npcs.value.find((npc) => npc.id === npcId) ?? null;
    }
  });
}

async function handleDelete(npcId: number): Promise<void> {
  await run(async () => {
    await getGameApi().deleteNpc(npcId);
    cardOpen.value = false;
    await load();
  });
}

function openVisibility(npc: GameNpc): void {
  visibilityTarget.value = npc;
  visibilityOpen.value = true;
}

async function saveVisibility(visibility: SheetVisibility): Promise<void> {
  const target = visibilityTarget.value;
  if (!target) return;
  await run(async () => {
    await getGameApi().updateNpc(target.id, {
      name: target.name,
      shortDescription: target.shortDescription,
      fullDescription: target.fullDescription,
      tags: target.tags,
      visibility,
      version: target.version,
    });
    visibilityOpen.value = false;
    await load();
    if (selectedNpc.value?.id === target.id) {
      selectedNpc.value = npcs.value.find((npc) => npc.id === target.id) ?? null;
    }
  });
}

// Перезагрузка при активации вкладки (лист/видимость НПС меняются и на других экранах).
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
    <v-alert v-if="actionError" type="error" variant="tonal" density="compact">{{ actionError }}</v-alert>

    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center">
        <span>НПС</span>
        <v-text-field
          v-model="query"
          class="npc-search"
          label="Поиск по имени и тегам"
          density="compact"
          hide-details
          clearable
        />
        <v-spacer />
        <v-btn
          v-if="canManage && !adding"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-plus"
          @click="openAdd"
        >
          Добавить НПС
        </v-btn>
        <v-btn
          v-else-if="isMember && !proposing && !adding"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-plus"
          @click="openPropose"
        >
          Предложить НПС
        </v-btn>
      </v-card-title>
      <v-card-text class="pa-0">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-3">{{ error }}</v-alert>

        <!-- inline-добавление (ведущий) -->
        <div v-if="adding" class="d-flex align-center ga-2 px-3 pb-3">
          <v-text-field v-model="newName" label="Имя НПС" density="compact" hide-details @keydown.enter="create" />
          <v-btn color="primary" variant="tonal" size="small" :loading="creating" @click="create">Сохранить</v-btn>
          <v-btn variant="text" size="small" @click="adding = false">Отмена</v-btn>
        </div>

        <!-- inline-предложение (игрок) -->
        <div v-else-if="proposing" class="d-flex flex-column ga-2 px-3 pb-3">
          <v-text-field v-model="proposalName" label="Имя НПС" density="compact" hide-details />
          <v-text-field v-model="proposalDescription" label="Краткое описание" density="compact" hide-details />
          <div class="d-flex align-center ga-2">
            <v-btn color="primary" variant="tonal" size="small" :loading="creating" @click="propose">
              Отправить на модерацию
            </v-btn>
            <v-btn variant="text" size="small" @click="proposing = false">Отмена</v-btn>
          </div>
        </div>

        <div v-if="loading" class="d-flex justify-center pa-4">
          <v-progress-circular indeterminate width="2" size="28" color="primary" />
        </div>

        <v-list v-else density="compact" lines="two">
          <v-list-item v-for="npc in filteredNpcs" :key="npc.id" @click="openCard(npc)">
            <div class="d-flex align-center ga-2 py-1">
              <v-avatar color="primary" size="28" variant="tonal" class="flex-shrink-0">
                <v-icon size="small">mdi-account-question</v-icon>
              </v-avatar>
              <span class="text-body-2">{{ npc.name }}</span>
              <v-chip
                v-if="canManage && !isVisibleToAll(npc)"
                :color="npc.visibility.length === 0 ? 'grey' : 'info'"
                variant="tonal"
                size="x-small"
              >
                {{ npc.visibility.length === 0 ? 'Скрыт от игроков' : 'Выборочно' }}
              </v-chip>
              <span v-if="npc.shortDescription" class="text-caption text-medium-emphasis npc-brief">
                {{ npc.shortDescription }}
              </span>
              <v-btn
                v-if="canManage"
                icon
                variant="text"
                size="x-small"
                title="Видимость НПС"
                @click.stop="openVisibility(npc)"
              >
                <v-icon>mdi-eye-settings-outline</v-icon>
              </v-btn>
            </div>
          </v-list-item>
        </v-list>

        <div
          v-if="!loading && filteredNpcs.length === 0 && filteredMyProposals.length === 0"
          class="text-center text-medium-emphasis pa-6"
        >
          НПС не найдены
        </div>

        <!-- Свои предложения игрока -->
        <div v-if="filteredMyProposals.length > 0" class="border-t">
          <div class="text-caption text-medium-emphasis px-3 pt-3">Ваши предложения</div>
          <v-list density="compact">
            <v-list-item v-for="npc in filteredMyProposals" :key="npc.id" @click="openCard(npc)">
              <div class="d-flex align-center ga-2 py-1">
                <span class="text-body-2">{{ npc.name }}</span>
                <v-chip color="warning" variant="tonal" size="x-small">На модерации</v-chip>
              </div>
            </v-list-item>
          </v-list>
        </div>
      </v-card-text>
    </v-card>

    <!-- Модерация предложений (ведущий) -->
    <v-card v-if="moderationQueue.length > 0">
      <v-card-title class="text-subtitle-1">Предложенные НПС</v-card-title>
      <v-card-text class="pa-0">
        <v-list density="compact">
          <v-list-item v-for="npc in moderationQueue" :key="npc.id">
            <div class="d-flex align-center ga-2 py-1">
              <span class="text-body-2">{{ npc.name }}</span>
              <span class="text-caption text-medium-emphasis">от {{ npc.proposedBy?.userName }}</span>
              <v-spacer />
              <v-btn size="x-small" color="success" variant="tonal" @click="moderate(npc.id, 'approve')">Принять</v-btn>
              <v-btn size="x-small" color="error" variant="tonal" @click="moderate(npc.id, 'reject')">Отклонить</v-btn>
            </div>
          </v-list-item>
        </v-list>
      </v-card-text>
    </v-card>
  </div>

  <NpcCard
    v-if="selectedNpc"
    v-model:open="cardOpen"
    :npc="selectedNpc"
    :is-gm="canManage"
    :user="currentUser"
    :members="members"
    :space-id="spaceId"
    :rules-revision="rulesRevision"
    @save="handleSave"
    @delete="handleDelete"
  />

  <SheetVisibilityDialog
    v-model:open="visibilityOpen"
    :title="`Видимость НПС — ${visibilityTarget?.name ?? ''}`"
    :visibility="visibilityTarget?.visibility ?? null"
    :members="members"
    @save="saveVisibility"
  />
</template>

<style scoped>
.npc-search {
  max-width: 260px;
  margin-left: 12px;
}
.npc-brief {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
