<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/Space/init';
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
    raceRuleId: null,
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

async function initDraft(found: GameNpc, gameDetail: GameDetail): Promise<void> {
  if (!draftKey.value) return;
  const rules = await loadRules(gameDetail.game.spaceId, gameDetail.game.rulesRevision);
  const config: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
  const build = found.version
    ? characterBuildService.fromVersion(found.version, gameDetail.game.spaceId, rules)
    : emptyBuild(
        found,
        gameDetail.game.id,
        gameDetail.game.spaceId,
        gameDetail.game.spaceCode,
        gameDetail.game.rulesRevision,
      );
  draftStore.initDraft(draftKey.value, build, config);
}

async function load(): Promise<void> {
  const gid = gameId.value;
  const nid = npcId.value;
  if (!Number.isFinite(gid) || !Number.isFinite(nid) || !draftKey.value) {
    router.replace({ name: 'NotFound' });

    return;
  }
  loading.value = true;
  gameStore.clearCurrent();
  const gameDetail = await gameStore.fetchGame(gid, signal.value);
  if (!gameDetail || !gameAccessService.canEditGame(currentUser.value, gameDetail)) {
    router.replace({ name: 'NotFound' });

    return;
  }
  const npcs = await getGameApi().getNpcs(gid);
  const found = npcs.find((npc) => npc.id === nid);
  if (!found) {
    router.replace({ name: 'NotFound' });

    return;
  }
  npcRef.value = found;
  gameDetailRef.value = gameDetail;

  if (
    needsNpcMigration(found, { rulesRevision: gameDetail.game.rulesRevision, spaceCode: gameDetail.game.spaceCode })
  ) {
    if (draftKey.value) draftStore.discard(draftKey.value);
  } else if (!draftStore.hasDraft(draftKey.value)) {
    await initDraft(found, gameDetail);
  }
  loading.value = false;
}

async function onMigrated(): Promise<void> {
  const gid = gameId.value;
  const nid = npcId.value;
  const gameDetail = gameDetailRef.value;
  if (!Number.isFinite(gid) || !Number.isFinite(nid) || !gameDetail || !draftKey.value) return;
  const npcs = await getGameApi().getNpcs(gid);
  const found = npcs.find((npc) => npc.id === nid);
  if (!found) return;
  draftStore.discard(draftKey.value);
  if (
    !needsNpcMigration(found, { rulesRevision: gameDetail.game.rulesRevision, spaceCode: gameDetail.game.spaceCode })
  ) {
    await initDraft(found, gameDetail);
  }
  npcRef.value = found;
}

/** Сохранение листа НПС: version + согласованные top-level имя/описания; теги/видимость сохраняются. */
async function handleSave(version: CharacterVersion): Promise<void> {
  const npc = npcRef.value;
  if (!npc) return;
  try {
    await getGameApi().updateNpc(npc.id, {
      name: version.name,
      shortDescription: version.shortDescription,
      fullDescription: version.fullDescription,
      tags: npc.tags,
      visibility: npc.visibility,
      version,
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

    <CharacterSheetEditor v-else-if="draftKey" :draft-key="draftKey" :require-race="false" @save="handleSave" />

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
