<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useCharacterDraft } from '@/modules/Roleplay/Character/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterBuildService } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { gameAccessService } from '@/modules/Roleplay/Game/Service/Instance/gameAccessService';

import { needsNpcMigration } from '@/modules/Roleplay/Game/Utils/npcRevision';
import { CharacterSheetEditor } from '@/modules/Roleplay/Character/init';
import NpcMigrationDialog from '@/modules/Roleplay/Game/Component/Detail/NpcMigrationDialog.vue';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';

const route = useRoute();
const router = useRouter();
const gameStore = useGameStore();
const { currentUser } = useCurrentUser();
const draftStore = useCharacterDraft();
const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();

const loading = ref(false);
const loadError = ref<string | null>(null);
const npcRef = ref<GameNpc | null>(null);
const gameDetailRef = ref<GameDetail | null>(null);
const migrationOpen = ref(false);

const gameId = computed(() => {
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : Number.NaN;
});

const npcId = computed(() => {
  const raw = route.params.npcId;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : Number.NaN;
});

const draftKey = computed<string | null>(() => (Number.isFinite(npcId.value) ? `npc:${npcId.value}` : null));

const stale = computed(() => {
  const npc = npcRef.value;
  const game = gameDetailRef.value?.game;
  if (!npc || !game) return false;

  return needsNpcMigration(npc, { rulesRevision: game.rulesRevision, spaceCode: game.spaceCode });
});

async function loadRules(spaceId: number, revision: number): Promise<Rule[]> {
  const revisionResult = await spaceRevision.fetchRevision(spaceId, revision, signal.value);

  return revisionResult.rules;
}

function emptyBuild(
  npc: GameNpc,
  gameId: number,
  spaceId: number,
  spaceCode: string,
  rulesRevision: number,
): CharacterBuild {
  return {
    name: npc.name,
    shortDescription: npc.shortDescription,
    fullDescription: npc.fullDescription,
    spaceId,
    spaceCode,
    rulesRevision,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
  };
}

function sheetSpaceId(found: GameNpc, gameDetail: GameDetail): number {
  return gameDetail.game.spaceId;
}

function sheetRevision(found: GameNpc, gameDetail: GameDetail): number {
  return found.version?.rulesRevision ?? gameDetail.game.rulesRevision;
}

function draftMatchesSheet(found: GameNpc, gameDetail: GameDetail): boolean {
  const existing = draftStore.draftOf(draftKey.value);
  if (!existing) return false;

  return (
    existing.build.spaceId === sheetSpaceId(found, gameDetail) &&
    existing.build.rulesRevision === sheetRevision(found, gameDetail) &&
    existing.config.osTotal === null &&
    existing.config.orTotal === null &&
    existing.config.moneyBudget === null
  );
}

async function initDraft(found: GameNpc, gameDetail: GameDetail): Promise<void> {
  if (!draftKey.value) return;
  const spaceId = sheetSpaceId(found, gameDetail);
  const rulesRevision = sheetRevision(found, gameDetail);
  const rules = await loadRules(spaceId, rulesRevision);
  const config: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
  const loaded = found.version
    ? characterBuildService.fromVersion(found.version, spaceId, rules)
    : emptyBuild(found, gameDetail.game.id, spaceId, gameDetail.game.spaceCode, rulesRevision);
  draftStore.initDraft(draftKey.value, { ...loaded, money: 0 }, config);
}

async function load(): Promise<void> {
  const gid = gameId.value;
  const nid = npcId.value;
  if (!Number.isFinite(gid) || !Number.isFinite(nid) || !draftKey.value) {
    router.replace({ name: 'NotFound' });

    return;
  }
  loading.value = true;
  loadError.value = null;
  gameStore.clearCurrent();
  try {
    const gameDetail = await gameStore.fetchGame(gid, signal.value);
    if (!gameDetail || !gameAccessService.canEditGame(currentUser.value, gameDetail)) {
      router.replace({ name: 'NotFound' });

      return;
    }
    const found = await getGameApi().getNpc(gid, nid);
    npcRef.value = found;
    gameDetailRef.value = gameDetail;

    if (
      needsNpcMigration(found, { rulesRevision: gameDetail.game.rulesRevision, spaceCode: gameDetail.game.spaceCode })
    ) {
      if (draftKey.value) draftStore.discard(draftKey.value);
    } else if (!draftMatchesSheet(found, gameDetail)) {
      await initDraft(found, gameDetail);
    } else if (draftStore.draftOf(draftKey.value)?.build.money !== 0) {
      draftStore.patchBuild(draftKey.value, { money: 0 });
    }
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить лист НПС';
  } finally {
    loading.value = false;
  }
}

async function onMigrated(): Promise<void> {
  const gid = gameId.value;
  const nid = npcId.value;
  const gameDetail = gameDetailRef.value;
  if (!Number.isFinite(gid) || !Number.isFinite(nid) || !gameDetail || !draftKey.value) return;
  let found: GameNpc;
  try {
    found = await getGameApi().getNpc(gid, nid);
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    loadError.value = e instanceof Error ? e.message : 'Не удалось загрузить лист НПС';

    return;
  }
  draftStore.discard(draftKey.value);
  if (
    !needsNpcMigration(found, { rulesRevision: gameDetail.game.rulesRevision, spaceCode: gameDetail.game.spaceCode })
  ) {
    await initDraft(found, gameDetail);
  }
  npcRef.value = found;
}

/** Сохранение листа НПС тем же документом choices, что character.update. */
async function handleSave(build: CharacterBuild, preview: CharacterVersion): Promise<void> {
  const npc = npcRef.value;
  const game = gameDetailRef.value;
  if (!npc || !game) return;
  try {
    await getGameApi().updateNpc(npc.id, {
      gameId: game.game.id,
      name: preview.name,
      shortDescription: preview.shortDescription,
      fullDescription: preview.fullDescription,
      tags: npc.tags,
      visibility: npc.visibility,
      version: preview,
      choices: build,
      creationConfig: { osTotal: null, orTotal: null, moneyBudget: null },
      spaceId: game.game.spaceId,
      spaceCode: game.game.spaceCode,
      rulesRevision: preview.rulesRevision,
      expectedNpcActualVersion: npc.actualVersion,
    });
    await router.push(`/games/${gameId.value}`);
    draftStore.discard(draftKey.value);
  } catch (e) {
    throw e instanceof Error ? e : new Error('Не удалось сохранить лист НПС');
  }
}

onMounted(load);
</script>

<template>
  <v-container fluid class="pa-0">
    <div v-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <div v-else-if="loadError" class="text-center pa-8">
      <p class="text-body-1 mb-4">{{ loadError }}</p>
      <v-btn color="primary" @click="load">Повторить</v-btn>
    </div>

    <div v-else-if="stale" class="pa-6 d-flex flex-column ga-3" style="max-width: 560px">
      <v-alert type="warning" variant="tonal">
        Лист НПС на другой ревизии правил. Сначала переведите его на ревизию игры — иначе правки запекут сломанный
        remap.
      </v-alert>
      <div class="d-flex ga-2">
        <v-btn color="warning" variant="tonal" @click="migrationOpen = true">Перевести</v-btn>
        <v-btn variant="text" @click="router.push(`/games/${gameId}`)">К игре</v-btn>
      </div>
    </div>

    <CharacterSheetEditor
      v-else-if="npcRef && draftKey"
      :draft-key="draftKey"
      :require-race="false"
      :save-choices="handleSave"
    />

    <NpcMigrationDialog
      v-if="gameDetailRef"
      v-model:open="migrationOpen"
      :game-space-id="gameDetailRef.game.spaceId"
      :game-space-code="gameDetailRef.game.spaceCode"
      :game-rules-revision="gameDetailRef.game.rulesRevision"
      :npc="npcRef"
      @applied="onMigrated"
    />
  </v-container>
</template>
