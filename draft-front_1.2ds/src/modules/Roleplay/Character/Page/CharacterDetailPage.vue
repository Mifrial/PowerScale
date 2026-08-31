<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/Space/init';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_COLOR';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import OverviewTab from '@/modules/Roleplay/Character/Component/Detail/OverviewTab.vue';
import DescriptionTab from '@/modules/Roleplay/Character/Component/Detail/DescriptionTab.vue';
import AbilityTab from '@/modules/Roleplay/Character/Component/Detail/AbilityTab.vue';
import InventoryTab from '@/modules/Roleplay/Character/Component/Editor/InventoryTab.vue';
import DiscussionTab from '@/modules/Roleplay/Character/Component/Detail/DiscussionTab.vue';
import { RuleSlider } from '@/modules/Roleplay/Rule/init';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { useCharacterCardDraft } from '@/modules/Roleplay/Character/Composables/useCharacterCardDraft';
import { getCharacterApi, getCharacterCardExtensions } from '@/modules/Roleplay/Character/init';
import { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import SheetCard from '@/modules/Roleplay/Character/Component/SheetCard.vue';
import UniqueRulesTab from '@/modules/Roleplay/Character/Component/Detail/UniqueRulesTab.vue';
import OwnerNotesDialog from '@/modules/Roleplay/Character/Component/OwnerNotesDialog.vue';

const route = useRoute();
const router = useRouter();
const store = useCharacterStore();
const { currentUser } = useCurrentUser();
const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();
const ruleSlider = useRuleDetailSlider();

const activeTab = ref('overview');

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

const canView = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return characterAccessService.canViewCharacter(currentUser.value, current.character);
});

const canEdit = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return characterAccessService.canEditCharacter(currentUser.value, current.character);
});

const {
  draftKey: sheetDraftKey,
  draft: sheetDraft,
  build: sheetBuild,
  model: sheetModel,
  displayVersion,
  validationIssues,
  saving: sheetSaving,
  saveError: sheetSaveError,
  catalogError: sheetCatalogError,
  keywords: sheetKeywords,
  ensureDraft,
  save: saveSheet,
  retryCatalog: retrySheetCatalog,
} = useCharacterCardDraft(detail, rules, canEdit, signal);

const notesOpen = ref(false);
const notesSaving = ref(false);
const notesError = ref<string | null>(null);

async function saveOwnerNotes(text: string): Promise<void> {
  const current = detail.value;
  if (!current) return;
  notesSaving.value = true;
  notesError.value = null;
  try {
    const updated = await getCharacterApi().updateOwnerNotes(current.character.id, text, signal.value);
    store.applyDetail(updated);
    notesOpen.value = false;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    notesError.value = 'Не удалось сохранить заметки';
  } finally {
    notesSaving.value = false;
  }
}

// Секции листа, видимые текущему зрителю (standalone-контекст): владелец/ведущие — всё,
// иначе — по зонам. Полный доступ → обычные вкладки; частичный → ограниченный вид.
const visibleSections = computed(() => {
  const current = detail.value;
  const user = currentUser.value;
  if (!current || !user) return [];

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
  store.clearCurrent();
  const loaded = await store.fetchCharacter(id, signal.value);
  if (loaded && !characterAccessService.canViewCharacter(currentUser.value, loaded.character)) {
    router.replace({ name: 'NotFound' });
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

watch(() => route.params.id, load, { immediate: true });

watch(detail, (value) => {
  if (!value) {
    rules.value = [];
    spaceName.value = null;
    rulesError.value = null;
    rulesLoading.value = false;

    return;
  }
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
      <Teleport to="#editor-actions">
        <v-btn
          v-if="canEdit"
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
            <v-chip v-if="detail.character.raceLabel" variant="tonal" size="small">{{
              detail.character.raceLabel
            }}</v-chip>
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
            <span class="text-caption text-medium-emphasis">владелец: {{ detail.character.ownerName }}</span>
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
      <v-alert v-if="sheetSaveError" type="error" variant="tonal" density="compact" class="mb-4">{{
        sheetSaveError
      }}</v-alert>
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

      <template v-if="hasFullView">
        <v-tabs v-model="activeTab" color="primary" class="mb-4">
          <v-tab value="overview">Обзор</v-tab>
          <v-tab value="description">Описание</v-tab>
          <v-tab value="abilities">Способности</v-tab>
          <v-tab value="inventory">Инвентарь</v-tab>
          <v-tab value="unique-rules">Уникальные правила</v-tab>
          <v-tab value="discussion">Обсуждение</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
          <v-window-item value="overview">
            <OverviewTab
              :version="displayVersion ?? detail.version"
              :rules="rules"
              :rules-loading="rulesLoading"
              :rules-error="rulesError"
            />
          </v-window-item>
          <v-window-item value="description">
            <DescriptionTab :version="displayVersion ?? detail.version" />
          </v-window-item>
          <v-window-item value="abilities">
            <AbilityTab
              :version="displayVersion ?? detail.version"
              :rules="rules"
              :rules-loading="rulesLoading"
              :character-id="detail.character.id"
            />
          </v-window-item>
          <v-window-item value="inventory">
            <InventoryTab
              v-if="sheetBuild && sheetModel"
              variant="sheet"
              :build="sheetBuild"
              :model="sheetModel"
              :draft-key="sheetDraftKey"
              :rules="rules"
              :keywords="sheetKeywords"
              :can-edit="canEdit"
              :ensure-draft="ensureDraft"
            />
            <div v-else-if="rulesLoading" class="d-flex justify-center pa-8">
              <v-progress-circular indeterminate width="2" size="28" color="primary" />
            </div>
            <div v-else class="text-medium-emphasis pa-4">{{ rulesError || 'Инвентарь недоступен' }}</div>
          </v-window-item>
          <v-window-item value="unique-rules">
            <UniqueRulesTab
              :version="detail.version"
              :character-id="detail.character.id"
              :space-id="detail.character.spaceId"
              :rules-revision="detail.version.rulesRevision"
              @updated="load()"
            />
          </v-window-item>
          <v-window-item value="discussion">
            <!-- Чат живёт в глобальном чат-сторе; монтируем вкладку только при открытии, чтобы освобождать чат при уходе -->
            <DiscussionTab
              v-if="activeTab === 'discussion'"
              :discussion-chat-id="detail.discussionChatId"
              :space-id="detail.character.spaceId"
              :rules-revision="detail.version.rulesRevision"
            />
          </v-window-item>
        </v-window>
      </template>

      <!-- Ограниченный доступ: показываем только видимые секции листа -->
      <v-card v-else>
        <v-card-text>
          <SheetCard
            :name="detail.character.name"
            :version="detail.version"
            :visible-sections="visibleSections"
            :space-id="detail.character.spaceId"
            :rules-revision="detail.version.rulesRevision"
            :short-description="detail.character.shortDescription"
          />
        </v-card-text>
      </v-card>
    </template>
  </v-container>

  <OwnerNotesDialog
    v-model="notesOpen"
    :text="detail?.ownerNotes"
    :saving="notesSaving"
    :error="notesError"
    @save="saveOwnerNotes"
  />

  <RuleSlider
    v-model:open="ruleSlider.state.open"
    :rule-code="ruleSlider.state.ruleCode"
    :space-id="detail?.character.spaceId ?? null"
    :rules-revision="detail?.version.rulesRevision ?? null"
    :rules="rules"
    :keywords="sheetKeywords"
  />
</template>
