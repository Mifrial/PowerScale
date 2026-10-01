<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/RuleSpace/init';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import { getMechanicApi } from '@/modules/Roleplay/Mechanic/init';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import OverviewTab from '@/modules/Roleplay/Character/Component/Detail/OverviewTab.vue';
import DescriptionTab from '@/modules/Roleplay/Character/Component/Detail/DescriptionTab.vue';
import AbilityTab from '@/modules/Roleplay/Character/Component/Detail/AbilityTab.vue';
import InventoryTab from '@/modules/Roleplay/Character/Component/Editor/InventoryTab.vue';
import UniqueRulesTab from '@/modules/Roleplay/Character/Component/Detail/UniqueRulesTab.vue';
import DiscussionTab from '@/modules/Roleplay/Character/Component/Detail/DiscussionTab.vue';
import SheetCard from '@/modules/Roleplay/Character/Component/SheetCard.vue';

const RuleSlider = defineAsyncComponent(() => import('@/modules/Roleplay/Rule/Component/RuleSlider.vue'));

const props = withDefaults(
  defineProps<{
    name: string;
    version: CharacterVersion | null;
    spaceId: number | null;
    visibleSections: SheetSection[];
    shortDescription?: string | null;
    fullDescription?: string | null;
    /** Карточка персонажа передаёт уже загруженные правила и черновик. NPC оставляет пустым: лист грузит сам. */
    suppliedRules?: Rule[] | null;
    suppliedRulesLoading?: boolean;
    suppliedRulesError?: string | null;
    suppliedBuild?: CharacterBuild | null;
    suppliedModel?: CharacterEditorModel | null;
    suppliedKeywords?: Keyword[] | null;
    canEdit?: boolean;
    draftKey?: string | null;
    ensureDraft?: () => void;
    /** Избранное и управление уникальными правилами. Для NPC не передаётся. */
    characterId?: number | null;
    showFavorites?: boolean;
    canManageUniqueRules?: boolean;
    discussionChatId?: number | null;
    showDiscussion?: boolean;
  }>(),
  {
    shortDescription: null,
    fullDescription: null,
    suppliedRules: null,
    suppliedRulesLoading: false,
    suppliedRulesError: null,
    suppliedBuild: null,
    suppliedModel: null,
    suppliedKeywords: null,
    canEdit: false,
    draftKey: null,
    ensureDraft: undefined,
    characterId: null,
    showFavorites: false,
    canManageUniqueRules: false,
    discussionChatId: null,
    showDiscussion: false,
  },
);

const emit = defineEmits<{ updated: [] }>();

const spaceRevision = useSpaceRevision();
const { signal } = useAbortable();
const ruleSlider = useRuleDetailSlider();
const { keywords: loadedKeywords, error: keywordsError, fetchTags } = useKeywords();

const activeTab = ref('overview');
const loadedRules = ref<Rule[]>([]);
const loadedRulesLoading = ref(false);
const loadedRulesError = ref<string | null>(null);
const mechanics = ref<Mechanic[]>([]);
const catalogError = ref<string | null>(null);

const usesSuppliedRules = computed(() => props.suppliedRules !== null);
const rules = computed(() => (usesSuppliedRules.value ? (props.suppliedRules ?? []) : loadedRules.value));
const rulesLoading = computed(() => (usesSuppliedRules.value ? props.suppliedRulesLoading : loadedRulesLoading.value));
const rulesError = computed(() => (usesSuppliedRules.value ? props.suppliedRulesError : loadedRulesError.value));
const keywords = computed(() => props.suppliedKeywords ?? loadedKeywords.value);

const hasFullView = computed(
  () => props.version !== null && props.visibleSections.length === SHEET_VISIBLE_SECTIONS.length,
);

const shownVersion = computed(() => props.version);

const creationConfig = computed<CharacterCreationConfig>(() => {
  const version = props.version;
  if (!version) return { osTotal: null, orTotal: null, moneyBudget: null };

  return {
    osTotal: version.budgets?.osTotal ?? null,
    orTotal: version.points.orTotal ?? null,
    moneyBudget: version.budgets?.moneyBudget ?? null,
  };
});

const ownedBuild = computed(() => {
  const version = props.version;
  if (props.suppliedBuild !== null || !version || props.spaceId === null || rules.value.length === 0) return null;

  return characterBuildService.fromVersion(version, props.spaceId, rules.value);
});

const ownedModel = computed(() => {
  const build = ownedBuild.value;
  if (props.suppliedModel !== null || !build || rules.value.length === 0) return null;

  return characterEditorService.build(build, rules.value, creationConfig.value, keywords.value, mechanics.value);
});

const sheetBuild = computed(() => props.suppliedBuild ?? ownedBuild.value);
const sheetModel = computed(() => props.suppliedModel ?? ownedModel.value);
const inventoryDraftKey = computed(() => (props.suppliedBuild !== null ? props.draftKey : null));

async function loadRules(spaceId: number, revision: number, abortSignal: AbortSignal): Promise<void> {
  loadedRulesLoading.value = true;
  loadedRulesError.value = null;
  try {
    const revisionResult = await spaceRevision.fetchRevision(spaceId, revision, abortSignal);
    loadedRules.value = revisionResult.rules;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    loadedRules.value = [];
    loadedRulesError.value = 'Не удалось загрузить правила ревизии';
  } finally {
    loadedRulesLoading.value = false;
  }
}

async function loadCatalog(abortSignal: AbortSignal): Promise<void> {
  catalogError.value = null;
  try {
    const pending: Promise<void>[] = [];
    if (props.suppliedKeywords === null && (loadedKeywords.value.length === 0 || keywordsError.value)) {
      pending.push(fetchTags(abortSignal));
    }
    pending.push(
      getMechanicApi()
        .getMechanics(abortSignal)
        .then((list) => {
          mechanics.value = list;
        }),
    );
    await Promise.all(pending);
    if (keywordsError.value) catalogError.value = keywordsError.value;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    mechanics.value = [];
    catalogError.value = e instanceof Error ? e.message : 'Не удалось загрузить справочники листа';
  }
}

watch(
  () => [props.spaceId, props.version?.rulesRevision, usesSuppliedRules.value] as const,
  ([spaceId, revision, supplied]) => {
    if (supplied || spaceId === null || revision == null || !props.version) {
      if (!supplied) {
        loadedRules.value = [];
        loadedRulesError.value = null;
        loadedRulesLoading.value = false;
      }

      return;
    }
    void loadRules(spaceId, revision, signal.value);
    void loadCatalog(signal.value);
  },
  { immediate: true },
);
</script>

<template>
  <div>
    <v-alert v-if="!usesSuppliedRules && catalogError" type="error" variant="tonal" density="compact" class="mb-4">
      {{ catalogError }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="loadCatalog(signal)">Попробовать снова</v-btn>
      </template>
    </v-alert>

    <template v-if="hasFullView && shownVersion">
      <v-tabs v-model="activeTab" color="primary" class="mb-4">
        <v-tab value="overview">Обзор</v-tab>
        <v-tab value="description">Описание</v-tab>
        <v-tab value="abilities">Способности</v-tab>
        <v-tab value="inventory">Инвентарь</v-tab>
        <v-tab value="unique-rules">Уникальные правила</v-tab>
        <v-tab v-if="showDiscussion" value="discussion">Обсуждение</v-tab>
      </v-tabs>

      <v-window v-model="activeTab">
        <v-window-item value="overview">
          <OverviewTab :version="shownVersion" :rules="rules" :rules-loading="rulesLoading" :rules-error="rulesError" />
        </v-window-item>
        <v-window-item value="description">
          <DescriptionTab :version="shownVersion" />
        </v-window-item>
        <v-window-item value="abilities">
          <AbilityTab
            :version="shownVersion"
            :rules="rules"
            :rules-loading="rulesLoading"
            :character-id="characterId ?? 0"
            :show-favorites="showFavorites"
          />
        </v-window-item>
        <v-window-item value="inventory">
          <InventoryTab
            v-if="sheetBuild && sheetModel"
            variant="sheet"
            :build="sheetBuild"
            :model="sheetModel"
            :draft-key="inventoryDraftKey"
            :rules="rules"
            :keywords="keywords"
            :can-edit="canEdit"
            :ensure-draft="ensureDraft"
          />
          <div v-else-if="rulesLoading" class="d-flex justify-center pa-8">
            <v-progress-circular indeterminate width="2" size="28" color="primary" />
          </div>
          <div v-else class="text-medium-emphasis pa-4">{{ rulesError || catalogError || 'Инвентарь недоступен' }}</div>
        </v-window-item>
        <v-window-item value="unique-rules">
          <UniqueRulesTab
            :version="shownVersion"
            :character-id="characterId ?? 0"
            :can-manage="canManageUniqueRules"
            :space-id="spaceId ?? undefined"
            :rules-revision="shownVersion.rulesRevision"
            @updated="emit('updated')"
          />
        </v-window-item>
        <v-window-item v-if="showDiscussion" value="discussion">
          <DiscussionTab
            v-if="activeTab === 'discussion'"
            :discussion-chat-id="discussionChatId"
            :space-id="spaceId ?? 0"
            :rules-revision="shownVersion.rulesRevision"
          />
        </v-window-item>
      </v-window>
    </template>

    <v-card v-else>
      <v-card-text>
        <div
          v-if="version === null && visibleSections.length === SHEET_VISIBLE_SECTIONS.length"
          class="text-medium-emphasis text-body-2 mb-2"
        >
          Лист ещё не заполнен.
        </div>
        <SheetCard
          :name="name"
          :version="version"
          :visible-sections="visibleSections"
          :space-id="spaceId"
          :rules-revision="version?.rulesRevision ?? null"
          :short-description="shortDescription"
          :full-description="fullDescription"
        />
      </v-card-text>
    </v-card>

    <RuleSlider
      v-model:open="ruleSlider.state.open"
      :rule-code="ruleSlider.state.ruleCode"
      :space-id="spaceId"
      :rules-revision="version?.rulesRevision ?? null"
      :rules="rules"
      :keywords="keywords"
    />
  </div>
</template>
