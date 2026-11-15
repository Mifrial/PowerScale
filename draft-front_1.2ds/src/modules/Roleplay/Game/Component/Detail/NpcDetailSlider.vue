<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import { CharacterDetailPanel, sheetAccessService } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
import { npcDetailAccessService } from '@/modules/Roleplay/Game/Service/Instance/npcDetailAccessService';

/** Деталка НПС в слайдере: шапка, лист по видимости и действия-ссылки. */
const props = withDefaults(defineProps<{ gameId: number | null; npcId: number | null }>(), {
  gameId: null,
  npcId: null,
});

const open = defineModel<boolean>('open', { default: false });

const { currentUser } = useCurrentUser();

const gameDetail = ref<GameDetail | null>(null);
const npc = ref<GameNpc | null>(null);
const visibleSections = ref<SheetSection[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const hidden = ref(false);
let loadSequence = 0;

const game = computed(() => gameDetail.value?.game ?? null);

const canEdit = computed(() => {
  const detail = gameDetail.value;
  if (!detail) return false;

  return gameAccessService.canEditGame(currentUser.value, detail);
});

const chipLabel = computed(() => (npc.value?.status === 'proposed' ? 'На модерации' : null));

const revisionHref = computed(() => {
  const currentGame = game.value;
  const revision = npc.value?.version?.rulesRevision;
  if (!currentGame || revision === undefined) return null;

  return `/space/${currentGame.spaceCode}/${revision}`;
});

const revisionLabel = computed(() => {
  const currentGame = game.value;
  const revision = npc.value?.version?.rulesRevision;
  if (!currentGame || revision === undefined) return null;

  return `Правила: ${currentGame.spaceCode} v${revision}`;
});

async function load(): Promise<void> {
  const gameId = props.gameId;
  const npcId = props.npcId;
  if (!open.value || gameId === null || npcId === null || gameId <= 0 || npcId <= 0) {
    if (open.value) hidden.value = true;

    return;
  }
  const requestSequence = ++loadSequence;
  loading.value = true;
  error.value = null;
  hidden.value = false;
  gameDetail.value = null;
  npc.value = null;
  visibleSections.value = [];
  try {
    const loadedGame = await getGameApi().getGame(gameId);
    if (requestSequence !== loadSequence) return;
    if (
      !gameAccessService.canViewGame(
        currentUser.value,
        loadedGame.game,
        loadedGame.members.map((member) => member.userId),
      )
    ) {
      hidden.value = true;

      return;
    }
    const loadedNpc = await getGameApi().getNpc(gameId, npcId);
    if (requestSequence !== loadSequence) return;
    if (!npcDetailAccessService.canOpen(currentUser.value, loadedGame, loadedNpc)) {
      hidden.value = true;

      return;
    }
    const viewer = currentUser.value;
    const sections = viewer
      ? sheetAccessService.visibleSheetSections(viewer, loadedNpc.visibility, {
          user: viewer,
          ownerId: null,
          characterId: loadedNpc.id,
          gameId,
        })
      : [];
    gameDetail.value = loadedGame;
    npc.value = loadedNpc;
    visibleSections.value = sections;
  } catch (e) {
    if (requestSequence !== loadSequence) return;
    if (isAccessError(e)) {
      hidden.value = true;

      return;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить НПС';
  } finally {
    if (requestSequence === loadSequence) loading.value = false;
  }
}

function isAccessError(e: unknown): boolean {
  return e instanceof Error && /недоступ|не найден|not found|forbidden/i.test(e.message);
}

watch(
  () => [open.value, props.gameId, props.npcId] as const,
  ([isOpen]) => {
    if (isOpen) void load();
  },
  { immediate: true },
);
</script>

<template>
  <CharacterDetailPanel
    v-model:open="open"
    icon="mdi-account-alert"
    :name="npc?.name ?? ''"
    :chip-label="chipLabel"
    chip-color="warning"
    :game-name="game?.name ?? null"
    :revision-href="revisionHref"
    :revision-label="revisionLabel"
    :version="npc?.version ?? null"
    :space-id="game?.spaceId ?? null"
    :visible-sections="visibleSections"
    :short-description="npc?.shortDescription ?? null"
    :full-description="npc?.fullDescription ?? null"
    :edit-route="canEdit && npc ? `/games/${npc.gameId}/npcs/${npc.id}/edit` : null"
    :page-route="npc ? `/games/${npc.gameId}/npcs/${npc.id}` : null"
    :loading="loading"
    :error="error"
    :hidden="hidden"
    @retry="load"
  />
</template>
