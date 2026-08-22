<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { canEditGame } from '@/modules/Roleplay/Game/Utils/access';
import { CharacterSheetEditor } from '@/modules/Roleplay/Character/init';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const route = useRoute();
const router = useRouter();
const gameStore = useGameStore();
const userStore = useUserStore();
const draftStore = useCharacterDraftStore();
const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const loading = ref(false);
const npcRef = ref<GameNpc | null>(null);

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

async function loadRules(spaceId: number, revision: number): Promise<Rule[]> {
  const revisionResult = await spaceRevisionStore.fetchRevision(spaceId, revision, signal.value);

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
  if (!gameDetail || !canEditGame(userStore.currentUser, gameDetail)) {
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

  if (!draftStore.hasDraft(draftKey.value)) {
    const rules = await loadRules(gameDetail.game.spaceId, gameDetail.game.rulesRevision);
    const config: CharacterCreationConfig = { osTotal: null, orTotal: null, moneyBudget: null };
    const build = found.version
      ? characterBuildService.fromVersion(found.version, gameDetail.game.spaceId, rules)
      : emptyBuild(found, gid, gameDetail.game.spaceId, gameDetail.game.spaceCode, gameDetail.game.rulesRevision);
    draftStore.initDraft(draftKey.value, build, config);
  }
  loading.value = false;
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

    <CharacterSheetEditor v-else-if="draftKey" :draft-key="draftKey" :require-race="false" @save="handleSave" />
  </v-container>
</template>
