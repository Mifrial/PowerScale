<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { sheetAccessService } from '@/modules/Roleplay/Character/init';
import { gameTimeLabel } from '@/modules/Roleplay/Game/Utils/gameTime';
import { CHRONICLE_EPOCH_LABEL } from '@/modules/Roleplay/Game/Constant/Chronicle/CHRONICLE_EPOCH_LABEL';
import type { Chronicle } from '@/modules/Roleplay/Game/Dto/Chronicle';
import type { ChronicleEntry } from '@/modules/Roleplay/Game/Dto/ChronicleEntry';
import type { ChronicleRef } from '@/modules/Roleplay/Game/Dto/ChronicleRef';
import type { CreateChronicleEntryData } from '@/modules/Roleplay/Game/Dto/CreateChronicleEntryData';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { User } from '@/modules/Core/User/Dto/User';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import ChronicleEntryDialog from '@/modules/Roleplay/Game/Component/Detail/ChronicleEntryDialog.vue';
import ChronicleEntryContent from '@/modules/Roleplay/Game/Component/Detail/ChronicleEntryContent.vue';
import { SheetCard } from '@/modules/Roleplay/Character/init';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  canManage: boolean;
  spaceId: number | null;
  rulesRevision: number | null;
}>();

const userStore = useUserStore();

const chronicle = ref<Chronicle | null>(null);
const entries = ref<ChronicleEntry[]>([]);
const memberships = ref<GameCharacterMembership[]>([]);
const npcs = ref<GameNpc[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const actionError = ref<string | null>(null);

const formOpen = ref(false);
const formInitial = ref<ChronicleEntry | null>(null);
const confirmDeleteId = ref<number | null>(null);

const currentUser = computed<User | null>(() => userStore.currentUser);

const confirmDeleteOpen = computed({
  get: () => confirmDeleteId.value !== null,
  set: (value: boolean) => {
    if (!value) confirmDeleteId.value = null;
  },
});

// Источники ссылок: approved-персонажи игры и активные НПС (как в форме записи).
const approvedCharacters = computed(() =>
  memberships.value.filter((membership) => membership.membershipStatus === 'active'),
);
const activeNpcs = computed(() => npcs.value.filter((npc) => npc.status === 'active'));

const characterOptions = computed(() =>
  approvedCharacters.value.map((membership) => ({ id: membership.characterId, name: membership.characterName })),
);
const npcOptions = computed(() => activeNpcs.value.map((npc) => ({ id: npc.id, name: npc.name })));

function epochLabel(): string {
  return CHRONICLE_EPOCH_LABEL[chronicle.value?.epoch ?? 'adventure_start'];
}

function offsetLabel(entry: ChronicleEntry): string {
  return `${gameTimeLabel(entry.offset)} ${epochLabel()}`;
}

function characterCtx(user: User, membership: GameCharacterMembership): SheetAccessContext {
  return {
    user,
    ownerId: membership.characterOwnerId,
    characterId: membership.characterId,
    gameId: props.gameId,
  };
}

function npcCtx(user: User, npc: GameNpc): SheetAccessContext {
  return { user, ownerId: null, characterId: npc.id, gameId: props.gameId };
}

const refView = ref<ChronicleRef | null>(null);

const refViewOpen = computed({
  get: () => refView.value !== null,
  set: (value: boolean) => {
    if (!value) refView.value = null;
  },
});

/** Данные для просмотра листа по ссылке (SheetCard по видимым секциям). */
const refCard = computed<{
  name: string;
  version: CharacterVersion | null;
  visibleSections: SheetSection[];
  shortDescription: string | null;
  fullDescription: string | null;
} | null>(() => {
  const ref = refView.value;
  const user = currentUser.value;
  if (!ref || !user) return null;
  if (ref.kind === 'character') {
    const membership = memberships.value.find(
      (candidate) => candidate.characterId === ref.id && candidate.membershipStatus === 'active',
    );
    if (!membership) return null;

    return {
      name: membership.characterName,
      version: sessionCharacterService.resolve(membership.approvedCharacterVersion, membership.overlay),
      visibleSections: sheetAccessService.visibleSheetSections(
        user,
        membership.visibility,
        characterCtx(user, membership),
      ),
      shortDescription: null,
      fullDescription: null,
    };
  }
  const npc = npcs.value.find((candidate) => candidate.id === ref.id && candidate.status === 'active');
  if (!npc) return null;

  return {
    name: npc.name,
    version: npc.version,
    visibleSections: sheetAccessService.visibleSheetSections(user, npc.visibility, npcCtx(user, npc)),
    shortDescription: npc.shortDescription,
    fullDescription: npc.fullDescription,
  };
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const [chronicleResult, entryList, membershipList, npcList] = await Promise.all([
      getGameApi().getChronicle(props.gameId),
      getGameApi().getChronicleEntries(props.gameId),
      getGameApi().getGameCharacters(props.gameId),
      getGameApi().getNpcs(props.gameId),
    ]);
    chronicle.value = chronicleResult;
    entries.value = entryList;
    memberships.value = membershipList;
    npcs.value = npcList;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить летопись';
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

function openCreate(): void {
  formInitial.value = null;
  formOpen.value = true;
}

function openEdit(entry: ChronicleEntry): void {
  formInitial.value = entry;
  formOpen.value = true;
}

function openRef(ref: ChronicleRef): void {
  refView.value = ref;
}

async function saveForm(data: CreateChronicleEntryData): Promise<void> {
  const initial = formInitial.value;
  await run(async () => {
    if (initial) {
      await getGameApi().updateChronicleEntry(initial.id, data);
    } else {
      await getGameApi().createChronicleEntry(props.gameId, data);
    }
    formOpen.value = false;
    await load();
  });
}

function askDelete(entry: ChronicleEntry): void {
  confirmDeleteId.value = entry.id;
}

async function doDelete(): Promise<void> {
  const id = confirmDeleteId.value;
  if (id === null) return;
  await run(async () => {
    await getGameApi().deleteChronicleEntry(id);
    confirmDeleteId.value = null;
    await load();
  });
}

// Перезагрузка при активации вкладки: записи/сдвиги меняются и на других экранах.
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
        <span>Летопись</span>
        <span v-if="chronicle?.name" class="text-caption text-medium-emphasis ml-2">{{ chronicle.name }}</span>
        <v-spacer />
        <v-btn
          v-if="canManage"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          Записать событие
        </v-btn>
      </v-card-title>
      <v-card-text class="pa-0">
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-3">{{ error }}</v-alert>

        <div v-if="loading" class="d-flex justify-center pa-4">
          <v-progress-circular indeterminate width="2" size="28" color="primary" />
        </div>

        <div v-else-if="entries.length === 0" class="text-center text-medium-emphasis pa-6">
          Записей в летописи пока нет.
          <template v-if="canManage">Ведущий может начать хронику событий игры.</template>
        </div>

        <v-timeline v-else density="compact" side="end" line-inset="8" class="chronicle-timeline">
          <v-timeline-item
            v-for="entry in entries"
            :key="entry.id"
            dot-color="primary"
            size="x-small"
            fill-dot
            class="chronicle-item"
          >
            <v-card variant="tonal" class="mb-2 chronicle-card">
              <v-card-title class="text-subtitle-2 d-flex align-center pt-2 pb-0">
                <span>{{ entry.title }}</span>
                <span class="text-caption text-medium-emphasis chronicle-offset ml-1">({{ offsetLabel(entry) }})</span>
                <v-spacer />
                <template v-if="canManage">
                  <v-btn size="x-small" variant="text" @click="openEdit(entry)">Правка</v-btn>
                  <v-btn size="x-small" variant="text" color="error" @click="askDelete(entry)">Удалить</v-btn>
                </template>
              </v-card-title>
              <v-card-text class="pt-1 chronicle-content">
                <ChronicleEntryContent :entry="entry" :memberships="memberships" :npcs="npcs" @open-ref="openRef" />
              </v-card-text>
            </v-card>
          </v-timeline-item>
        </v-timeline>
      </v-card-text>
    </v-card>
  </div>

  <ChronicleEntryDialog
    v-model:open="formOpen"
    :initial="formInitial"
    :character-options="characterOptions"
    :npc-options="npcOptions"
    @save="saveForm"
  />

  <v-dialog v-model="confirmDeleteOpen" max-width="420">
    <v-card>
      <v-card-text>Удалить запись летописи?</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="error" variant="tonal" @click="doDelete">Удалить</v-btn>
        <v-btn variant="text" @click="confirmDeleteId = null">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="refViewOpen" max-width="560">
    <v-card v-if="refCard">
      <v-card-title class="d-flex align-center">
        <span class="text-h6">{{ refCard.name }}</span>
        <v-spacer />
        <v-btn icon variant="text" size="small" @click="refViewOpen = false"><v-icon>mdi-close</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <SheetCard
          :name="refCard.name"
          :version="refCard.version"
          :visible-sections="refCard.visibleSections"
          :space-id="spaceId"
          :rules-revision="rulesRevision"
          :short-description="refCard.shortDescription"
          :full-description="refCard.fullDescription"
        />
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.chronicle-timeline {
  padding-top: 8px;
}
.chronicle-offset {
  white-space: nowrap;
}
.chronicle-content {
  white-space: pre-wrap;
}
</style>
