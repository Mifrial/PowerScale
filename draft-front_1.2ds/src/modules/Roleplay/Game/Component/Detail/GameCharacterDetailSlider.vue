<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import type { CharacterDetailNotes } from '@/modules/Roleplay/Character/Dto/CharacterDetailNotes';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import {
  CharacterDetailPanel,
  characterHttpViewService,
  characterNoteContextService,
  getCharacterApi,
  sheetAccessService,
} from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';
import { gamePhpMode } from '@/modules/Roleplay/Game/Service/Instance/gamePhpMode';
import { GAME_MEMBERSHIP_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { GAME_MEMBERSHIP_STATUS_LABEL } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';

/**
 * Деталка персонажа в контексте игры: лист по видимости членства, шапка с владельцем и
 * действиями-ссылками. Заметки внутри — те же заметки персонажа, что и на его странице.
 */
const props = withDefaults(defineProps<{ gameId: number | null; characterId: number | null }>(), {
  gameId: null,
  characterId: null,
});

const open = defineModel<boolean>('open', { default: false });

const { currentUser } = useCurrentUser();
const spaceRevision = useSpaceRevision();

const gameDetail = ref<GameDetail | null>(null);
const membership = ref<GameCharacterMembership | null>(null);
const version = ref<CharacterVersion | null>(null);
const visibleSections = ref<SheetSection[]>([]);
const revisionRules = ref<Rule[]>([]);
const spaceName = ref<string | null>(null);
const discussionChatId = ref<number | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const hidden = ref(false);
let loadSequence = 0;

const game = computed(() => gameDetail.value?.game ?? null);
const rulesRevision = computed(() => version.value?.rulesRevision ?? game.value?.rulesRevision ?? null);
const hasFullView = computed(() => visibleSections.value.length === SHEET_VISIBLE_SECTIONS.length);
const showDiscussion = computed(() => hasFullView.value && discussionChatId.value !== null);

const raceLabel = computed(() => {
  const code = version.value?.raceRuleCode;
  if (!code) return null;

  return revisionRules.value.find((rule) => rule.code === code)?.name ?? null;
});

const revisionHref = computed(() => {
  const currentGame = game.value;
  if (!currentGame || rulesRevision.value === null) return null;

  return `/space/${currentGame.spaceCode}/${rulesRevision.value}`;
});

const revisionLabel = computed(() => {
  const currentGame = game.value;
  if (!currentGame || rulesRevision.value === null) return null;

  return `Правила: ${spaceName.value ?? currentGame.spaceCode} v${rulesRevision.value}`;
});

const chipLabel = computed(() => {
  const current = membership.value;
  if (!current) return null;

  return GAME_MEMBERSHIP_STATUS_LABEL[current.membershipStatus] ?? current.membershipStatus;
});

const chipColor = computed(() => {
  const current = membership.value;
  if (!current) return null;

  return GAME_MEMBERSHIP_STATUS_COLOR[current.membershipStatus] ?? null;
});

const notes = computed<CharacterDetailNotes | null>(() => {
  const current = membership.value;
  const currentGame = game.value;
  if (!current || !currentGame) return null;
  const context = characterNoteContextService.build({
    rules: revisionRules.value,
    spaceId: currentGame.spaceId,
    spaceCode: currentGame.spaceCode,
    rulesRevision: rulesRevision.value,
    viewer: currentUser.value,
    gameId: currentGame.id,
    entityContext: 'game',
  });

  return {
    hostId: current.characterId,
    title: current.characterName,
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
    spaceName.value = loaded.spaceName;
  } catch {
    if (requestSequence !== loadSequence) return;
    revisionRules.value = [];
    spaceName.value = null;
  }
}

async function loadVersion(
  gameId: number,
  characterId: number,
  currentGame: GameDetail,
): Promise<CharacterVersion | null> {
  if (gamePhpMode.isEnabled()) {
    const pack = await getGameApi().getSheets({
      gameId,
      keys: [{ type: 'character', id: characterId }],
    });
    const row = pack.sheets[0];
    if (!row) return null;

    return characterHttpViewService.versionOf({
      name: row.name,
      spaceCode: currentGame.game.spaceCode,
      rulesRevision: row.rulesRevision ?? currentGame.game.rulesRevision,
      choices: row.choices,
      sheet: row.sheet,
    });
  }
  const projection: GameRuntimeEntityProjection | null = await getGameApi().getRuntimeEntity(
    gameId,
    `character:${characterId}`,
    'full',
  );
  if (!projection || projection.projectionLevel !== 'full') return null;

  return projection.version;
}

async function loadDiscussionChat(requestSequence: number, characterId: number): Promise<void> {
  try {
    const detail = await getCharacterApi().getCharacter(characterId);
    if (requestSequence !== loadSequence) return;
    discussionChatId.value = detail.discussionChatId;
  } catch {
    if (requestSequence !== loadSequence) return;
    discussionChatId.value = null;
  }
}

async function load(): Promise<void> {
  const gameId = props.gameId;
  const characterId = props.characterId;
  if (!open.value || gameId === null || characterId === null || gameId <= 0 || characterId <= 0) return;
  const requestSequence = ++loadSequence;
  loading.value = true;
  error.value = null;
  hidden.value = false;
  gameDetail.value = null;
  membership.value = null;
  version.value = null;
  visibleSections.value = [];
  revisionRules.value = [];
  spaceName.value = null;
  discussionChatId.value = null;
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
    const memberships = await getGameApi().getCharacterList({ gameId });
    if (requestSequence !== loadSequence) return;
    const ownMembership = memberships.find(
      (candidate) => candidate.characterId === characterId && candidate.membershipStatus !== 'left',
    );
    if (!ownMembership) {
      hidden.value = true;

      return;
    }
    const loadedVersion = await loadVersion(gameId, characterId, loadedGame);
    if (requestSequence !== loadSequence) return;
    const viewer = currentUser.value;
    const sections = viewer
      ? sheetAccessService.visibleSheetSections(viewer, ownMembership.visibility, {
          user: viewer,
          ownerId: ownMembership.characterOwnerId,
          characterId,
          gameId,
        })
      : [];
    if (sections.length === 0) {
      hidden.value = true;

      return;
    }
    gameDetail.value = loadedGame;
    membership.value = ownMembership;
    version.value = loadedVersion;
    visibleSections.value = sections;
    await loadRules(requestSequence);
    await loadDiscussionChat(requestSequence, characterId);
  } catch (e) {
    if (requestSequence !== loadSequence) return;
    if (isAccessError(e)) {
      hidden.value = true;

      return;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить персонажа';
  } finally {
    if (requestSequence === loadSequence) loading.value = false;
  }
}

function isAccessError(e: unknown): boolean {
  return e instanceof Error && /недоступ|не найден|not found|forbidden/i.test(e.message);
}

watch(
  () => [open.value, props.gameId, props.characterId] as const,
  ([isOpen]) => {
    if (isOpen) void load();
  },
  { immediate: true },
);
</script>

<template>
  <CharacterDetailPanel
    v-model:open="open"
    :name="membership?.characterName ?? ''"
    :race-label="raceLabel"
    :chip-label="chipLabel"
    :chip-color="chipColor"
    :owner-name="membership?.characterOwnerName ?? null"
    :game-name="game?.name ?? null"
    :revision-href="revisionHref"
    :revision-label="revisionLabel"
    :version="version"
    :space-id="game?.spaceId ?? null"
    :visible-sections="visibleSections"
    :full-description="version?.fullDescription ?? null"
    :discussion-chat-id="discussionChatId"
    :show-discussion="showDiscussion"
    :character-id="membership?.characterId ?? null"
    :show-favorites="true"
    :notes="notes"
    :edit-route="membership ? `/characters/${membership.characterId}/edit` : null"
    :page-route="membership ? `/characters/${membership.characterId}` : null"
    :loading="loading"
    :error="error"
    :hidden="hidden"
    @retry="load"
  />
</template>
