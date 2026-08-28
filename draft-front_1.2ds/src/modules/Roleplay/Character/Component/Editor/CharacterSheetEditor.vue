<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { characterSheetValidationService } from '@/modules/Roleplay/Character/Service/Instance/characterSheetValidationService';
import { clampAgeYears } from '@/modules/Roleplay/Character/Utils/clampAgeYears';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import EditorStageNav from '@/modules/Roleplay/Character/Component/Editor/EditorStageNav.vue';
import RaceTab from '@/modules/Roleplay/Character/Component/Editor/RaceTab.vue';
import CharacteristicsTab from '@/modules/Roleplay/Character/Component/Editor/CharacteristicsTab.vue';
import BaseTab from '@/modules/Roleplay/Character/Component/Editor/BaseTab.vue';
import PersonalityTab from '@/modules/Roleplay/Character/Component/Editor/PersonalityTab.vue';
import DevelopmentTab from '@/modules/Roleplay/Character/Component/Editor/DevelopmentTab.vue';
import EditorDescriptionTab from '@/modules/Roleplay/Character/Component/Editor/EditorDescriptionTab.vue';
import InventoryTab from '@/modules/Roleplay/Character/Component/Editor/InventoryTab.vue';
import RuleSlider from '@/modules/Roleplay/Rule/Component/RuleSlider.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import { characterVersionIntegrityService } from '@/modules/Roleplay/Character/init';

/**
 * Редактор листа персонажа/НПС (переиспользуемый, ТР §7): владеет черновиком (по `draftKey`
 * в сторе characterDraft), загрузкой правил/механик, моделью (build), валидацией «Готов»
 * и рендером табов. «Сохранить» — после валидации собирает версию и эмитит `save(version)`
 * (awaitable): сохранение/навигацию делает хост (CharacterEditPage / NpcEditPage).
 */
const props = withDefaults(
  defineProps<{
    draftKey: string | null;
    /** Обязательность расы для «Готов». Для НПС (свободный лист) — false. */
    requireRace?: boolean;
    /** Цель телепорта кнопок «Черновик»/«Сохранить». Дефолт — топбар; внутри слайдера — локальный контейнер. */
    actionsTarget?: string;
  }>(),
  { requireRace: true, actionsTarget: '#editor-actions' },
);

const emit = defineEmits<{
  save: [version: CharacterVersion];
  cancel: [];
}>();

const draftStore = useCharacterDraftStore();
const keywordStore = useKeywordStore();
const spaceRevisionStore = useSpaceRevisionStore();
const { signal } = useAbortable();

const activeTab = ref('race');
const rules = ref<Rule[]>([]);
const mechanics = ref<Mechanic[]>([]);
const rulesLoading = ref(false);
const rulesError = ref<string | null>(null);
const saveError = ref<string | null>(null);
const saveMessage = ref<string | null>(null);
const saving = ref(false);
const storageToast = ref(false);
const integrityPromptOpen = ref(false);
const integrityDismissed = ref(false);

const draft = computed(() => draftStore.draftOf(props.draftKey));
const unsupportedRuleIds = computed(() => {
  if (!draft.value || rules.value.length === 0) return [];

  return characterVersionIntegrityService.invalidBuildRuleIds(draft.value.build, rules.value);
});
const ruleSlider = useRuleDetailSlider();

const config = computed(() => draft.value?.config ?? { osTotal: null, orTotal: null, moneyBudget: null });

const model = computed(() => {
  if (!draft.value || rules.value.length === 0) return null;

  return characterEditorService.build(
    draft.value.build,
    rules.value,
    config.value,
    keywordStore.keywords,
    mechanics.value,
  );
});

const saveReady = computed(() => validationIssues.value.length === 0);

/** Проблемы, блокирующие «Сохранить» (ТР §7.6 «Готов»). */
const validationIssues = computed(() =>
  characterSheetValidationService.characterSheetValidationIssues(draft.value?.build, model.value, props.requireRace),
);

// Возраст авто-меняется только если не подходит: если ageYears не попадает ни в одну ступень
// шкалы (например, сменилась раса/правила), он зажимается к минимальной границе первой ступени.
// Без возраста (null) ничего не ставим; за верхней границей — «Старый» (без ограничений), не трогаем.
watch(
  () => draft.value?.build.ageYears,
  (ageYears) => {
    const build = draft.value?.build;
    const scale = model.value?.personality.ageScale;
    if (!build || scale === undefined) return;
    const clamped = clampAgeYears(ageYears, scale);
    if (clamped !== null) draftStore.patchBuild(props.draftKey, { ageYears: clamped });
  },
  { immediate: true },
);

watch(
  unsupportedRuleIds,
  (ruleIds) => {
    if (ruleIds.length > 0 && !integrityDismissed.value) integrityPromptOpen.value = true;
  },
  { immediate: true },
);

function removeUnsupportedRules(): void {
  const current = draft.value;
  if (!current) return;
  draftStore.patchBuild(
    props.draftKey,
    characterVersionIntegrityService.removeUnsupportedFromBuild(current.build, unsupportedRuleIds.value),
  );
  integrityDismissed.value = true;
  integrityPromptOpen.value = false;
}

function exitWithoutSaving(): void {
  draftStore.discard(props.draftKey);
  integrityPromptOpen.value = false;
  emit('cancel');
}

async function loadRules(spaceId: number, revision: number, abortSignal: AbortSignal): Promise<void> {
  rulesLoading.value = true;
  rulesError.value = null;
  try {
    const revisionResult = await spaceRevisionStore.fetchRevision(spaceId, revision, abortSignal);
    rules.value = revisionResult.rules;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    rules.value = [];
    rulesError.value = 'Не удалось загрузить правила ревизии';
  } finally {
    rulesLoading.value = false;
  }
}

watch(
  () => {
    const build = draft.value?.build;
    if (!build) return null;

    return `${build.spaceId}:${build.rulesRevision}`;
  },
  (key) => {
    if (!key || !draft.value) {
      rules.value = [];
      rulesError.value = null;

      return;
    }
    const build = draft.value.build;
    void loadRules(build.spaceId, build.rulesRevision, signal.value);
  },
  { immediate: true },
);

// Первый вход на шаг «Инвентарь»: фиксируем базовую линию отмены (R2). Для нового листа
// деньги донормируются до effectiveMoney (учитывает особенности богатства «Личности»).
watch(
  () => activeTab.value,
  (tab) => {
    if (tab !== 'inventory') return;
    const current = draft.value;
    const currentModel = model.value;
    if (!current || !currentModel) return;
    draftStore.ensureInventoryBaseline(props.draftKey, currentModel.budgets.money.total ?? current.build.money);
  },
);

async function finish(): Promise<void> {
  if (!draft.value || !model.value || saving.value) return;
  saveError.value = null;
  saveMessage.value = null;
  if (validationIssues.value.length > 0) {
    saveError.value = `Нельзя сохранить: ${validationIssues.value.join('; ')}`;

    return;
  }

  saving.value = true;
  try {
    const version = characterEditorService.toVersion(
      draft.value.build,
      rules.value,
      config.value,
      keywordStore.keywords,
      mechanics.value,
    );
    await emit('save', version);
  } catch (e) {
    saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить';
  } finally {
    saving.value = false;
  }
}

function saveDraft(): void {
  saveMessage.value = 'Черновик сохранён (в браузере)';
}

function clearMessage(): void {
  saveMessage.value = null;
}

const actionsReady = ref(false);

onMounted(() => {
  actionsReady.value = true;
  if (draftStore.storageDiscarded) {
    storageToast.value = true;
    draftStore.acknowledgeStorageDiscarded();
  }
  if (keywordStore.keywords.length === 0) void keywordStore.fetchTags();
  if (mechanics.value.length === 0) {
    void getRuleApi()
      .getMechanics(signal.value)
      .then((list) => {
        mechanics.value = list;
      })
      .catch(() => {
        mechanics.value = [];
      });
  }
});
</script>

<template>
  <template v-if="draft">
    <v-dialog v-model="integrityPromptOpen" persistent max-width="520">
      <v-card>
        <v-card-title>Правила больше не поддерживаются</v-card-title>
        <v-card-text>
          <p class="mb-3">
            В черновике есть ссылки на правила, которых нет в выбранной ревизии. Их нельзя сохранить в текущем виде:
          </p>
          <div class="d-flex flex-wrap ga-2">
            <v-chip v-for="ruleId in unsupportedRuleIds" :key="ruleId" size="small" variant="tonal">
              {{ ruleId }}
            </v-chip>
          </div>
          <p class="text-caption text-medium-emphasis mt-3">
            Удаление уберёт способности, ресурсы, предметы и другие ссылки на эти правила из черновика.
          </p>
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="exitWithoutSaving">Выйти без сохранения</v-btn>
          <v-spacer />
          <v-btn color="warning" @click="removeUnsupportedRules">Удалить неподдерживаемые</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <EditorStageNav
      class="editor-nav-bleed"
      :model="model"
      :build="draft.build"
      :rules="rules"
      :active-tab="activeTab"
      @update:active-tab="(tab) => (activeTab = tab)"
    />

    <div class="editor-content">
      <Teleport v-if="actionsReady" :to="props.actionsTarget">
        <div class="d-flex align-center ga-2">
          <v-btn variant="outlined" prepend-icon="mdi-content-save" :disabled="!draft.dirty" @click="saveDraft">
            Черновик
          </v-btn>
          <v-btn color="primary" prepend-icon="mdi-check" :disabled="!saveReady || saving" @click="finish">
            {{ saving ? 'Сохранение…' : 'Сохранить' }}
          </v-btn>
        </div>
      </Teleport>

      <v-alert v-if="saveError" type="error" variant="tonal" density="compact" class="mb-4">{{ saveError }}</v-alert>
      <v-alert v-if="saveMessage" type="success" variant="tonal" density="compact" class="mb-4" @click="clearMessage">
        {{ saveMessage }}
      </v-alert>

      <v-alert
        v-if="model && !saveError && validationIssues.length > 0"
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

      <div v-if="rulesLoading && rules.length === 0" class="d-flex justify-center pa-8">
        <v-progress-circular indeterminate width="2" size="28" color="primary" />
      </div>
      <div v-else-if="rulesError" class="muted-text pa-4">{{ rulesError }}</div>

      <template v-else-if="model">
        <RaceTab
          v-if="activeTab === 'race'"
          :build="draft.build"
          :rules="rules"
          :model="model"
          :draft-key="draftKey"
          :keywords="keywordStore.keywords"
          :config="config"
        />
        <CharacteristicsTab
          v-else-if="activeTab === 'characteristics'"
          :build="draft.build"
          :rules="rules"
          :model="model"
          :keywords="keywordStore.keywords"
          :draft-key="draftKey"
        />
        <BaseTab
          v-else-if="activeTab === 'base'"
          :build="draft.build"
          :model="model"
          :draft-key="draftKey"
          :keywords="keywordStore.keywords"
          :rules="rules"
        />
        <PersonalityTab
          v-else-if="activeTab === 'personality'"
          :build="draft.build"
          :model="model"
          :draft-key="draftKey"
          :keywords="keywordStore.keywords"
          :rules="rules"
        />
        <DevelopmentTab
          v-else-if="activeTab === 'development'"
          :build="draft.build"
          :model="model"
          :draft-key="draftKey"
          :keywords="keywordStore.keywords"
          :rules="rules"
        />
        <InventoryTab
          v-else-if="activeTab === 'inventory'"
          :build="draft.build"
          :model="model"
          :draft-key="draftKey"
          :keywords="keywordStore.keywords"
          :rules="rules"
        />
        <EditorDescriptionTab
          v-else-if="activeTab === 'description'"
          :build="draft.build"
          :draft-key="draftKey"
          :model="model"
        />
      </template>

      <RuleSlider
        v-model:open="ruleSlider.state.open"
        :rule-id="ruleSlider.state.ruleId"
        :space-id="draft.build.spaceId"
        :rules-revision="draft.build.rulesRevision"
        :rules="rules"
        :keywords="keywordStore.keywords"
      />
    </div>
  </template>
  <v-snackbar v-model="storageToast" color="error" timeout="4000">
    Черновик листа в браузере повреждён и сброшен
  </v-snackbar>
</template>

<style scoped>
.editor-nav-bleed {
  margin: 0;
}

.editor-content {
  padding: 10px;
}

.muted-text {
  color: rgba(var(--v-theme-on-surface), 0.72);
}
</style>
