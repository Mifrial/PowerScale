<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { GAME_MEMBERSHIP_STATUS_LABEL } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { GAME_MEMBERSHIP_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameMembershipStatus/GAME_MEMBERSHIP_STATUS';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/init';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/init';
import { sheetAccessService } from '@/modules/Roleplay/Character/init';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import type { User } from '@/modules/Core/User/Dto/User';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameMembershipStatus } from '@/modules/Roleplay/Game/Enum/GameMembershipStatus';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import SheetVisibilityDialog from '@/modules/Roleplay/Game/Component/Detail/SheetVisibilityDialog.vue';
import CharacterMigrationDialog from '@/modules/Roleplay/Game/Component/Detail/CharacterMigrationDialog.vue';
import { SheetCard } from '@/modules/Roleplay/Character/init';
import { UniqueRulesTab } from '@/modules/Roleplay/Character/init';
import { sessionCharacterService } from '@/modules/Roleplay/Game/Service/Instance/sessionCharacterService';

const props = defineProps<{
  /** Активна ли вкладка: перезагрузка при активации (v-window не размонтирует вкладки). */
  active: boolean;
  gameId: number;
  /** Цель миграции персонажей — ревизия игры (персонажи должны быть той же ревизии). */
  gameSpaceId: number;
  gameSpaceCode: string;
  gameRulesRevision: number;
  gameOsLimit: number | null;
  gameOrLimit: number | null;
  gameMoneyLimit: number | null;
  canSubmit: boolean;
  canManage: boolean;
  members: GameMember[];
  spaceId: number | null;
  rulesRevision: number | null;
  gameStatus: GameStatus;
}>();

const { currentUser, userId } = useCurrentUser();

const memberships = ref<GameCharacterMembership[]>([]);
const actualById = ref<Record<number, CharacterVersion>>({});
const characterById = ref<Record<number, Character>>({});
const loading = ref(false);
const error = ref<string | null>(null);

const submitOpen = ref(false);
const candidateCharacters = ref<Character[]>([]);
const selectedCharacterId = ref<number | null>(null);
const submitting = ref(false);

const visibilityOpen = ref(false);
const visibilityTarget = ref<GameCharacterMembership | null>(null);

const grantsOpen = ref(false);
const grantsTarget = ref<GameCharacterMembership | null>(null);
const grantsOs = ref(0);
const grantsOr = ref(0);
const grantsOl = ref(0);
const granting = ref(false);

const customRuleOpen = ref(false);
const customRuleTarget = ref<GameCharacterMembership | null>(null);
const customRuleKind = ref<'item' | 'ability'>('item');
const customRuleName = ref('');
const customRuleDescription = ref('');
const customRuleSaving = ref(false);

const uniqueRulesOpen = ref(false);
const uniqueRulesTarget = ref<GameCharacterMembership | null>(null);

const migrationOpen = ref(false);
const migrationTarget = ref<GameCharacterMembership | null>(null);

/**
 * Персонаж требует перехода, если actual на другой ревизии, чем игра.
 */
function needsMigration(membership: GameCharacterMembership): boolean {
  const actual = actualById.value[membership.characterId];
  if (!actual) return false;
  if (membership.membershipStatus === 'left') return false;

  return actual.rulesRevision !== props.gameRulesRevision;
}

function canOwnerAct(membership: GameCharacterMembership): boolean {
  return currentUser.value?.id === membership.characterOwnerId;
}

async function leave(membership: GameCharacterMembership): Promise<void> {
  error.value = null;
  try {
    await getGameApi().leaveGame(props.gameId, membership.characterId);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось покинуть игру';
  }
  await load();
}

function canLeave(membership: GameCharacterMembership): boolean {
  return canOwnerAct(membership) && membership.membershipStatus !== 'left' && props.gameStatus !== 'playing';
}

function openMigration(membership: GameCharacterMembership): void {
  migrationTarget.value = membership;
  migrationOpen.value = true;
}

// После подачи миграции (слайдер закрылся) перечитываем список членств.
watch(migrationOpen, (value) => {
  if (!value) void load();
});

const cardOpen = ref(false);
const cardTarget = ref<GameCharacterMembership | null>(null);

// Игрок видит только approved-членства, видимые по зонам (+ свои, любого статуса); ГМ — все.
const visibleMemberships = computed(() => {
  if (props.canManage) return memberships.value;
  const user = currentUser.value;
  if (!user) return [];

  return memberships.value.filter((membership) => {
    const isOwner = membership.characterOwnerId === user.id;
    if (!sheetAccessService.canSeeSheet(user, membership.visibility, ctxFor(user, membership))) return false;

    return membership.membershipStatus !== 'left' && (membership.membershipStatus === 'active' || isOwner);
  });
});

function ctxFor(user: User, membership: GameCharacterMembership): SheetAccessContext {
  return {
    user,
    ownerId: membership.characterOwnerId,
    characterId: membership.characterId,
    gameId: props.gameId,
  };
}

const cardVisibleSections = computed(() => {
  const target = cardTarget.value;
  const user = currentUser.value;
  if (!target || !user) return [];

  return sheetAccessService.visibleSheetSections(user, target.visibility, ctxFor(user, target));
});

// Эффективная версия листа в игре: полный лист оверлея (in-game редактор) или approved + боевые правки.
const cardVersion = computed<CharacterVersion | null>(() => {
  const target = cardTarget.value;
  if (!target) return null;

  return sessionCharacterService.resolve(target.approvedCharacterVersion, target.overlay);
});

async function load(): Promise<void> {
  loading.value = true;
  error.value = null;
  try {
    memberships.value = await getGameApi().getGameCharacters(props.gameId);
    const actuals: Record<number, CharacterVersion> = {};
    const chars: Record<number, Character> = {};
    await Promise.all(
      memberships.value.map(async (membership) => {
        try {
          const detail = await getCharacterApi().getCharacter(membership.characterId);
          actuals[membership.characterId] = detail.version;
          chars[membership.characterId] = detail.character;
        } catch {
          // лист недоступен
        }
      }),
    );
    actualById.value = actuals;
    characterById.value = chars;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось загрузить персонажей игры';
  } finally {
    loading.value = false;
  }
}

async function openSubmitDialog(): Promise<void> {
  submitOpen.value = true;
  selectedCharacterId.value = null;
  const all = await getCharacterApi().getCharacters();
  const inGame = new Set(memberships.value.map((membership) => membership.characterId));
  candidateCharacters.value = all.filter(
    (character) =>
      character.ownerId === userId.value &&
      character.status === 'ready' &&
      character.active &&
      !inGame.has(character.id),
  );
}

async function submit(): Promise<void> {
  if (selectedCharacterId.value === null) return;
  submitting.value = true;
  error.value = null;
  try {
    await getGameApi().submitCharacterToGame(props.gameId, selectedCharacterId.value);
    submitOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось подать персонажа';
  } finally {
    submitting.value = false;
  }
  await load();
}

// Видимость листа настраивает ГМ или владелец персонажа.
function canConfigureVisibility(membership: GameCharacterMembership): boolean {
  const user = currentUser.value;
  if (!user) return false;

  return props.canManage || user.id === membership.characterOwnerId;
}

function openCard(membership: GameCharacterMembership): void {
  cardTarget.value = membership;
  cardOpen.value = true;
}

function openVisibility(membership: GameCharacterMembership): void {
  visibilityTarget.value = membership;
  visibilityOpen.value = true;
}

async function saveVisibility(visibility: SheetVisibility): Promise<void> {
  const target = visibilityTarget.value;
  if (!target) return;
  error.value = null;
  try {
    await getGameApi().updateMembershipVisibility(props.gameId, target.characterId, visibility);
    visibilityOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить видимость';
  }
  await load();
}

function openGrants(membership: GameCharacterMembership): void {
  grantsTarget.value = membership;
  grantsOs.value = membership.osBonus;
  grantsOr.value = membership.orBonus;
  grantsOl.value = membership.olBonus;
  grantsOpen.value = true;
}

/** ГМ выдаёт кастомное правило («Уникальные правила») персонажу «на ходу». */
function openCustomRule(membership: GameCharacterMembership): void {
  customRuleTarget.value = membership;
  customRuleKind.value = 'item';
  customRuleName.value = '';
  customRuleDescription.value = '';
  customRuleOpen.value = true;
}

/** ГМ управляет записями «Уникальные правила» персонажа (оформить/заменить). */
function openUniqueRules(membership: GameCharacterMembership): void {
  uniqueRulesTarget.value = membership;
  uniqueRulesOpen.value = true;
}

async function saveCustomRule(): Promise<void> {
  const target = customRuleTarget.value;
  if (!target || !customRuleName.value.trim()) return;
  customRuleSaving.value = true;
  error.value = null;
  try {
    await getCharacterApi().addCustomRule(target.characterId, {
      kind: customRuleKind.value,
      name: customRuleName.value.trim(),
      description: customRuleDescription.value.trim() || null,
    });
    customRuleOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось выдать правило';
  } finally {
    customRuleSaving.value = false;
  }
  await load();
}

/** ГМ выдаёт бонусные очки персонажу сверх общих лимитов (реальные лимиты = лимит игры + бонус). */
async function saveGrants(): Promise<void> {
  const target = grantsTarget.value;
  if (!target) return;
  granting.value = true;
  error.value = null;
  try {
    await getGameApi().updateCharacterGrants(props.gameId, target.characterId, {
      osBonus: grantsOs.value,
      orBonus: grantsOr.value,
      olBonus: grantsOl.value,
    });
    grantsOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить очки';
  } finally {
    granting.value = false;
  }
  await load();
}

function membershipStatusLabel(status: GameMembershipStatus): string {
  return GAME_MEMBERSHIP_STATUS_LABEL[status];
}

function membershipStatusColor(status: GameMembershipStatus): string {
  return GAME_MEMBERSHIP_STATUS_COLOR[status];
}

function characterStatusLabel(status: CharacterStatus): string {
  return CHARACTER_STATUS_OPTIONS.find((option) => option.value === status)?.label ?? status;
}

function characterStatusColor(status: CharacterStatus): string {
  return CHARACTER_STATUS_COLOR[status];
}

// Перезагрузка при активации вкладки: члены меняются в ModerateTab, подача/модерация — здесь же.
watch(
  () => props.active,
  (value) => {
    if (value) void load();
  },
  { immediate: true },
);
</script>

<template>
  <v-card>
    <v-card-title class="text-subtitle-1 d-flex align-center">
      <span>Персонажи игры</span>
      <v-spacer />
      <v-btn
        v-if="canSubmit"
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="mdi-account-plus"
        @click="openSubmitDialog"
      >
        Подать персонажа
      </v-btn>
      <v-btn
        v-if="canSubmit"
        variant="outlined"
        color="primary"
        size="small"
        prepend-icon="mdi-account-edit"
        class="ml-2"
        :to="`/games/${gameId}/characters/new`"
      >
        Создать в игре
      </v-btn>
    </v-card-title>
    <v-card-text class="pa-0">
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="ma-3">{{ error }}</v-alert>
      <div v-if="loading" class="d-flex justify-center pa-4">
        <v-progress-circular indeterminate width="2" size="28" color="primary" />
      </div>
      <v-list v-else density="compact" lines="two">
        <v-list-item
          v-for="membership in visibleMemberships"
          :key="membership.characterId"
          @click="openCard(membership)"
        >
          <div class="d-flex align-center ga-2 py-1">
            <v-avatar color="primary" size="28" variant="tonal" class="flex-shrink-0">
              <span class="text-body-2">{{ membership.characterName.charAt(0) }}</span>
            </v-avatar>
            <span class="text-body-2">{{ membership.characterName }}</span>
            <span class="text-caption text-medium-emphasis">владелец: {{ membership.characterOwnerName }}</span>
            <v-spacer />
            <v-chip
              v-if="characterById[membership.characterId]"
              :color="characterStatusColor(characterById[membership.characterId].status)"
              variant="tonal"
              size="x-small"
            >
              {{ characterStatusLabel(characterById[membership.characterId].status) }}
            </v-chip>
            <v-chip v-if="membership.reviewState === 'returned'" color="warning" variant="tonal" size="x-small">
              На доработку
            </v-chip>
            <v-chip
              v-else-if="membership.reviewState === 'changes_pending'"
              color="info"
              variant="tonal"
              size="x-small"
            >
              Есть изменения
            </v-chip>
            <v-chip :color="membershipStatusColor(membership.membershipStatus)" variant="tonal" size="x-small">
              {{ membershipStatusLabel(membership.membershipStatus) }}
            </v-chip>
            <v-chip
              v-if="needsMigration(membership)"
              color="warning"
              variant="tonal"
              size="x-small"
              class="flex-shrink-0"
            >
              Требует перехода
            </v-chip>
            <v-btn
              v-if="needsMigration(membership) && canOwnerAct(membership)"
              size="x-small"
              variant="tonal"
              color="warning"
              class="flex-shrink-0"
              @click.stop="openMigration(membership)"
            >
              Перевести
            </v-btn>
            <v-btn
              v-if="canLeave(membership)"
              size="x-small"
              variant="tonal"
              color="error"
              class="flex-shrink-0"
              @click.stop="leave(membership)"
            >
              Покинуть
            </v-btn>
            <v-btn
              v-if="canManage"
              icon
              variant="text"
              size="x-small"
              title="Выдать очки"
              @click.stop="openGrants(membership)"
            >
              <v-icon>mdi-trophy-outline</v-icon>
            </v-btn>
            <v-btn
              v-if="canManage && membership.membershipStatus === 'active'"
              icon
              variant="text"
              size="x-small"
              title="Выдать кастомное правило"
              @click.stop="openCustomRule(membership)"
            >
              <v-icon>mdi-file-document-plus-outline</v-icon>
            </v-btn>
            <v-btn
              v-if="canManage && membership.membershipStatus === 'active'"
              icon
              variant="text"
              size="x-small"
              title="Уникальные правила"
              @click.stop="openUniqueRules(membership)"
            >
              <v-icon>mdi-notebook-outline</v-icon>
            </v-btn>
            <v-btn
              v-if="membership.membershipStatus === 'active' && canConfigureVisibility(membership)"
              icon
              variant="text"
              size="x-small"
              title="Видимость листа"
              @click.stop="openVisibility(membership)"
            >
              <v-icon>mdi-eye-settings-outline</v-icon>
            </v-btn>
          </div>
        </v-list-item>
      </v-list>
      <div v-if="!loading && visibleMemberships.length === 0" class="text-center text-medium-emphasis pa-6">
        Персонажей в игре пока нет
      </div>
    </v-card-text>
  </v-card>

  <v-dialog v-model="submitOpen" max-width="480">
    <v-card>
      <v-card-title>Подать персонажа</v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-3">
          В игру подаются только готовые персонажи. После подачи они уходят на модерацию ведущему.
        </p>
        <v-select
          v-model="selectedCharacterId"
          label="Готовый персонаж"
          :items="candidateCharacters"
          item-title="name"
          item-value="id"
          hint="Ваши готовые персонажи, ещё не в этой игре"
          persistent-hint
        />
      </v-card-text>
      <v-card-actions>
        <v-btn
          color="primary"
          :disabled="selectedCharacterId === null || submitting"
          :loading="submitting"
          @click="submit"
        >
          Подать
        </v-btn>
        <v-btn variant="text" @click="submitOpen = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="cardOpen" max-width="560">
    <v-card v-if="cardTarget">
      <v-card-title class="d-flex align-center">
        <span class="text-h6">Персонаж</span>
        <v-spacer />
        <v-btn icon variant="text" size="small" @click="cardOpen = false"><v-icon>mdi-close</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <SheetCard
          :name="cardTarget.characterName"
          :version="cardVersion"
          :visible-sections="cardVisibleSections"
          :space-id="spaceId"
          :rules-revision="rulesRevision"
        />
        <div class="text-center mt-3 d-flex justify-center ga-2">
          <v-btn
            v-if="cardTarget?.membershipStatus === 'active'"
            variant="tonal"
            color="primary"
            size="small"
            prepend-icon="mdi-account-edit"
            :to="`/characters/${cardTarget.characterId}/edit?gameId=${gameId}`"
          >
            Редактировать в игре
          </v-btn>
          <v-btn
            v-else-if="currentUser?.id === cardTarget.characterOwnerId"
            variant="tonal"
            color="primary"
            size="small"
            prepend-icon="mdi-account-edit"
            :to="`/characters/${cardTarget.characterId}/edit`"
          >
            Редактировать
          </v-btn>
          <v-btn
            v-if="currentUser?.id === cardTarget.characterOwnerId"
            variant="text"
            color="primary"
            size="small"
            :to="`/characters/${cardTarget.characterId}`"
          >
            Открыть страницу персонажа
          </v-btn>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>

  <v-dialog v-model="grantsOpen" max-width="440">
    <v-card>
      <v-card-title class="text-subtitle-1">Выдать очки — {{ grantsTarget?.characterName }}</v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-3">
          Бонусные очки сверх общих лимитов игры (персонаж получил их за действия). Реальные лимиты персонажа = лимит
          игры + бонус. Деньги выдаются через добычу.
        </p>
        <div class="d-flex ga-4">
          <v-text-field v-model.number="grantsOs" label="ОС" type="number" density="compact" hide-details />
          <v-text-field v-model.number="grantsOr" label="ОР" type="number" density="compact" hide-details />
          <v-text-field v-model.number="grantsOl" label="ОЛ" type="number" density="compact" hide-details />
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" variant="tonal" :loading="granting" @click="saveGrants">Сохранить</v-btn>
        <v-btn variant="text" @click="grantsOpen = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <CharacterMigrationDialog
    v-model:open="migrationOpen"
    :game-id="gameId"
    :game-space-id="gameSpaceId"
    :game-space-code="gameSpaceCode"
    :game-rules-revision="gameRulesRevision"
    :game-os-limit="gameOsLimit"
    :game-or-limit="gameOrLimit"
    :game-money-limit="gameMoneyLimit"
    :membership="migrationTarget"
  />

  <v-dialog v-model="customRuleOpen" max-width="480">
    <v-card>
      <v-card-title class="text-subtitle-1">
        Выдать кастомное правило — {{ customRuleTarget?.characterName }}
      </v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-3">
          Текстовая запись «на ходу» (имя + описание), появится у персонажа во вкладке «Уникальные правила». Позже её
          можно оформить как настоящее правило.
        </p>
        <v-select
          v-model="customRuleKind"
          label="Тип"
          :items="[
            { title: 'Предмет', value: 'item' },
            { title: 'Способность', value: 'ability' },
          ]"
          item-title="title"
          item-value="value"
          density="compact"
          class="mb-3"
        />
        <v-text-field v-model="customRuleName" label="Название" density="compact" class="mb-3" />
        <v-textarea v-model="customRuleDescription" label="Описание" density="compact" rows="3" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn
          color="primary"
          variant="tonal"
          :disabled="!customRuleName.trim()"
          :loading="customRuleSaving"
          @click="saveCustomRule"
        >
          Выдать
        </v-btn>
        <v-btn variant="text" @click="customRuleOpen = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="uniqueRulesOpen" max-width="560">
    <v-card v-if="uniqueRulesTarget">
      <v-card-title class="text-subtitle-1 d-flex align-center">
        <span>Уникальные правила — {{ uniqueRulesTarget.characterName }}</span>
        <v-spacer />
        <v-btn icon variant="text" size="small" @click="uniqueRulesOpen = false"><v-icon>mdi-close</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <UniqueRulesTab
          v-if="actualById[uniqueRulesTarget.characterId]"
          :version="actualById[uniqueRulesTarget.characterId]"
          :character-id="uniqueRulesTarget.characterId"
          :can-manage="true"
          :space-id="spaceId ?? undefined"
          :rules-revision="rulesRevision ?? undefined"
          @updated="load()"
        />
      </v-card-text>
    </v-card>
  </v-dialog>

  <SheetVisibilityDialog
    v-model:open="visibilityOpen"
    :title="`Видимость листа — ${visibilityTarget?.characterName ?? ''}`"
    :visibility="visibilityTarget?.visibility ?? null"
    :members="members"
    @save="saveVisibility"
  />
</template>
