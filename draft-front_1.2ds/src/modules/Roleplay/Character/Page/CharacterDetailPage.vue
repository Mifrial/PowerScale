<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { canViewCharacter, canEditCharacter } from '@/modules/Roleplay/Character/Utils/access';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_COLOR';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import OverviewTab from '@/modules/Roleplay/Character/Component/Detail/OverviewTab.vue';
import DescriptionTab from '@/modules/Roleplay/Character/Component/Detail/DescriptionTab.vue';
import AbilityTab from '@/modules/Roleplay/Character/Component/Detail/AbilityTab.vue';
import InventoryTab from '@/modules/Roleplay/Character/Component/Detail/InventoryTab.vue';
import DiscussionTab from '@/modules/Roleplay/Character/Component/Detail/DiscussionTab.vue';
import RuleSlider from '@/modules/Roleplay/Rule/Component/RuleSlider.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { getCharacterCardExtensions } from '@/modules/Roleplay/Character/init';
import { visibleSheetSections } from '@/modules/Roleplay/Character/Utils/sheetAccess';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import SheetCard from '@/modules/Roleplay/Character/Component/SheetCard.vue';
import UniqueRulesTab from '@/modules/Roleplay/Character/Component/Detail/UniqueRulesTab.vue';

const route = useRoute();
const router = useRouter();
const store = useCharacterStore();
const userStore = useUserStore();
const spaceRevisionStore = useSpaceRevisionStore();
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

  return canViewCharacter(userStore.currentUser, current.character);
});

const canEdit = computed(() => {
  const current = detail.value;
  if (!current) return false;

  return canEditCharacter(userStore.currentUser, current.character);
});

// Секции листа, видимые текущему зрителю (standalone-контекст): владелец/ведущие — всё,
// иначе — по зонам. Полный доступ → обычные вкладки; частичный → ограниченный вид.
const visibleSections = computed(() => {
  const current = detail.value;
  const user = userStore.currentUser;
  if (!current || !user) return [];

  const ctx: SheetAccessContext = {
    user,
    ownerId: current.character.ownerId,
    characterId: current.character.id,
    gameId: null,
  };

  return visibleSheetSections(user, current.character.visibility, ctx);
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
  if (loaded && !canViewCharacter(userStore.currentUser, loaded.character)) {
    router.replace({ name: 'NotFound' });
  }
}

// Правила ревизии грузим через fetchRevision (кэш), без syncFromContext: она мутирует
// глобальный activeContext пространств и ломает навигацию раздела /space.
async function loadRules(spaceId: number, revision: number, abortSignal: AbortSignal): Promise<void> {
  rulesLoading.value = true;
  rulesError.value = null;
  try {
    const revisionResult = await spaceRevisionStore.fetchRevision(spaceId, revision, abortSignal);
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
        <v-spacer />
        <v-btn
          v-if="canEdit"
          variant="tonal"
          color="primary"
          prepend-icon="mdi-pencil"
          class="mr-2"
          :to="`/characters/${detail.character.id}/edit`"
        >
          Редактировать
        </v-btn>
        <v-btn
          v-if="canEdit"
          variant="tonal"
          prepend-icon="mdi-transfer"
          class="mr-2"
          :to="`/characters/${detail.character.id}/migrate`"
        >
          Перевести
        </v-btn>
        <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="router.push('/characters')"> К списку </v-btn>
      </div>

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
              :version="detail.version"
              :rules="rules"
              :rules-loading="rulesLoading"
              :rules-error="rulesError"
            />
          </v-window-item>
          <v-window-item value="description">
            <DescriptionTab :version="detail.version" />
          </v-window-item>
          <v-window-item value="abilities">
            <AbilityTab
              :version="detail.version"
              :rules="rules"
              :rules-loading="rulesLoading"
              :character-id="detail.character.id"
            />
          </v-window-item>
          <v-window-item value="inventory">
            <InventoryTab :version="detail.version" :rules="rules" :rules-loading="rulesLoading" />
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

  <RuleSlider
    v-model:open="ruleSlider.state.open"
    :rule-id="ruleSlider.state.ruleId"
    :space-id="detail?.character.spaceId ?? null"
    :rules-revision="detail?.version.rulesRevision ?? null"
  />
</template>
