<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { characterSheetValidationIssues } from '@/modules/Roleplay/Character/Utils/characterSheetValidation';
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
import EditorAbilitySlider from '@/modules/Roleplay/Character/Component/Editor/EditorAbilitySlider.vue';

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

const draft = computed(() => draftStore.draftOf(props.draftKey));

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
  characterSheetValidationIssues(draft.value?.build, model.value, props.requireRace),
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

      <EditorAbilitySlider :rules="rules" :keywords="keywordStore.keywords" />
    </div>
  </template>
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
