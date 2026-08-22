<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { GameInitiative } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import InitiativeDialog from '@/modules/Roleplay/Game/Component/InitiativeDialog.vue';
import type { ChatMessage } from '@/modules/Messages/Chat/Dto/ChatMessage';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import { combatCardModel, combatExhaustion } from '@/modules/Roleplay/Game/Utils/combatCardModel';

type SystemNotification = { content: string; kind: ChatMessage['kind'] };

/**
 * Шкала инициативы (ТР §8 «Чат игры»). Жизненный цикл: «Инициатива» (окно проверки, ГМ) →
 * активная шкала (порядок + «Передать ход»/«Добавить»/«Закончить») → завершена («Продолжить»).
 * Порядок хода хранится как есть (результат броска не хранится); при передаче хода в чат
 * постится системное уведомление «Ходит Имя». Эмитит `turn` (id активного участника) —
 * для авто-переключения селектора «от лица кого» у владельца хода.
 */
const props = defineProps<{
  gameId: number;
  spaceId: number;
  /** Игровой чат (для системных уведомлений «Ходит Имя»). */
  chatId: number | null;
  /** ГМ управляет шкалой (проверка/добавление/передача/завершение); игроки — просмотр. */
  canEdit: boolean;
  /** Approved-персонажи игры (для выбора в окне проверки и «Добавить»). */
  characters: GameCharacterMembership[];
  /** Активные НПС игры. */
  npcs: GameNpc[];
  /** Правила ревизии игры (характеристики + дефолты «Бросок»). */
  rules: Rule[];
  /** Механики ревизии (броски инициативы через RollEngine). */
  mechanics: Mechanic[];
  /** Счётчик мутаций боевых оверлеев: при изменении — перечитать оверлеи (Истощение). */
  overlayRevision: number;
}>();

const emit = defineEmits<{
  turn: [participantId: string | null];
  /** Открыть боевую карточку участника (entityKey: `character:{id}` | `npc:{id}`). */
  'open-card': [entityKey: string];
}>();

const userStore = useUserStore();
const chatStore = useChatStore();

const initiative = ref<GameInitiative | null>(null);
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);

const dialogOpen = ref(false);
const addMenuOpen = ref(false);

// Оверлеи боевых изменений участников: для текущего Истощения (сумма состояния 'exhaustion').
const overlays = ref<GameCombatOverlay[]>([]);

const currentUser = computed(() => userStore.currentUser);

const activeParticipant = computed(() => {
  const data = initiative.value;
  if (!data || data.activeIndex === null) return null;

  return data.participants[data.activeIndex] ?? null;
});

// «Передать ход»: ГМ или владелец персонажа, чей сейчас ход; НПС — только ГМ.
const canPass = computed(() => {
  if (props.canEdit) return true;
  const participant = activeParticipant.value;
  const user = currentUser.value;
  if (!participant || !user || participant.kind !== 'character' || participant.entityId === null) return false;
  const membership = props.characters.find((item) => item.characterId === participant.entityId);

  return membership?.characterOwnerId === user.id;
});

const addOptions = computed(() => {
  const existing = new Set(initiative.value?.participants.map((participant) => participant.id) ?? []);
  const characters = props.characters
    .filter((membership) => membership.membershipStatus === 'approved')
    .map((membership) => ({
      id: `character:${membership.characterId}`,
      name: membership.characterName,
      kind: 'character' as const,
      entityId: membership.characterId,
    }));
  const npcs = props.npcs
    .filter((npc) => npc.status === 'active')
    .map((npc) => ({ id: `npc:${npc.id}`, name: npc.name, kind: 'npc' as const, entityId: npc.id }));

  return [...characters, ...npcs].filter((option) => !existing.has(option.id));
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    initiative.value = await getGameApi().getInitiative(props.gameId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить шкалу инициативы';
  } finally {
    loading.value = false;
  }
}

/** Оверлеи боевых изменений (текущее Истощение); сбой не блокирует шкалу. */
async function loadOverlays(): Promise<void> {
  try {
    overlays.value = await getGameApi().getCombatOverlays(props.gameId);
  } catch {
    overlays.value = [];
  }
}

// Истощение per участник: эффективное состояние (версия + оверлей) → сумма 'exhaustion'.
const exhaustionByEntity = computed<Map<string, number>>(() => {
  const map = new Map<string, number>();
  for (const participant of initiative.value?.participants ?? []) {
    if (participant.entityId === null) continue;
    const overlay = overlays.value.find((item) => item.entityKey === participant.id) ?? null;
    const model = combatCardModel(
      participant.id as CombatEntityKey,
      props.characters,
      props.npcs,
      false,
      null,
      overlay,
    );
    if (!model.effectiveVersion) continue;
    const value = combatExhaustion(model.effectiveVersion.states, props.rules);
    if (value !== null) map.set(participant.id, value);
  }

  return map;
});

async function save(next: GameInitiative): Promise<void> {
  saving.value = true;
  error.value = null;
  try {
    initiative.value = await getGameApi().saveInitiative(props.gameId, next);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить шкалу';
  } finally {
    saving.value = false;
  }
}

function saveAndNotify(next: GameInitiative, notifications: SystemNotification[]): void {
  void save(next).then(() => {
    if (props.chatId !== null) {
      for (const notification of notifications) {
        void chatStore.postSystemMessage(notification.content, props.chatId, notification.kind);
      }
    }
  });
}

/** Передать ход следующему участнику (цикл по порядку шкалы) + уведомление в чат.
 *  При переходе от последнего участника к первому — новый раунд: номер инкрементится,
 *  постится акцентное уведомление «Новый раунд: N» (kind: highlighted), затем «Ходит Имя». */
function nextTurn(): void {
  const data = initiative.value;
  if (!data || data.participants.length === 0) return;
  const currentIndex = data.activeIndex ?? 0;
  const nextIndex = (currentIndex + 1) % data.participants.length;
  const nextParticipant = data.participants[nextIndex];
  const notifications: SystemNotification[] = [];
  let nextRound = data.round;
  if (currentIndex === data.participants.length - 1) {
    nextRound = data.round + 1;
    notifications.push({ content: `Новый раунд: ${nextRound}`, kind: 'highlighted' });
  }
  if (nextParticipant) notifications.push({ content: `Ходит ${nextParticipant.name}`, kind: 'default' });
  saveAndNotify({ ...data, activeIndex: nextIndex, round: nextRound }, notifications);
}

function endScale(): void {
  const data = initiative.value;
  if (!data) return;
  void save({ ...data, active: false });
}

function continueScale(): void {
  const data = initiative.value;
  if (!data) return;
  void save({ ...data, active: true });
}

function addToBattle(id: string): void {
  const data = initiative.value;
  if (!data) return;
  const option = addOptions.value.find((item) => item.id === id);
  if (!option) return;
  addMenuOpen.value = false;
  void save({
    ...data,
    participants: [
      ...data.participants,
      { id: option.id, name: option.name, kind: option.kind, entityId: option.entityId },
    ],
  });
}

watch(
  () => props.gameId,
  () => {
    void load();
    void loadOverlays();
  },
  { immediate: true },
);

// Правки в боевой карточке (оверлей мутировал) → перечитать оверлеи, чтобы Истощение было актуальным.
watch(
  () => props.overlayRevision,
  () => void loadOverlays(),
);

// Текущий ход: эмит для авто-переключения селектора «от лица кого» у владельца хода.
watch(
  () => initiative.value?.activeIndex,
  () => emit('turn', activeParticipant.value?.id ?? null),
  { immediate: true },
);

function kindIcon(kind: 'character' | 'npc'): string {
  return kind === 'npc' ? 'mdi-robot-outline' : 'mdi-account';
}
</script>

<template>
  <v-card variant="flat" class="initiative-track" border>
    <div class="initiative-track__header">
      <span class="text-subtitle-2 font-weight-medium">
        <v-icon icon="mdi-format-list-numbered" size="18" class="mr-1" />
        Инициатива
      </span>
    </div>
    <v-card-text class="initiative-track__body pa-2">
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-1">{{ error }}</v-alert>
      <div v-if="loading" class="d-flex justify-center pa-3">
        <v-progress-circular indeterminate width="2" size="22" color="primary" />
      </div>

      <template v-else>
        <!-- Нет шкалы: кнопка запуска (ГМ) -->
        <div v-if="!initiative || initiative.participants.length === 0" class="initiative-track__placeholder">
          <v-btn
            v-if="canEdit"
            variant="tonal"
            color="primary"
            size="small"
            block
            prepend-icon="mdi-dice-d6"
            :disabled="saving"
            @click="dialogOpen = true"
          >
            НАЧАТЬ
          </v-btn>
        </div>

        <!-- Активная шкала: порядок хода (сверху вниз) + управление -->
        <div v-else-if="initiative.active" class="initiative-track__active">
          <div class="initiative-track__list">
            <div
              v-for="(participant, index) in initiative.participants"
              :key="participant.id"
              class="initiative-track__row initiative-track__row--clickable"
              :class="{ 'initiative-track__row--active': initiative.activeIndex === index }"
              @click="emit('open-card', participant.id)"
            >
              <v-icon :size="16" class="initiative-track__row-icon">{{ kindIcon(participant.kind) }}</v-icon>
              <span class="initiative-track__row-name">{{ participant.name }}</span>
              <span
                v-if="exhaustionByEntity.has(participant.id)"
                class="initiative-track__exhaustion"
                :title="`Истощение: ${exhaustionByEntity.get(participant.id)}`"
              >
                {{ exhaustionByEntity.get(participant.id) }}
              </span>
            </div>
          </div>

          <div class="initiative-track__actions">
            <v-btn
              size="small"
              variant="tonal"
              color="primary"
              block
              prepend-icon="mdi-skip-next"
              :disabled="!canPass || saving || initiative.participants.length === 0"
              @click="nextTurn"
            >
              Передать ход
            </v-btn>
            <v-menu v-if="canEdit" v-model="addMenuOpen" :close-on-content-click="false">
              <template #activator="{ props: menuProps }">
                <v-btn size="small" variant="tonal" color="success" block prepend-icon="mdi-plus" v-bind="menuProps">
                  Добавить
                </v-btn>
              </template>
              <v-card min-width="240" max-width="300" elevation="8" border>
                <v-card-text class="pa-2">
                  <v-list dense max-height="240">
                    <v-list-item
                      v-for="option in addOptions"
                      :key="option.id"
                      density="compact"
                      :prepend-icon="kindIcon(option.kind)"
                      :title="option.name"
                      @click="addToBattle(option.id)"
                    />
                    <div v-if="!addOptions.length" class="text-caption text-medium-emphasis pa-2 text-center">
                      Некого добавить
                    </div>
                  </v-list>
                </v-card-text>
              </v-card>
            </v-menu>
            <v-btn
              v-if="canEdit"
              size="small"
              variant="text"
              color="warning"
              block
              :disabled="saving"
              @click="endScale"
            >
              Закончить
            </v-btn>
          </div>
        </div>

        <!-- Завершена: «Продолжить» (отмена случайного «Закончить») или новый бросок -->
        <div v-else class="initiative-track__ended">
          <span class="text-caption text-medium-emphasis">Шкала завершена</span>
          <v-btn
            v-if="canEdit"
            size="small"
            variant="tonal"
            color="primary"
            block
            :disabled="saving"
            @click="continueScale"
          >
            Продолжить
          </v-btn>
          <v-btn v-if="canEdit" size="small" variant="outlined" color="primary" block @click="dialogOpen = true">
            Новый бросок
          </v-btn>
        </div>
      </template>
    </v-card-text>
  </v-card>

  <InitiativeDialog
    v-model:open="dialogOpen"
    :game-id="gameId"
    :space-id="spaceId"
    :characters="characters"
    :npcs="npcs"
    :rules="rules"
    :mechanics="mechanics"
    :chat-id="chatId"
    @saved="load"
  />
</template>

<style scoped>
.initiative-track {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.initiative-track__header {
  padding: 8px 12px 2px;
  flex-shrink: 0;
}
.initiative-track__body {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.initiative-track__placeholder {
  padding: 4px 0;
}
.initiative-track__active {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  gap: 8px;
}
.initiative-track__list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.initiative-track__row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px;
  border-radius: 8px;
  border-left: 3px solid transparent;
  font-size: 13px;
  flex-shrink: 0;
}
.initiative-track__row--active {
  background: rgba(var(--v-theme-primary), 0.1);
  border-left-color: rgb(var(--v-theme-primary));
  font-weight: 600;
}
.initiative-track__row--clickable {
  cursor: pointer;
}
.initiative-track__row--clickable:hover {
  background: rgba(var(--v-theme-on-surface), 0.06);
}
.initiative-track__row-icon {
  opacity: 0.7;
  flex-shrink: 0;
}
.initiative-track__row-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.initiative-track__exhaustion {
  margin-left: auto;
  min-width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1px solid rgb(var(--v-theme-warning));
  color: rgb(var(--v-theme-warning));
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  flex-shrink: 0;
}
.initiative-track__actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  padding-top: 8px;
  flex-shrink: 0;
}
.initiative-track__ended {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 4px 0;
}
</style>
