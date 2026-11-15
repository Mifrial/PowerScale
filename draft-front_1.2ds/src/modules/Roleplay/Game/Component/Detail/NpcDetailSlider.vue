<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import type { CharacterDetailNotes } from '@/modules/Roleplay/Character/Dto/CharacterDetailNotes';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CharacterDetailPanel, sheetAccessService } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
import { gameNoteContextService } from '@/modules/Roleplay/Game/Service/Instance/gameNoteContextService';
import { npcDetailAccessService } from '@/modules/Roleplay/Game/Service/Instance/npcDetailAccessService';

/** Деталка НПС в слайдере: шапка, лист по видимости, заметки и действия-ссылки. */
const props = withDefaults(defineProps<{ gameId: number | null; npcId: number | null }>(), {
  gameId: null,
  npcId: null,
});

const open = defineModel<boolean>('open', { default: false });

const { currentUser } = useCurrentUser();
const spaceRevision = useSpaceRevision();

const gameDetail = ref<GameDetail | null>(null);
const npc = ref<GameNpc | null>(null);
const visibleSections = ref<SheetSection[]>([]);
const revisionRules = ref<Rule[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const hidden = ref(false);
let loadSequence = 0;

const game = computed(() => gameDetail.value?.game ?? null);

const rulesRevision = computed(() => npc.value?.version?.rulesRevision ?? game.value?.rulesRevision ?? null);

const canEdit = computed(() => {
  const detail = gameDetail.value;
  if (!detail) return false;

  return gameAccessService.canEditGame(currentUser.value, detail);
});

const chipLabel = computed(() => (npc.value?.status === 'proposed' ? 'На модерации' : null));

const revisionHref = computed(() => {
  const currentGame = game.value;
  const revision = rulesRevision.value;
  if (!currentGame || revision === null) return null;

  return `/space/${currentGame.spaceCode}/${revision}`;
});

const revisionLabel = computed(() => {
  const currentGame = game.value;
  const revision = rulesRevision.value;
  if (!currentGame || revision === null) return null;

  return `Правила: ${currentGame.spaceCode} v${revision}`;
});

// Заметки об НПС живут в хосте `npc`, ссылки — в контексте игры.
const notes = computed<CharacterDetailNotes | null>(() => {
  const current = npc.value;
  const currentGame = game.value;
  if (!current || !currentGame) return null;
  const context = gameNoteContextService.build({
    rules: revisionRules.value,
    mechanics: [],
    spaceId: currentGame.spaceId,
    spaceCode: currentGame.spaceCode,
    rulesRevision: rulesRevision.value,
    gameId: currentGame.id,
  });

  return {
    hostType: 'npc',
    hostId: current.id,
    title: current.name,
    inlineContext: context.inlineContext,
    tokenSources: context.tokenSources,
  };
});

async function loadRules(requestSequence: number): Promise<void> {
  const currentGame = game.value;
  const revision = rulesRevision.value;
  if (!currentGame || revision === null) return;
  try {
    const loaded = await spaceRevision.fetchRevision(currentGame.spaceId, revision);
    if (requestSequence !== loadSequence) return;
    revisionRules.value = loaded.rules;
  } catch {
    if (requestSequence !== loadSequence) return;
    revisionRules.value = [];
  }
}

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
  revisionRules.value = [];
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
    await loadRules(requestSequence);
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
    :notes="notes"
    :edit-route="canEdit && npc ? `/games/${npc.gameId}/npcs/${npc.id}/edit` : null"
    :page-route="npc ? `/games/${npc.gameId}/npcs/${npc.id}` : null"
    :loading="loading"
    :error="error"
    :hidden="hidden"
    @retry="load"
  />
</template>
