<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { GAME_LOOT_STATUS_LABEL, GAME_LOOT_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/Loot/GAME_LOOT_STATUS';
import type { GameLoot, GameLootDistribution } from '@/modules/Roleplay/Game/Dto/GameLoot';
import type { GameLootStatus } from '@/modules/Roleplay/Game/Enum/GameLootStatus';
import type { CreateLootData } from '@/modules/Roleplay/Game/Dto/CreateLootData';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { User } from '@/modules/Core/User/Dto/User';
import LootFormDialog from '@/modules/Roleplay/Game/Component/Detail/LootFormDialog.vue';
import LootDistributeDialog from '@/modules/Roleplay/Game/Component/Detail/LootDistributeDialog.vue';

interface LootGroup {
  name: string;
  loots: GameLoot[];
}

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  canManage: boolean;
  isMember: boolean;
  spaceId: number | null;
  rulesRevision: number | null;
}>();

const userStore = useUserStore();
const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const loots = ref<GameLoot[]>([]);
const npcs = ref<GameNpc[]>([]);
const characters = ref<GameCharacterMembership[]>([]);
const rules = ref<Rule[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const actionError = ref<string | null>(null);

const formOpen = ref(false);
const formInitial = ref<GameLoot | null>(null);
const distributeTarget = ref<GameLoot | null>(null);
const confirmDeleteId = ref<number | null>(null);

const distributeOpen = computed({
  get: () => distributeTarget.value !== null,
  set: (value: boolean) => {
    if (!value) distributeTarget.value = null;
  },
});

const confirmDeleteOpen = computed({
  get: () => confirmDeleteId.value !== null,
  set: (value: boolean) => {
    if (!value) confirmDeleteId.value = null;
  },
});

const currentUser = computed<User | null>(() => userStore.currentUser);

const itemNameById = computed(
  () => new Map(rules.value.filter((rule) => rule.type === 'item').map((rule) => [rule.id, rule.name])),
);

const groupOptions = computed(() =>
  [...new Set(loots.value.map((loot) => loot.group).filter((group): group is string => group !== null))].sort(),
);

const statusOrder: Record<GameLootStatus, number> = { prepared: 0, available: 1, distributed: 2 };

const groupedLoots = computed<LootGroup[]>(() => {
  const map = new Map<string, GameLoot[]>();
  for (const loot of loots.value) {
    const key = loot.group ?? 'Без группы';
    if (!map.has(key)) map.set(key, []);
    map.get(key)?.push(loot);
  }

  return [...map.entries()]
    .map(([name, list]) => ({
      name,
      loots: [...list].sort(
        (a, b) => statusOrder[a.status] - statusOrder[b.status] || a.createdAt.localeCompare(b.createdAt),
      ),
    }))
    .sort((a, b) => {
      if (a.name === 'Без группы') return 1;
      if (b.name === 'Без группы') return -1;

      return a.name.localeCompare(b.name);
    });
});

const availableLoots = computed(() => loots.value.filter((loot) => loot.status === 'available'));
const distributedLoots = computed(() => loots.value.filter((loot) => loot.status === 'distributed'));

function titleOf(loot: GameLoot): string {
  if (loot.itemRuleId !== null) {
    const name = itemNameById.value.get(loot.itemRuleId) ?? loot.itemRuleId;

    return loot.quantity > 1 ? `${name} ×${loot.quantity}` : name;
  }

  return `Деньги: ${loot.moneyAmount ?? 0} гм`;
}

function isMoney(loot: GameLoot): boolean {
  return loot.itemRuleId === null;
}

function isInterested(loot: GameLoot): boolean {
  return currentUser.value !== null && loot.interestedUserIds.includes(currentUser.value.id);
}

function distributionSummary(loot: GameLoot): string {
  return loot.distribution
    .map((entry) => {
      const name =
        entry.type === 'character'
          ? (entry.characterName ?? 'Персонаж')
          : entry.type === 'npc'
            ? `НПС: ${entry.npcName ?? '?'}`
            : 'Вникуда';

      return entry.amount !== null && entry.amount !== undefined ? `${name} (${entry.amount})` : name;
    })
    .join(', ');
}

async function loadRules(): Promise<void> {
  if (props.spaceId === null || props.rulesRevision === null) {
    rules.value = [];

    return;
  }
  try {
    const revision = await spaceRevisionStore.fetchRevision(props.spaceId, props.rulesRevision, signal.value);
    rules.value = revision.rules;
  } catch {
    rules.value = [];
  }
}

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    const [lootList, npcList, characterList] = await Promise.all([
      getGameApi().getLoot(props.gameId),
      getGameApi().getNpcs(props.gameId),
      getGameApi().getGameCharacters(props.gameId),
    ]);
    loots.value = lootList;
    npcs.value = npcList;
    characters.value = characterList;
    await loadRules();
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить добычу';
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

function openEdit(loot: GameLoot): void {
  formInitial.value = loot;
  formOpen.value = true;
}

async function saveForm(data: CreateLootData): Promise<void> {
  const initial = formInitial.value;
  await run(async () => {
    if (initial) {
      await getGameApi().updateLoot(initial.id, data);
    } else {
      await getGameApi().addLoot(props.gameId, data);
    }
    formOpen.value = false;
    await load();
  });
}

async function handout(lootIds: number[]): Promise<void> {
  if (lootIds.length === 0) return;
  await run(async () => {
    await getGameApi().handoutLoot(lootIds);
    await load();
  });
}

function preparedIdsInGroup(groupName: string): number[] {
  const key = groupName === 'Без группы' ? null : groupName;

  return loots.value
    .filter((loot) => loot.status === 'prepared' && (loot.group ?? null) === key)
    .map((loot) => loot.id);
}

function handoutGroup(groupName: string): void {
  void handout(preparedIdsInGroup(groupName));
}

async function toggleInterest(loot: GameLoot): Promise<void> {
  await run(async () => {
    const updated = await getGameApi().toggleLootInterest(loot.id);
    const idx = loots.value.findIndex((entry) => entry.id === updated.id);
    if (idx !== -1) loots.value[idx] = updated;
  });
}

function openDistribute(loot: GameLoot): void {
  distributeTarget.value = loot;
}

async function distribute(distribution: GameLootDistribution[]): Promise<void> {
  const target = distributeTarget.value;
  if (!target) return;
  await run(async () => {
    await getGameApi().distributeLoot(target.id, { distribution });
    distributeTarget.value = null;
    await load();
  });
}

function askDelete(loot: GameLoot): void {
  confirmDeleteId.value = loot.id;
}

async function doDelete(): Promise<void> {
  const id = confirmDeleteId.value;
  if (id === null) return;
  await run(async () => {
    await getGameApi().deleteLoot(id);
    confirmDeleteId.value = null;
    await load();
  });
}

// Перезагрузка при активации вкладки (интерес/раздача могут меняться и на других экранах).
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
    <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>

    <v-card>
      <v-card-title class="text-subtitle-1 d-flex align-center">
        <span>Добыча</span>
        <v-spacer />
        <v-btn
          v-if="canManage"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          Добавить лут
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <div v-if="loading" class="d-flex justify-center pa-4">
          <v-progress-circular indeterminate width="2" size="28" color="primary" />
        </div>

        <!-- Ведущий: все статусы по группам -->
        <template v-else-if="canManage">
          <div v-if="groupedLoots.length === 0" class="text-center text-medium-emphasis pa-6">
            Добычи пока нет. Добавьте лут в запас, чтобы подготовить его заранее.
          </div>

          <div v-for="group in groupedLoots" :key="group.name" class="border-t">
            <div class="d-flex align-center px-3 pt-2">
              <span class="text-subtitle-2">{{ group.name }}</span>
              <v-spacer />
              <v-btn
                v-if="preparedIdsInGroup(group.name).length > 0"
                size="x-small"
                variant="tonal"
                prepend-icon="mdi-hand-back-right-outline"
                @click="handoutGroup(group.name)"
              >
                Выдать группу на разбор
              </v-btn>
            </div>
            <v-list density="compact">
              <v-list-item v-for="loot in group.loots" :key="loot.id">
                <div class="d-flex align-center ga-2 py-1">
                  <v-icon size="small" :icon="isMoney(loot) ? 'mdi-cash' : 'mdi-sword'" class="text-medium-emphasis" />
                  <span class="text-body-2">{{ titleOf(loot) }}</span>
                  <v-chip :color="GAME_LOOT_STATUS_COLOR[loot.status]" variant="tonal" size="x-small">
                    {{ GAME_LOOT_STATUS_LABEL[loot.status] }}
                  </v-chip>
                  <v-chip v-if="loot.interestedUserIds.length > 0" size="x-small" variant="outlined">
                    {{ loot.interestedUserIds.length }} интерес
                  </v-chip>
                  <span v-if="loot.status === 'distributed'" class="text-caption text-medium-emphasis">
                    → {{ distributionSummary(loot) }}
                  </span>
                  <v-spacer />

                  <template v-if="loot.status === 'prepared'">
                    <v-btn size="x-small" variant="tonal" @click="handout([loot.id])">На разбор</v-btn>
                    <v-btn size="x-small" variant="text" @click="openEdit(loot)">Правка</v-btn>
                    <v-btn size="x-small" variant="text" color="error" @click="askDelete(loot)">Удалить</v-btn>
                  </template>
                  <v-btn
                    v-else-if="loot.status === 'available'"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-handshake-outline"
                    @click="openDistribute(loot)"
                  >
                    {{ isMoney(loot) ? 'Разделить' : 'Раздать' }}
                  </v-btn>
                </div>
                <div v-if="loot.notes" class="text-caption text-medium-emphasis pl-8">{{ loot.notes }}</div>
              </v-list-item>
            </v-list>
          </div>
        </template>

        <!-- Игрок: на разборе + роздано -->
        <template v-else-if="isMember">
          <div class="text-caption text-medium-emphasis px-3 pt-3">На разборе</div>
          <v-list v-if="availableLoots.length > 0" density="compact">
            <v-list-item v-for="loot in availableLoots" :key="loot.id">
              <div class="d-flex align-center ga-2 py-1">
                <v-icon size="small" :icon="isMoney(loot) ? 'mdi-cash' : 'mdi-sword'" class="text-medium-emphasis" />
                <span class="text-body-2">{{ titleOf(loot) }}</span>
                <v-chip
                  v-if="loot.interestedUserIds.length > 0"
                  size="x-small"
                  variant="outlined"
                  class="flex-shrink-0"
                >
                  {{ loot.interestedUserIds.length }} интерес
                </v-chip>
                <v-spacer />
                <v-btn
                  size="x-small"
                  :variant="isInterested(loot) ? 'tonal' : 'outlined'"
                  :color="isInterested(loot) ? 'primary' : undefined"
                  @click="toggleInterest(loot)"
                >
                  {{ isInterested(loot) ? 'Интерес отмечен' : 'Проявить интерес' }}
                </v-btn>
              </div>
              <div v-if="loot.notes" class="text-caption text-medium-emphasis pl-8">{{ loot.notes }}</div>
            </v-list-item>
          </v-list>
          <div v-else class="text-center text-medium-emphasis pa-4">Лута на разборе пока нет</div>

          <div v-if="distributedLoots.length > 0" class="border-t">
            <div class="text-caption text-medium-emphasis px-3 pt-3">Роздано</div>
            <v-list density="compact">
              <v-list-item v-for="loot in distributedLoots" :key="loot.id">
                <div class="d-flex align-center ga-2 py-1">
                  <v-icon size="small" :icon="isMoney(loot) ? 'mdi-cash' : 'mdi-sword'" class="text-medium-emphasis" />
                  <span class="text-body-2">{{ titleOf(loot) }}</span>
                  <span class="text-caption text-medium-emphasis">→ {{ distributionSummary(loot) }}</span>
                </div>
              </v-list-item>
            </v-list>
          </div>
        </template>

        <div v-else-if="!loading" class="text-center text-medium-emphasis pa-6">Добыча доступна участникам игры</div>
      </v-card-text>
    </v-card>
  </div>

  <LootFormDialog
    v-model:open="formOpen"
    :rules="rules"
    :group-options="groupOptions"
    :initial="formInitial"
    @save="saveForm"
  />

  <LootDistributeDialog
    v-model:open="distributeOpen"
    :loot="distributeTarget"
    :characters="characters"
    :npcs="npcs"
    @distribute="distribute"
  />

  <v-dialog v-model="confirmDeleteOpen" max-width="420">
    <v-card>
      <v-card-text>Удалить запись добычи?</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="error" variant="tonal" @click="doDelete">Удалить</v-btn>
        <v-btn variant="text" @click="confirmDeleteId = null">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
