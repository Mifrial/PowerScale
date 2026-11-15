<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterDetailNotes } from '@/modules/Roleplay/Character/Dto/CharacterDetailNotes';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import {
  CharacterApiError,
  characterAccessService,
  getCharacterApi,
  sheetAccessService,
} from '@/modules/Roleplay/Character/init';
import { characterNoteContextService } from '@/modules/Roleplay/Character/Service/Instance/characterNoteContextService';
import CharacterDetailPanel from '@/modules/Roleplay/Character/Component/Detail/CharacterDetailPanel.vue';

/**
 * Standalone-деталка персонажа в слайдере: грузит лист и ревизию правил по id,
 * не трогая глобальный стор страницы персонажа.
 */
const props = withDefaults(defineProps<{ characterId: number | null }>(), { characterId: null });

const open = defineModel<boolean>('open', { default: false });

const { currentUser } = useCurrentUser();
const spaceRevision = useSpaceRevision();

const detail = ref<CharacterDetail | null>(null);
const revisionRules = ref<Rule[]>([]);
const spaceName = ref<string | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const hidden = ref(false);
let loadSequence = 0;

const character = computed(() => detail.value?.character ?? null);

const visibleSections = computed<SheetSection[]>(() => {
  const current = detail.value;
  if (!current) return [];
  if (current.visibleSections) return current.visibleSections;

  const viewer = currentUser.value;
  if (!viewer) return [];

  return sheetAccessService.visibleSheetSections(viewer, current.character.visibility, {
    user: viewer,
    ownerId: current.character.ownerId,
    characterId: current.character.id,
    gameId: null,
  });
});

const hasFullView = computed(() => visibleSections.value.length === SHEET_VISIBLE_SECTIONS.length);

const canEdit = computed(() => {
  const current = character.value;
  if (!current) return false;

  return characterAccessService.canEditCharacter(currentUser.value, current);
});

const raceLabel = computed(() => {
  const current = detail.value;
  if (!current) return null;
  if (current.character.raceLabel) return current.character.raceLabel;
  const code = current.version.raceRuleCode;
  if (!code) return null;

  return revisionRules.value.find((rule) => rule.code === code)?.name ?? null;
});

const revisionHref = computed(() => {
  const current = detail.value;
  if (!current) return null;

  return `/space/${current.character.spaceCode}/${current.version.rulesRevision}`;
});

const revisionLabel = computed(() => {
  const current = detail.value;
  if (!current) return null;

  return `Правила: ${spaceName.value ?? current.character.spaceCode} v${current.version.rulesRevision}`;
});

const notes = computed<CharacterDetailNotes | null>(() => {
  const current = detail.value;
  if (!current) return null;
  const context = characterNoteContextService.build({
    rules: revisionRules.value,
    spaceId: current.character.spaceId,
    spaceCode: current.character.spaceCode,
    rulesRevision: current.version.rulesRevision,
    viewer: currentUser.value,
    gameId: current.character.gameId,
    entityContext: 'standalone',
  });

  return {
    hostType: 'character',
    hostId: current.character.id,
    title: current.character.name,
    inlineContext: context.inlineContext,
    tokenSources: context.tokenSources,
  };
});

async function loadRules(spaceId: number, rulesRevision: number, requestSequence: number): Promise<void> {
  try {
    const revision = await spaceRevision.fetchRevision(spaceId, rulesRevision);
    if (requestSequence !== loadSequence) return;
    revisionRules.value = revision.rules;
    spaceName.value = revision.spaceName;
  } catch {
    if (requestSequence !== loadSequence) return;
    revisionRules.value = [];
    spaceName.value = null;
  }
}

async function load(): Promise<void> {
  const id = props.characterId;
  if (!open.value || id === null || !Number.isInteger(id) || id <= 0) return;
  const requestSequence = ++loadSequence;
  loading.value = true;
  error.value = null;
  hidden.value = false;
  detail.value = null;
  revisionRules.value = [];
  spaceName.value = null;
  try {
    const loaded = await getCharacterApi().getCharacter(id);
    if (requestSequence !== loadSequence) return;
    const canView =
      (loaded.visibleSections?.length ?? 0) > 0 ||
      characterAccessService.canViewCharacter(currentUser.value, loaded.character);
    if (!canView) {
      hidden.value = true;

      return;
    }
    detail.value = loaded;
    await loadRules(loaded.character.spaceId, loaded.version.rulesRevision, requestSequence);
  } catch (e) {
    if (requestSequence !== loadSequence) return;
    if (isHiddenError(e)) {
      hidden.value = true;

      return;
    }
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить персонажа';
  } finally {
    if (requestSequence === loadSequence) loading.value = false;
  }
}

function isHiddenError(e: unknown): boolean {
  if (e instanceof CharacterApiError) return /notfound|forbidden|denied/i.test(e.code);

  return e instanceof Error && /not found|не найден|недоступ|forbidden/i.test(e.message);
}

watch(
  () => [open.value, props.characterId] as const,
  ([isOpen]) => {
    if (isOpen) void load();
  },
  { immediate: true },
);
</script>

<template>
  <CharacterDetailPanel
    v-model:open="open"
    :name="character?.name ?? ''"
    :race-label="raceLabel"
    :status="character?.status ?? null"
    :owner-name="character?.ownerName ?? null"
    :game-name="character?.gameName ?? null"
    :revision-href="revisionHref"
    :revision-label="revisionLabel"
    :version="detail?.version ?? null"
    :space-id="character?.spaceId ?? null"
    :visible-sections="visibleSections"
    :short-description="character?.shortDescription ?? null"
    :full-description="detail?.version.fullDescription ?? null"
    :discussion-chat-id="detail?.discussionChatId ?? null"
    :show-discussion="hasFullView"
    :character-id="character?.id ?? null"
    :show-favorites="true"
    :notes="notes"
    :edit-route="canEdit && character ? `/characters/${character.id}/edit` : null"
    :migrate-route="canEdit && character ? `/characters/${character.id}/migrate` : null"
    :page-route="character ? `/characters/${character.id}` : null"
    :loading="loading"
    :error="error"
    :hidden="hidden"
    @retry="load"
  />
</template>
