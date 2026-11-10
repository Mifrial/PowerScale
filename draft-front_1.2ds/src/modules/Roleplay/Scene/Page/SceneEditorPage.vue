<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import SceneBoardSettings from '@/modules/Roleplay/Scene/Component/Scene/SceneBoardSettings.vue';
import SceneBoardTabs from '@/modules/Roleplay/Scene/Component/Scene/SceneBoardTabs.vue';
import SceneConflictDialog from '@/modules/Roleplay/Scene/Component/Scene/SceneConflictDialog.vue';
import SceneCanvas from '@/modules/Roleplay/Scene/Component/Scene/SceneCanvas.vue';
import SceneElementSettings from '@/modules/Roleplay/Scene/Component/Scene/SceneElementSettings.vue';
import SceneLocationSettings from '@/modules/Roleplay/Scene/Component/Scene/SceneLocationSettings.vue';
import SceneToolbar from '@/modules/Roleplay/Scene/Component/Scene/SceneToolbar.vue';
import type { SceneConflict } from '@/modules/Roleplay/Scene/Dto/SceneConflict';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import { SCENE_EMPTY_DETAIL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_EMPTY_DETAIL';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';
import { useSceneDraft } from '@/modules/Roleplay/Scene/Composables/useSceneDraft';
import { getSceneApi } from '@/modules/Roleplay/Scene/init';
import { SceneApiError } from '@/modules/Roleplay/Scene/Service/SceneApiError';
import { SceneDraftMapper } from '@/modules/Roleplay/Scene/Service/SceneDraftMapper';

const route = useRoute();
const router = useRouter();
const sceneId = Number(route.params.id);
const mapper = new SceneDraftMapper();
const state = useSceneDraft(SCENE_EMPTY_DETAIL);
const canvas = ref<InstanceType<typeof SceneCanvas> | null>(null);
const tool = ref<SceneTool>('select');
const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const notice = ref<string | null>(null);
const conflict = ref<SceneConflict | null>(null);
const viewport = ref<SceneViewport | null>(null);
const boardPanelKey = ref(0);

const draft = state.draft;
const selection = state.selection;
const isDirty = state.isDirty;
const activeBoard = state.activeBoard;
const viewBoards = computed(() => mapper.toViewBoards(draft.value.boards));
const viewElements = computed(() => mapper.toViewElements(draft.value.elements));
const viewEnclosures = computed(() => mapper.toViewEnclosures(draft.value.enclosures));
const activeViewBoard = computed(
  () => viewBoards.value.find((board) => board.id === draft.value.activeBoardId) ?? null,
);
const renderModel = computed<SceneRenderModel>(() => ({
  activeBoardId: draft.value.activeBoardId ?? 0,
  boards: viewBoards.value,
  elements: viewElements.value,
  enclosures: viewEnclosures.value,
  selection: selection.value,
  mode: 'edit',
}));
const toolDefinition = computed(() => SCENE_TOOLS.find((item) => item.id === tool.value) ?? SCENE_TOOLS[0]);
const selectedElement = computed(() =>
  selection.value.length === 1 ? (state.selectedElements.value[0] ?? null) : null,
);
const boardIndex = computed(() =>
  activeBoard.value === null ? -1 : draft.value.boards.findIndex((board) => board.id === activeBoard.value?.id),
);
const canSave = computed(() => draft.value.id !== null && !loading.value);
const zoomLabel = computed(() => (viewport.value === null ? '—' : `${Math.round(viewport.value.zoom * 100)}%`));

onMounted(load);
onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload);
  window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  window.removeEventListener('keydown', onKeydown);
});

onBeforeRouteLeave(() => {
  if (!isDirty.value) return true;

  return window.confirm('В локации есть несохранённые изменения. Уйти без сохранения?');
});

async function load(): Promise<void> {
  if (!Number.isFinite(sceneId) || sceneId <= 0) {
    error.value = 'Некорректный id локации';
    loading.value = false;

    return;
  }

  loading.value = true;
  error.value = null;
  try {
    const detail = await getSceneApi().get(sceneId);
    if (!detail.canEdit) {
      error.value = 'Нет прав на редактирование локации';

      return;
    }

    state.reset(detail);
    tool.value = 'select';
  } catch (exception) {
    error.value = messageOf(exception);
  } finally {
    loading.value = false;
  }
}

async function save(expectedVersion = draft.value.version): Promise<void> {
  if (draft.value.id === null) return;

  saving.value = true;
  error.value = null;
  notice.value = null;
  try {
    const detail = await getSceneApi().save({
      id: draft.value.id,
      expectedVersion,
      scene: state.snapshot(),
    });
    state.reset(detail);
    notice.value = 'Локация сохранена';
  } catch (exception) {
    const found = exception instanceof SceneApiError ? conflictOf(exception) : null;
    if (found !== null) {
      conflict.value = found;

      return;
    }

    error.value = messageOf(exception);
  } finally {
    saving.value = false;
  }
}

function overwrite(): void {
  const current = conflict.value?.currentVersion ?? null;
  conflict.value = null;
  if (current !== null) {
    void save(current);
  }
}

function reload(): void {
  conflict.value = null;
  void load();
}

function onDraw(from: ScenePoint, to: ScenePoint): void {
  const definition = toolDefinition.value;
  if (definition === null) return;
  state.createFromDrag(definition, from, to);
}

function onMove(delta: ScenePoint): void {
  state.moveElements(selection.value, delta[0], delta[1]);
}

function onSelection(ids: number[]): void {
  state.select(ids);
}

function addBoard(): void {
  state.addBoard();
  tool.value = 'select';
}

function removeBoard(boardId: number): void {
  if (!state.removeBoard(boardId)) {
    error.value = 'Нельзя удалить последнее поле';
  }
}

function renameBoard(boardId: number, name: string): void {
  if (!state.renameBoard(boardId, name)) {
    error.value = 'Имя поля пустое или уже занято';
    boardPanelKey.value += 1;
  }
}

function updateBoard(boardId: number, patch: Parameters<typeof state.updateBoard>[1]): void {
  state.updateBoard(boardId, patch);
}

function updateBackdrop(boardId: number, publicId: string | null, width: number | null, height: number | null): void {
  state.setBackdrop(boardId, publicId, width, height);
}

function updateElementProps(elementProps: SceneElementProps): void {
  if (selectedElement.value === null) return;
  state.updateElementProps(selectedElement.value.id, elementProps);
}

function rescaleResolution(next: number): void {
  if (state.setResolution(next)) {
    notice.value = 'Точность изменена: координаты пересчитаны';
    error.value = null;

    return;
  }

  error.value = 'Точность должна быть степенью двойки от 16 до 1024';
}

function updateElementTarget(boardId: number | null): void {
  if (selectedElement.value === null) return;
  state.setElementTarget(selectedElement.value.id, boardId);
}

function updateElementAsset(publicId: string | null): void {
  if (selectedElement.value === null) return;
  state.setElementAsset(selectedElement.value.id, publicId);
}

function removeSelected(): void {
  state.removeSelected();
}

function onViewport(next: SceneViewport): void {
  viewport.value = next;
}

function onKeydown(event: KeyboardEvent): void {
  const target = event.target as HTMLElement | null;
  if (target !== null && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return;

  if ((event.key === 'Delete' || event.key === 'Backspace') && selection.value.length > 0) {
    event.preventDefault();
    state.removeSelected();

    return;
  }

  if (event.key === 'Escape') {
    tool.value = 'select';
    state.select([]);
  }
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!isDirty.value) return;

  event.preventDefault();
  event.returnValue = '';
}

function conflictOf(exception: SceneApiError): SceneConflict | null {
  const details = exception.details;
  if (typeof details !== 'object' || details === null) return null;

  const values = details as Record<string, unknown>;
  if (typeof values.currentVersion !== 'number') return null;

  return {
    currentVersion: values.currentVersion,
    updatedAt: typeof values.updatedAt === 'number' ? values.updatedAt : 0,
    updatedBy: typeof values.updatedBy === 'number' ? values.updatedBy : null,
  };
}

function messageOf(exception: unknown): string {
  return exception instanceof SceneApiError ? exception.message : 'Не удалось выполнить действие';
}
</script>

<template>
  <v-container fluid class="scene-editor">
    <v-progress-linear v-if="loading" indeterminate />

    <div class="scene-editor__header">
      <v-btn
        variant="text"
        prepend-icon="mdi-arrow-left"
        @click="router.push({ name: 'SceneDetail', params: { id: sceneId } })"
      >
        К локации
      </v-btn>
      <h1 class="text-h6">{{ draft.name || 'Локация' }}</h1>
      <v-chip size="small" variant="tonal">Версия {{ draft.version }}</v-chip>
      <v-chip v-if="isDirty" size="small" color="warning" variant="tonal">Есть изменения</v-chip>
      <v-btn
        color="primary"
        prepend-icon="mdi-content-save"
        :loading="saving"
        :disabled="!canSave || saving"
        @click="save()"
      >
        Сохранить
      </v-btn>
      <v-spacer />
      <v-btn icon="mdi-magnify-minus" variant="tonal" size="small" title="Уменьшить" @click="canvas?.zoomBy(0.9)" />
      <v-btn icon="mdi-magnify-plus" variant="tonal" size="small" title="Увеличить" @click="canvas?.zoomBy(1.1)" />
      <v-btn icon="mdi-fit-to-screen" variant="tonal" size="small" title="Вписать" @click="canvas?.fit()" />
      <v-chip size="small" variant="outlined">{{ zoomLabel }}</v-chip>
    </div>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
      density="compact"
      class="mb-3"
      closable
      @click:close="error = null"
    >
      {{ error }}
    </v-alert>
    <v-alert
      v-if="notice"
      type="success"
      variant="tonal"
      density="compact"
      class="mb-3"
      closable
      @click:close="notice = null"
    >
      {{ notice }}
    </v-alert>

    <SceneToolbar v-model:tool="tool" :has-selection="selection.length > 0" @remove="removeSelected" />

    <SceneBoardTabs
      :model-value="draft.activeBoardId"
      :boards="draft.boards"
      class="my-3"
      @update:model-value="state.setActiveBoard"
      @add="addBoard"
    />

    <div class="scene-editor__workspace">
      <div class="scene-editor__canvas">
        <SceneCanvas
          ref="canvas"
          :model="renderModel"
          :board="activeViewBoard"
          :tool="tool"
          interactive
          @draw="onDraw"
          @move="onMove"
          @selection="onSelection"
          @viewport="onViewport"
        />
      </div>

      <aside class="scene-editor__panel">
        <SceneLocationSettings :draft="draft" @meta="state.setMeta" @resolution="rescaleResolution" />

        <SceneBoardSettings
          v-if="activeBoard !== null"
          :key="`${activeBoard.id}-${boardPanelKey}`"
          :board="activeBoard"
          :board-count="draft.boards.length"
          :index="boardIndex"
          :resolution="draft.resolution"
          @rename="renameBoard"
          @update="updateBoard"
          @backdrop="updateBackdrop"
          @move="(boardId, offset) => state.moveBoard(boardId, offset)"
          @remove="removeBoard"
        />

        <SceneElementSettings
          v-if="selectedElement !== null"
          :element="selectedElement"
          :boards="draft.boards"
          :resolution="draft.resolution"
          @update="updateElementProps"
          @target="updateElementTarget"
          @asset="updateElementAsset"
          @remove="removeSelected"
        />
        <v-card v-else-if="selection.length > 1" variant="outlined">
          <v-card-text class="text-body-2">Выбрано элементов: {{ selection.length }}</v-card-text>
        </v-card>
      </aside>
    </div>

    <SceneConflictDialog
      :conflict="conflict"
      :current-version="draft.version"
      @reload="reload"
      @overwrite="overwrite"
    />
  </v-container>
</template>

<style scoped>
.scene-editor {
  display: flex;
  flex-direction: column;
  height: calc(100vh - var(--v-layout-top, 0px));
  height: calc(100dvh - var(--v-layout-top, 0px));
  min-height: 0;
  overflow: hidden;
}

.scene-editor__header {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.scene-editor__workspace {
  display: flex;
  gap: 16px;
  flex: 1 1 auto;
  min-height: 0;
}

.scene-editor__canvas {
  flex: 1 1 auto;
  height: 100%;
  min-height: 0;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.25);
}

.scene-editor__panel {
  flex: 0 0 380px;
  min-width: 300px;
  height: 100%;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: scroll;
  overflow-x: hidden;
  padding-right: 8px;
  scrollbar-gutter: stable;
  scrollbar-width: thin;
  scrollbar-color: rgba(100, 116, 139, 0.65) rgba(148, 163, 184, 0.12);
}

.scene-editor__panel::-webkit-scrollbar {
  width: 12px;
}

.scene-editor__panel::-webkit-scrollbar-track {
  background: rgba(148, 163, 184, 0.12);
  border-radius: 999px;
}

.scene-editor__panel::-webkit-scrollbar-thumb {
  background: rgba(100, 116, 139, 0.65);
  border: 3px solid transparent;
  background-clip: content-box;
  border-radius: 999px;
}

.scene-editor__panel::-webkit-scrollbar-thumb:hover {
  background: rgba(71, 85, 105, 0.85);
  border: 3px solid transparent;
  background-clip: content-box;
}

@media (max-width: 1100px) {
  .scene-editor__panel {
    flex-basis: 320px;
  }
}

@media (max-width: 900px) {
  .scene-editor {
    height: auto;
    overflow: visible;
  }

  .scene-editor__workspace {
    flex-direction: column;
  }

  .scene-editor__canvas {
    height: 60vh;
  }

  .scene-editor__panel {
    flex-basis: auto;
    min-width: 0;
    height: auto;
    max-height: none;
    overflow-y: visible;
  }
}
</style>
