<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import type { LoreArticleInput } from '@/modules/Roleplay/Lore/Dto/LoreArticleInput';
import type { LoreConflict } from '@/modules/Roleplay/Lore/Dto/LoreConflict';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { TreeSelectItem } from '@/modules/Core/UI/Dto/Tree/TreeSelectItem';
import type { LoreArticleStatus } from '@/modules/Roleplay/Lore/Enum/LoreArticleStatus';
import type { LoreArticleVisibility } from '@/modules/Roleplay/Lore/Enum/LoreArticleVisibility';
import { LORE_ARTICLE_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Lore/Constant/LORE_VISIBILITY_OPTIONS';
import { LORE_STATUS_OPTIONS } from '@/modules/Roleplay/Lore/Constant/LORE_STATUS_OPTIONS';
import { useLoreDraft } from '@/modules/Roleplay/Lore/Composables/useLoreDraft';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import { isLoreCode, loreSlug } from '@/modules/Roleplay/Lore/Utils/loreSlug';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { getRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import TreeSelect from '@/modules/Core/UI/Component/Input/TreeSelect.vue';
import LoreArticleBody from '@/modules/Roleplay/Lore/Component/LoreArticleBody.vue';
import LoreArticleEditor from '@/modules/Roleplay/Lore/Component/LoreArticleEditor.vue';
import LoreConflictDialog from '@/modules/Roleplay/Lore/Component/LoreConflictDialog.vue';
import LoreNotice from '@/modules/Roleplay/Lore/Component/LoreNotice.vue';
import LoreReadersField from '@/modules/Roleplay/Lore/Component/LoreReadersField.vue';
import { useLoreCrumbStore } from '@/modules/Roleplay/Lore/Store/crumbs';

const route = useRoute();
const router = useRouter();
const crumbStore = useLoreCrumbStore();
const { keywords, fetchTags } = useKeywords();

const worldCode = computed(() => String(route.params.worldCode ?? ''));
const articleCode = computed(() => (route.params.articleCode === undefined ? null : String(route.params.articleCode)));

const world = ref<LoreWorldDetail | null>(null);
const article = ref<LoreArticleDetail | null>(null);
const rules = ref<Rule[]>([]);
const rulesHint = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);
const notice = ref<{ text: string; color: 'success' | 'error' | 'info' | 'warning' } | null>(null);
const previewOpen = ref(false);
const conflict = ref<LoreConflict | null>(null);
const editorRef = ref<InstanceType<typeof LoreArticleEditor> | null>(null);
const createOpen = ref(false);
const newTitle = ref('');
const newCode = ref('');
const newSection = ref<string | null>(null);
const createdSelection = ref('');
const leaveOpen = ref(false);

const draftStore = useLoreDraft(worldCode.value);
const draftCode = ref(articleCode.value ?? 'new');
let draftTimer: ReturnType<typeof setTimeout> | null = null;
let dirty = false;
let hydrating = true;
let pendingTarget: string | null = null;
let allowLeave = false;

const form = reactive({
  code: articleCode.value ?? '',
  title: '',
  summary: '',
  bodyHtml: '<p></p>',
  sectionCode: null as string | null,
  sortOrder: 0,
  keywordIds: [] as number[],
  status: 'draft' as LoreArticleStatus,
  visibility: 'public' as LoreArticleVisibility,
  readers: [] as number[],
  expectedVersion: undefined as number | undefined,
});

const isNew = computed(() => articleCode.value === null);
const codeLocked = computed(() => !isNew.value);
const sectionItems = computed<TreeSelectItem[]>(() =>
  (world.value?.sections ?? []).map((section) => ({
    value: section.code,
    label: section.name,
    parentValue: section.parentCode,
  })),
);
const knownCodes = computed(() => (world.value ? world.value.articles.map((item) => item.code) : []));
const canSubmit = computed(() => form.title.trim() !== '' && isLoreCode(form.code));
const resolvedCode = computed(() => form.code.trim() || loreSlug(form.title));

onMounted(async () => {
  await loadWorld();
  await loadArticle();
  await loadRules();
  await fetchTags();
  restoreDraft();
  await nextTick();
  hydrating = false;
  dirty = false;
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  if (draftTimer) clearTimeout(draftTimer);
});

onBeforeRouteLeave((to) => {
  if (!dirty || allowLeave) return true;
  pendingTarget = to.fullPath;
  leaveOpen.value = true;

  return false;
});

watch(
  form,
  () => {
    if (hydrating) return;
    dirty = true;
    scheduleDraft();
  },
  { deep: true },
);

function applyForm(patch: Partial<typeof form>): void {
  hydrating = true;
  Object.assign(form, patch);
  // Ждём флаш вотчера: иначе он увидит hydrating=false и пометит статью изменённой.
  void nextTick().then(() => {
    hydrating = false;
  });
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!dirty) return;
  event.preventDefault();
  event.returnValue = '';
}

async function loadWorld(): Promise<void> {
  if (worldCode.value === '') return;
  try {
    world.value = await getLoreApi().getWorld(worldCode.value);
    crumbStore.setWorld(worldCode.value, world.value.name);
  } catch (exception) {
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить мир';
  }
}

async function loadArticle(): Promise<void> {
  if (articleCode.value === null) {
    const queryCode = typeof route.query.code === 'string' ? route.query.code : '';
    const queryTitle = typeof route.query.title === 'string' ? route.query.title : '';
    applyForm({ code: queryCode, title: queryTitle });
    dirty = false;

    return;
  }

  loading.value = true;
  try {
    const detail = await getLoreApi().getArticle(worldCode.value, articleCode.value);
    article.value = detail;
    crumbStore.setArticle(worldCode.value, detail.code, detail.title);
    applyForm({
      code: detail.code,
      title: detail.title,
      summary: detail.summary,
      bodyHtml: detail.bodyHtml,
      sectionCode: detail.sectionCode,
      sortOrder: detail.sortOrder,
      keywordIds: [...detail.keywordIds],
      status: detail.status,
      visibility: detail.visibility,
      readers: [...detail.readerIds],
      expectedVersion: detail.version,
    });
    dirty = false;
  } catch (exception) {
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить статью';
  } finally {
    loading.value = false;
  }
}

async function loadRules(): Promise<void> {
  const spaceCode = world.value?.spaceCode ?? '';
  if (spaceCode === '') {
    rulesHint.value = 'У мира не указано пространство правил.';

    return;
  }
  try {
    const space = await getRuleSpaceApi().getSpaceByCode(spaceCode);
    rules.value = await getRuleApi().getRules(space.id);
  } catch {
    rulesHint.value = `Пространство правил «${spaceCode}» не найдено.`;
  }
}

function restoreDraft(): void {
  const draft = draftStore.load(draftCode.value);
  if (!draft) return;
  if (!isNew.value && draft.expectedVersion !== form.expectedVersion) return;
  applyForm({
    ...draft.article,
    expectedVersion: isNew.value ? undefined : form.expectedVersion,
  });
  notice.value = { text: 'Восстановлен локальный черновик статьи.', color: 'info' };
}

function scheduleDraft(): void {
  if (draftTimer) clearTimeout(draftTimer);
  draftTimer = setTimeout(() => {
    draftStore.save(draftCode.value, articleInput(), form.expectedVersion);
  }, 800);
}

function articleInput(): LoreArticleInput {
  return {
    code: resolvedCode.value,
    title: form.title,
    summary: form.summary,
    bodyHtml: form.bodyHtml,
    sectionCode: form.sectionCode,
    sortOrder: form.sortOrder,
    keywordIds: form.keywordIds,
    status: form.status,
    visibility: form.visibility,
    readers: form.readers,
  };
}

async function save(forcedVersion?: number): Promise<void> {
  if (!canSubmit.value) {
    error.value = 'Укажите заголовок и корректный код статьи.';

    return;
  }

  saving.value = true;
  error.value = null;
  try {
    const saved = await getLoreApi().saveArticle({
      worldCode: worldCode.value,
      article: articleInput(),
      expectedVersion: isNew.value ? undefined : (forcedVersion ?? form.expectedVersion),
    });
    article.value = saved;
    applyForm({ expectedVersion: saved.version, code: saved.code });
    draftStore.clear(draftCode.value);
    conflict.value = null;
    crumbStore.setArticle(worldCode.value, saved.code, saved.title);
    dirty = false;
    allowLeave = true;
    notice.value = { text: 'Статья сохранена.', color: 'success' };
    if (isNew.value) {
      draftCode.value = saved.code;
      await router.replace({
        name: 'LoreArticleEdit',
        params: { worldCode: worldCode.value, articleCode: saved.code },
      });
      allowLeave = false;
    }
  } catch (exception) {
    if (exception instanceof LoreApiError && exception.code === 'LORE_VERSION_CONFLICT') {
      conflict.value = exception.details as LoreConflict;
    } else {
      error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось сохранить статью';
    }
  } finally {
    saving.value = false;
  }
}

function overwrite(): void {
  const currentVersion = conflict.value?.currentVersion;
  if (currentVersion === undefined) return;
  void save(currentVersion);
}

function reloadFromServer(): void {
  conflict.value = null;
  draftStore.clear(draftCode.value);
  allowLeave = false;
  void loadArticle();
}

/** Уход со страницы после диалога о несохранённых правках. */
function confirmLeave(): void {
  const target = pendingTarget;
  pendingTarget = null;
  leaveOpen.value = false;
  allowLeave = true;
  dirty = false;
  if (target !== null) void router.push(target);
}

function stayOnPage(): void {
  pendingTarget = null;
  leaveOpen.value = false;
}

function openCreateFromSelection(): void {
  createdSelection.value = editorRef.value?.selectedText() ?? '';
  newTitle.value = createdSelection.value;
  newCode.value = loreSlug(createdSelection.value);
  newSection.value = form.sectionCode;
  createOpen.value = true;
}

async function createLinkedArticle(): Promise<void> {
  if (newTitle.value.trim() === '' || !isLoreCode(newCode.value.trim())) {
    notice.value = { text: 'Укажите название и корректный код новой статьи.', color: 'warning' };

    return;
  }
  try {
    const created = await getLoreApi().saveArticle({
      worldCode: worldCode.value,
      article: {
        code: newCode.value.trim(),
        title: newTitle.value.trim(),
        summary: '',
        bodyHtml: '<p></p>',
        sectionCode: newSection.value,
        sortOrder: 0,
        keywordIds: [],
        status: 'draft',
        visibility: 'public',
        readers: [],
      },
    });
    editorRef.value?.insertLoreLink(created.code, created.title);
    createOpen.value = false;
    notice.value = { text: 'Статья создана и вставлена ссылка.', color: 'success' };
  } catch (exception) {
    notice.value = {
      text: exception instanceof LoreApiError ? exception.message : 'Не удалось создать статью',
      color: 'error',
    };
  }
}

function onSortOrderUpdate(value: number | null): void {
  form.sortOrder = value ?? 0;
}
</script>

<template>
  <v-container>
    <Teleport to="#editor-actions" defer>
      <v-btn variant="text" size="small" @click="router.back()">Отмена</v-btn>
      <v-btn variant="tonal" size="small" prepend-icon="mdi-eye" @click="previewOpen = !previewOpen">
        {{ previewOpen ? 'Редактор' : 'Предпросмотр' }}
      </v-btn>
      <v-btn color="primary" size="small" :loading="saving" :disabled="!canSubmit" @click="save()">Сохранить</v-btn>
    </Teleport>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-alert v-if="rulesHint" type="info" variant="tonal" density="compact" class="mb-4">{{ rulesHint }}</v-alert>

    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <v-card>
      <v-card-text>
        <v-text-field v-model="form.title" label="Заголовок" autofocus />

        <v-textarea v-model="form.summary" label="Краткое описание" rows="2" auto-grow class="mt-3" />

        <v-autocomplete
          v-model="form.keywordIds"
          :items="keywords"
          item-title="name"
          item-value="id"
          label="Признаки"
          multiple
          chips
          closable-chips
          variant="outlined"
          density="comfortable"
          hide-details="auto"
          class="mt-1"
        />

        <div class="mt-1">
          <TreeSelect
            v-model="form.sectionCode"
            :items="sectionItems"
            label="Раздел"
            empty-text="В мире пока нет разделов"
            search-label="Поиск по разделам"
            placeholder="— Без раздела —"
            clearable
          />
        </div>

        <v-row dense class="mt-1">
          <v-col cols="12" md="6">
            <v-select
              v-model="form.visibility"
              :items="LORE_ARTICLE_VISIBILITY_OPTIONS"
              item-title="title"
              item-value="value"
              label="Видимость"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
          <v-col cols="12" md="6">
            <ClampedNumberField
              :model-value="form.sortOrder"
              label="Порядок"
              :min="0"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              @update:model-value="onSortOrderUpdate"
            />
          </v-col>
        </v-row>

        <v-row dense class="mt-1">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="form.code"
              label="Код"
              :placeholder="loreSlug(form.title)"
              :readonly="codeLocked"
              :hint="codeLocked ? 'Код не меняется после создания' : 'Стабильный код ссылки'"
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              v-model="form.status"
              :items="LORE_STATUS_OPTIONS"
              item-title="title"
              item-value="value"
              label="Статус"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
        </v-row>

        <div v-if="form.visibility === 'secret'" class="mt-3">
          <LoreReadersField v-model="form.readers" :readers="article?.readers ?? []" :world-code="worldCode" />
        </div>

        <v-btn
          v-if="!isNew"
          variant="text"
          size="small"
          prepend-icon="mdi-plus-box-outline"
          class="mt-2"
          @click="openCreateFromSelection"
        >
          Создать статью из выделения
        </v-btn>

        <div class="mt-3">
          <template v-if="previewOpen">
            <LoreArticleBody :html="form.bodyHtml" :known-codes="knownCodes" />
          </template>
          <LoreArticleEditor
            v-else
            ref="editorRef"
            v-model="form.bodyHtml"
            :articles="world?.articles ?? []"
            :rules="rules"
            @create-article="openCreateFromSelection"
          />
        </div>

        <div class="text-caption text-medium-emphasis mt-3">Версия: {{ form.expectedVersion ?? '—' }}</div>
      </v-card-text>
    </v-card>

    <v-dialog v-model="createOpen" max-width="520">
      <v-card>
        <v-card-title>Новая статья из выделения</v-card-title>
        <v-card-text>
          <v-text-field v-model="newTitle" label="Заголовок" autofocus />
          <v-text-field v-model="newCode" label="Код" :placeholder="loreSlug(newTitle)" />
          <TreeSelect
            v-model="newSection"
            :items="sectionItems"
            label="Раздел"
            empty-text="В мире пока нет разделов"
            search-label="Поиск по разделам"
            placeholder="— Без раздела —"
            clearable
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="createOpen = false">Отмена</v-btn>
          <v-btn color="primary" @click="createLinkedArticle">Создать и вставить ссылку</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="leaveOpen" max-width="460">
      <v-card>
        <v-card-title>Несохранённые правки</v-card-title>
        <v-card-text>Статья изменена. Если уйти сейчас, правки потеряются.</v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="stayOnPage">Остаться</v-btn>
          <v-spacer />
          <v-btn color="error" @click="confirmLeave">Уйти без сохранения</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <LoreConflictDialog
      :conflict="conflict"
      :current-version="form.expectedVersion ?? 0"
      @reload="reloadFromServer"
      @overwrite="overwrite"
    />

    <LoreNotice
      :model-value="notice !== null"
      :text="notice?.text ?? ''"
      :color="notice?.color ?? 'success'"
      @update:model-value="notice = null"
    />
  </v-container>
</template>
