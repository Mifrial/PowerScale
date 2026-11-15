<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import type { LoreArticleDetail } from '@/modules/Roleplay/Lore/Dto/LoreArticleDetail';
import type { LoreArticleInput } from '@/modules/Roleplay/Lore/Dto/LoreArticleInput';
import type { LoreConflict } from '@/modules/Roleplay/Lore/Dto/LoreConflict';
import type { LoreWorldDetail } from '@/modules/Roleplay/Lore/Dto/LoreWorldDetail';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
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
import LoreArticleBody from '@/modules/Roleplay/Lore/Component/LoreArticleBody.vue';
import LoreArticleEditor from '@/modules/Roleplay/Lore/Component/LoreArticleEditor.vue';
import LoreConflictDialog from '@/modules/Roleplay/Lore/Component/LoreConflictDialog.vue';
import LoreReadersField from '@/modules/Roleplay/Lore/Component/LoreReadersField.vue';

const route = useRoute();
const router = useRouter();
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
const snackbar = ref<string | null>(null);
const previewOpen = ref(false);
const conflict = ref<LoreConflict | null>(null);
const editorRef = ref<InstanceType<typeof LoreArticleEditor> | null>(null);
const createOpen = ref(false);
const newTitle = ref('');
const newCode = ref('');
const newSection = ref<string | null>(null);
const createdSelection = ref('');

const draftStore = useLoreDraft(worldCode.value);
const draftCode = ref(articleCode.value ?? 'new');
let draftTimer: ReturnType<typeof setTimeout> | null = null;
let dirty = false;
let hydrating = true;

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
const sectionOptions = computed(() => [
  { value: null as string | null, title: '— Без раздела —' },
  ...(world.value?.sections ?? []).map((section) => ({ value: section.code, title: section.name })),
]);
const knownCodes = computed(() => (world.value ? world.value.articles.map((item) => item.code) : []));
const canSubmit = computed(() => form.title.trim() !== '' && isLoreCode(form.code));
const resolvedCode = computed(() => form.code.trim() || loreSlug(form.title));

onMounted(async () => {
  await loadWorld();
  await loadArticle();
  await loadRules();
  await fetchTags();
  restoreDraft();
  hydrating = false;
  dirty = false;
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  if (draftTimer) clearTimeout(draftTimer);
});

onBeforeRouteLeave(() => {
  if (!dirty) return true;

  return window.confirm('Есть несохранённые правки статьи. Уйти без сохранения?');
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
  queueMicrotask(() => {
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
  snackbar.value = 'Восстановлен локальный черновик.';
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
    snackbar.value = 'Статья сохранена.';
    if (isNew.value) {
      draftStore.clear(draftCode.value);
      draftCode.value = saved.code;
      await router.replace({
        name: 'LoreArticleEdit',
        params: { worldCode: worldCode.value, articleCode: saved.code },
      });
    }
    dirty = false;
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
  void loadArticle();
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
    snackbar.value = 'Укажите название и корректный код новой статьи.';

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
    snackbar.value = 'Статья создана и вставлена ссылка.';
  } catch (exception) {
    snackbar.value = exception instanceof LoreApiError ? exception.message : 'Не удалось создать статью';
  }
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

    <v-row>
      <v-col cols="12" md="8">
        <v-card>
          <v-card-text>
            <v-text-field v-model="form.title" label="Заголовок" autofocus class="mb-2" />
            <v-row dense>
              <v-col cols="12" md="4">
                <v-text-field
                  v-model="form.code"
                  label="Код"
                  :placeholder="loreSlug(form.title)"
                  :readonly="codeLocked"
                  :hint="codeLocked ? 'Код не меняется после создания' : 'Стабильный код ссылки'"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="form.sectionCode"
                  :items="sectionOptions"
                  item-title="title"
                  item-value="value"
                  label="Раздел"
                />
              </v-col>
              <v-col cols="12" md="2">
                <v-text-field v-model.number="form.sortOrder" type="number" label="Порядок" min="0" />
              </v-col>
              <v-col cols="12" md="2">
                <v-select
                  v-model="form.status"
                  :items="LORE_STATUS_OPTIONS"
                  item-title="title"
                  item-value="value"
                  label="Статус"
                />
              </v-col>
            </v-row>
            <v-textarea v-model="form.summary" label="Краткое описание" rows="2" auto-grow class="mt-2" />
            <v-btn
              v-if="!isNew"
              variant="text"
              size="small"
              prepend-icon="mdi-plus-box-outline"
              class="mt-1"
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
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="4">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-2">Доступ</v-card-title>
          <v-card-text>
            <v-select
              v-model="form.visibility"
              :items="LORE_ARTICLE_VISIBILITY_OPTIONS"
              item-title="title"
              item-value="value"
              label="Видимость"
              density="compact"
              class="mb-3"
            />
            <LoreReadersField
              v-if="form.visibility === 'secret'"
              v-model="form.readers"
              :readers="article?.readers ?? []"
              :world-code="worldCode"
              class="mt-3"
            />
            <v-alert v-else type="info" variant="tonal" density="compact" class="mt-3">
              Публичная статья видна всем, кому доступен мир.
            </v-alert>
            <div class="text-caption text-medium-emphasis mt-3">Версия: {{ form.expectedVersion ?? '—' }}</div>
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-2">Признаки</v-card-title>
          <v-card-text>
            <v-select
              v-model="form.keywordIds"
              :items="keywords"
              item-title="name"
              item-value="id"
              label="Признаки"
              multiple
              chips
              density="compact"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-dialog v-model="createOpen" max-width="520">
      <v-card>
        <v-card-title>Новая статья из выделения</v-card-title>
        <v-card-text>
          <v-text-field v-model="newTitle" label="Заголовок" autofocus />
          <v-text-field v-model="newCode" label="Код" :placeholder="loreSlug(newTitle)" />
          <v-select v-model="newSection" :items="sectionOptions" item-title="title" item-value="value" label="Раздел" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="createOpen = false">Отмена</v-btn>
          <v-btn color="primary" @click="createLinkedArticle">Создать и вставить ссылку</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <LoreConflictDialog
      :conflict="conflict"
      :current-version="form.expectedVersion ?? 0"
      @reload="reloadFromServer"
      @overwrite="overwrite"
    />

    <v-snackbar :model-value="snackbar !== null" :timeout="4000" @update:model-value="snackbar = null">
      {{ snackbar }}
    </v-snackbar>
  </v-container>
</template>
