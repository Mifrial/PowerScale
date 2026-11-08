<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { displayName, useCurrentUser, useUserCatalog } from '@/modules/Core/User/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_COLOR';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { useCharacterCardDraft } from '@/modules/Roleplay/Character/Composables/useCharacterCardDraft';
import { characterChangePort, getCharacterCardExtensions } from '@/modules/Roleplay/Character/init';
import { characterChatRulesContextService } from '@/modules/Roleplay/Character/Service/Instance/characterChatRulesContextService';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import { NotesSlider } from '@/modules/Messages/Notes/init';
import { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import CharacterSheetBody from '@/modules/Roleplay/Character/Component/Detail/CharacterSheetBody.vue';

const route = useRoute();
const router = useRouter();
const store = useCharacterStore();
const draftStore = useCharacterDraftStore();
const { currentUser } = useCurrentUser();
const { users, ensureUsers } = useUserCatalog();
const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();
const detail = computed(() => store.currentCharacter);
const detailError = computed(() => store.detailError);
const detailLoading = computed(() => store.detailLoading);

const rules = ref<Rule[]>([]);
const rulesLoading = ref(false);
const rulesError = ref<string | null>(null);
const spaceName = ref<string | null>(null);

const characterId = computed(() => {
  const raw = route.params.id;
  if (typeof raw !== 'string') return Number.NaN;

  return Number(raw);
});

function canViewDetail(current: CharacterDetail): boolean {
  // Backend уже отдал разрешённые секции; повторная клиентская фильтрация по пустому visibility
  // ошибочно закрывала бы чужой лист, выданный зрителю.
  if (current.visibleSections && current.visibleSections.length > 0) return true;

  return characterAccessService.canViewCharacter(currentUser.value, current.character);
}

const canView = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return canViewDetail(current);
});

const canEdit = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return characterAccessService.canEditCharacter(currentUser.value, current.character);
});

const externalChangePending = ref(false);

const {
  draftKey: sheetDraftKey,
  draft: sheetDraft,
  build: sheetBuild,
  model: sheetModel,
  displayVersion,
  validationIssues,
  saving: sheetSaving,
  saveError: sheetSaveError,
  saveConflict: sheetSaveConflict,
  catalogError: sheetCatalogError,
  keywords: sheetKeywords,
  ensureDraft,
  save: saveSheet,
  retrySave: retrySheetSave,
  reloadAfterConflict: reloadSheetAfterConflict,
  retryCatalog: retrySheetCatalog,
} = useCharacterCardDraft(detail, rules, canEdit, signal);

const notesOpen = ref(false);

// Контекст правил персонажа для чипов и вставки в заметках — тот же срез ревизии, что у обсуждения.
const notesContext = computed<ChatRulesContext>(() =>
  characterChatRulesContextService.build(
    rules.value,
    detail.value?.character.spaceId,
    detail.value?.version.rulesRevision,
  ),
);
const notesInline = computed<Record<string, unknown>>(() => ({
  tokenLabels: notesContext.value.tokenLabels,
  spaceId: notesContext.value.spaceId,
  rulesRevision: notesContext.value.rulesRevision,
}));
const notesSources = computed(() => notesContext.value.tokenSources);

// Секции листа, видимые текущему зрителю (standalone-контекст): владелец/ведущие — всё,
// иначе — по зонам. Полный доступ → обычные вкладки; частичный → ограниченный вид.
const visibleSections = computed(() => {
  const current = detail.value;
  if (!current) return [];
  if (current.visibleSections) return current.visibleSections;

  const user = currentUser.value;
  if (!user) return [];

  const ctx: SheetAccessContext = {
    user,
    ownerId: current.character.ownerId,
    characterId: current.character.id,
    gameId: null,
  };

  return sheetAccessService.visibleSheetSections(user, current.character.visibility, ctx);
});

const hasFullView = computed(() => visibleSections.value.length === SHEET_VISIBLE_SECTIONS.length);

const cardExtensions = computed(() => getCharacterCardExtensions());

const statusLabel = computed(() => {
  const current = detail.value?.character;
  if (!current) return '';

  return CHARACTER_STATUS_OPTIONS.find((option) => option.value === current.status)?.label ?? current.status;
});

const ownerName = computed(() => {
  const current = detail.value?.character;
  if (!current) return '';
  if (current.ownerName) return current.ownerName;

  const owner = users.value.find((user) => user.id === current.ownerId);

  return owner ? displayName(owner.name, owner.surname, owner.login) || owner.login : '';
});

const raceLabel = computed(() => {
  const current = detail.value;
  if (!current) return null;
  if (current.character.raceLabel) return current.character.raceLabel;

  const code = current.version.raceRuleCode;
  if (!code) return null;

  return rules.value.find((rule) => rule.code === code)?.name ?? null;
});

const revisionHref = computed(() => {
  const current = detail.value;
  if (!current) return '';

  return `/space/${current.character.spaceCode}/${current.version.rulesRevision}`;
});

const revisionLabel = computed(() => {
  const current = detail.value;
  if (!current) return '';

  return `Правила: ${spaceName.value ?? current.character.spaceCode} v${current.version.rulesRevision}`;
});

function statusColor(status: CharacterStatus): string {
  return CHARACTER_STATUS_COLOR[status];
}

async function load(): Promise<void> {
  const id = characterId.value;
  if (!Number.isFinite(id) || id <= 0) {
    router.replace({ name: 'NotFound' });

    return;
  }
  externalChangePending.value = false;
  store.clearCurrent();
  const loaded = await store.fetchCharacter(id, signal.value);
  if (loaded) {
    void ensureUsers([loaded.character.ownerId]);
    if (!canViewDetail(loaded)) router.replace({ name: 'NotFound' });
  }
}

// Правила ревизии грузим через fetchRevision (кэш), без syncFromContext: она мутирует
// глобальный activeContext пространств и ломает навигацию раздела /space.
async function loadRules(spaceId: number, revision: number, abortSignal: AbortSignal): Promise<void> {
  rulesLoading.value = true;
  rulesError.value = null;
  try {
    const revisionResult = await spaceRevision.fetchRevision(spaceId, revision, abortSignal);
    rules.value = revisionResult.rules;
    spaceName.value = revisionResult.spaceName;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    rules.value = [];
    rulesError.value = 'Не удалось загрузить правила ревизии';
  } finally {
    rulesLoading.value = false;
  }
}

function retry(): void {
  void load();
}

let stopCharacterChangeSubscription: (() => void) | null = null;

onMounted(() => {
  stopCharacterChangeSubscription = characterChangePort.subscribe((change) => {
    if (change.characterId !== characterId.value) return;
    const current = detail.value;
    if (sheetSaving.value && current && change.actualVersion <= current.actualVersion + 1) return;
    if (sheetDraft.value?.dirty) {
      externalChangePending.value = true;

      return;
    }
    draftStore.discard(sheetDraftKey.value);
    void store.fetchCharacter(change.characterId, signal.value);
  });
});

onUnmounted(() => {
  stopCharacterChangeSubscription?.();
  stopCharacterChangeSubscription = null;
});

watch(() => route.params.id, load, { immediate: true });

async function reloadAfterExternalChange(): Promise<void> {
  await reloadSheetAfterConflict();
  if (!sheetSaveError.value) externalChangePending.value = false;
}

function keepLocalDraft(): void {
  externalChangePending.value = false;
}

watch(detail, (value) => {
  if (!value) {
    rules.value = [];
    spaceName.value = null;
    rulesError.value = null;
    rulesLoading.value = false;

    return;
  }
  if (!value.character || !value.version) return;
  void loadRules(value.character.spaceId, value.version.rulesRevision, signal.value);
});
</script>

<template>
  <v-container>
    <div v-if="detailError" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ detailError }}</p>
      <v-btn color="primary" @click="retry">Попробовать снова</v-btn>
    </div>

    <div v-else-if="detailLoading || !detail" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>

    <template v-else-if="canView && detail">
      <Teleport to="#editor-actions" defer>
        <v-btn
          v-if="canView"
          variant="tonal"
          size="small"
          prepend-icon="mdi-note-text-outline"
          @click="notesOpen = true"
        >
          Заметки
        </v-btn>
        <v-btn
          v-if="canEdit && sheetDraft?.dirty"
          color="primary"
          size="small"
          prepend-icon="mdi-check"
          :loading="sheetSaving"
          :disabled="sheetSaving || Boolean(sheetCatalogError)"
          @click="saveSheet()"
        >
          Сохранить
        </v-btn>
        <v-btn
          v-if="canEdit"
          variant="tonal"
          color="primary"
          size="small"
          prepend-icon="mdi-pencil"
          :to="`/characters/${detail.character.id}/edit`"
        >
          Редактировать
        </v-btn>
        <v-btn
          v-if="canEdit"
          variant="tonal"
          size="small"
          prepend-icon="mdi-transfer"
          :to="`/characters/${detail.character.id}/migrate`"
        >
          Перевести
        </v-btn>
      </Teleport>

      <div class="d-flex align-center mb-4">
        <div class="d-flex flex-column ga-1">
          <div class="d-flex align-center ga-2">
            <v-chip v-if="raceLabel" variant="tonal" size="small">{{ raceLabel }}</v-chip>
            <h1 class="text-h5">{{ detail.character.name }}</h1>
          </div>
          <div class="d-flex align-center ga-2 flex-wrap">
            <v-chip :color="statusColor(detail.character.status)" variant="tonal" size="small">{{
              statusLabel
            }}</v-chip>
            <v-btn
              variant="text"
              color="primary"
              size="small"
              class="pa-0"
              prepend-icon="mdi-book-open-variant"
              :to="revisionHref"
            >
              {{ revisionLabel }}
            </v-btn>
            <span class="text-caption text-medium-emphasis">владелец: {{ ownerName || '—' }}</span>
            <v-chip v-if="detail.character.gameName" variant="tonal" size="small" prepend-icon="mdi-dice-multiple">
              {{ detail.character.gameName }}
            </v-chip>
          </div>
        </div>
      </div>

      <v-alert v-if="sheetCatalogError" type="error" variant="tonal" density="compact" class="mb-4">
        {{ sheetCatalogError }}
        <template #append>
          <v-btn size="small" variant="tonal" @click="retrySheetCatalog">Попробовать снова</v-btn>
        </template>
      </v-alert>
      <v-alert v-if="sheetSaveError" type="error" variant="tonal" density="compact" class="mb-4">
        {{ sheetSaveError }}
        <template #append>
          <v-btn
            size="small"
            variant="tonal"
            :loading="sheetSaving"
            @click="sheetSaveConflict ? reloadSheetAfterConflict() : retrySheetSave()"
          >
            {{ sheetSaveConflict ? 'Загрузить актуальный лист' : 'Повторить' }}
          </v-btn>
        </template>
      </v-alert>
      <v-alert v-if="externalChangePending" type="warning" variant="tonal" density="compact" class="mb-4">
        Актуальный лист изменился в другой вкладке или во время игры. Локальный черновик сохранён.
        <template #append>
          <div class="d-flex ga-2">
            <v-btn size="small" variant="tonal" @click="reloadAfterExternalChange">Загрузить актуальный лист</v-btn>
            <v-btn size="small" variant="text" @click="keepLocalDraft">Оставить черновик</v-btn>
          </div>
        </template>
      </v-alert>
      <v-alert
        v-if="sheetDraft?.dirty && validationIssues.length > 0 && !sheetSaveError"
        type="warning"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <div class="font-weight-medium">Нельзя сохранить, пока не устранены проблемы:</div>
        <ul class="mb-0 mt-1 ps-4">
          <li v-for="issue in validationIssues" :key="issue">{{ issue }}</li>
        </ul>
      </v-alert>

      <!-- Расширения карточки (например «Видимость листа» от модуля Game) -->
      <div v-for="extension in cardExtensions" :key="extension.id" class="mb-4">
        <component :is="extension.component" :character-id="detail.character.id" />
      </div>

      <CharacterSheetBody
        :name="detail.character.name"
        :version="displayVersion ?? detail.version"
        :space-id="detail.character.spaceId"
        :visible-sections="visibleSections"
        :short-description="detail.character.shortDescription"
        :supplied-rules="rules"
        :supplied-rules-loading="rulesLoading"
        :supplied-rules-error="rulesError"
        :supplied-build="sheetBuild"
        :supplied-model="sheetModel"
        :supplied-keywords="sheetKeywords"
        :can-edit="canEdit"
        :draft-key="sheetDraftKey"
        :ensure-draft="ensureDraft"
        :character-id="detail.character.id"
        :show-favorites="true"
        :discussion-chat-id="detail.discussionChatId"
        :show-discussion="hasFullView"
        @updated="load()"
      />
    </template>
  </v-container>

  <NotesSlider
    v-if="detail"
    v-model:open="notesOpen"
    host-type="character"
    :host-id="detail.character.id"
    :title="detail.character.name"
    :inline-context="notesInline"
    :token-sources="notesSources"
  />
</template>
