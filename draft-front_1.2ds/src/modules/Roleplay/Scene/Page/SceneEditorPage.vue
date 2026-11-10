<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import { SCENE_ELEMENT_KIND_LABELS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ELEMENT_KIND_LABELS';
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
const openPanels = ref<string[]>(['board']);
const preview = ref(false);
const waterAnimation = ref(readWaterAnimation());

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
  resolution: draft.value.resolution,
  boards: viewBoards.value,
  elements: viewElements.value,
  enclosures: viewEnclosures.value,
  selection: selection.value,
  mode: preview.value ? 'preview' : 'edit',
}));
const toolDefinition = computed(() => SCENE_TOOLS.find((item) => item.id === tool.value) ?? SCENE_TOOLS[0]);
const selectedElement = computed(() =>
  selection.value.length === 1 ? (state.selectedElements.value[0] ?? null) : null,
);
const boardTerrainKind = computed<string | null>(() => {
  const board = activeBoard.value;
  if (board === null) {
    return null;
  }

  const full = draft.value.elements.find(
    (element) =>
      element.boardId === board.id &&
      element.kind === 'terrain' &&
      element.shape.type === 'rect' &&
      element.shape.x === 0 &&
      element.shape.y === 0 &&
      element.shape.w === board.boundWidth &&
      element.shape.h === board.boundHeight,
  );

  return full?.props.kind ?? null;
});
const boardIndex = computed(() =>
  activeBoard.value === null ? -1 : draft.value.boards.findIndex((board) => board.id === activeBoard.value?.id),
);
const canSave = computed(() => draft.value.id !== null && !loading.value);
const canUndo = state.canUndo;
const canRedo = state.canRedo;
const pathWidth = computed(() =>
  Math.max(1, Math.round(draft.value.resolution * (toolDefinition.value.widthIpari ?? 4))),
);
const elementTitle = computed(() =>
  selectedElement.value === null ? 'Элемент' : `Элемент · ${SCENE_ELEMENT_KIND_LABELS[selectedElement.value.kind]}`,
);
const zoomLabel = computed(() => (viewport.value === null ? '—' : `${Math.round(viewport.value.zoom * 100)}%`));

watch(
  () => selectedElement.value?.id ?? null,
  (elementId) => {
    if (elementId !== null) {
      openPanels.value = ['element'];
    } else if (selection.value.length > 1) {
      openPanels.value = ['multi'];
    } else if (selection.value.length === 0) {
      openPanels.value = ['board'];
    }
  },
);

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

/** Готовая фигура из кликов (полигон террейна). */
function onShape(shape: SceneShape): void {
  const definition = toolDefinition.value;
  if (definition === null) return;
  state.createElement(definition, shape);
}

/** Готовая лента из кликов. */
function onPath(points: ScenePoint[]): void {
  const definition = toolDefinition.value;
  if (definition === null) return;
  const shoreWidth =
    definition.shoreWidthIpari === undefined
      ? undefined
      : Math.max(1, Math.round(draft.value.resolution * definition.shoreWidthIpari));
  state.createPath(definition, points, pathWidth.value, shoreWidth === undefined ? undefined : { shoreWidth });
}

/** Перетаскивание вершины полигона или ленты. */
function onVertex(elementId: number, index: number, point: ScenePoint): void {
  state.moveVertex(elementId, index, point);
}

/** Перетаскивание ручки Безье. */
function onHandle(elementId: number, index: number, side: SceneHandleSide, offset: ScenePoint, mirror: boolean): void {
  state.moveHandle(elementId, index, side, offset, mirror);
}

/** Перетаскивание ручки ширины ленты. */
function onWidth(elementId: number, index: number, width: number): void {
  state.setPathWidth(elementId, index, width);
}

/** Двойной клик по контуру вставляет вершину. */
function onInsertVertex(elementId: number, point: ScenePoint, tolerance: number): void {
  state.insertVertex(elementId, point, tolerance);
}

/** Alt+клик по вершине удаляет её. */
function onRemoveVertex(elementId: number, index: number): void {
  state.removeVertex(elementId, index);
}

/** Начало перетаскивания: один шаг истории. */
function beginEdit(): void {
  state.beginEdit();
}

/** Конец перетаскивания. */
function endEdit(): void {
  state.endEdit();
}

function undo(): void {
  state.undo();
}

function redo(): void {
  state.redo();
}

/** Одна ширина на все вершины ленты. */
function setWidthAll(width: number): void {
  if (selectedElement.value === null) return;
  state.setPathWidthAll(selectedElement.value.id, width);
}

/** Сглаживает выделенную фигуру. */
function smoothShape(): void {
  if (selectedElement.value === null) return;
  state.smoothShape(selectedElement.value.id);
}

/** Убирает кривизну выделенной фигуры. */
function cornerShape(): void {
  if (selectedElement.value === null) return;
  state.cornerShape(selectedElement.value.id);
}

/** Заливка поля террейном выбранного шаблона. */
function fillTerrain(boardId: number, kind: string): void {
  state.fillTerrain(kind, boardId);
}

/** Растягивает выделенный террейн на всё поле. */
function stretchTerrain(): void {
  if (selectedElement.value === null) return;
  state.stretchTerrain(selectedElement.value.id);
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

  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
    event.preventDefault();
    if (event.shiftKey) {
      state.redo();
    } else {
      state.undo();
    }

    return;
  }

  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
    event.preventDefault();
    state.duplicateSelected();

    return;
  }

  if ((event.key === 'Delete' || event.key === 'Backspace') && selection.value.length > 0) {
    event.preventDefault();
    state.removeSelected();

    return;
  }

  if (event.key === 'Escape') {
    if (preview.value) {
      preview.value = false;

      return;
    }

    tool.value = 'select';
    state.select([]);
  }
}

const WATER_ANIMATION_KEY = 'scene.waterAnimation';

function readWaterAnimation(): boolean {
  if (typeof window === 'undefined') {
    return true;
  }

  const stored = window.localStorage.getItem(WATER_ANIMATION_KEY);
  if (stored === '0') {
    return false;
  }

  if (stored === '1') {
    return true;
  }

  return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function setWaterAnimation(value: boolean | null): void {
  waterAnimation.value = value === true;
  window.localStorage.setItem(WATER_ANIMATION_KEY, waterAnimation.value ? '1' : '0');
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

    <SceneToolbar
      v-if="!preview"
      v-model:tool="tool"
      :has-selection="selection.length > 0"
      :can-undo="canUndo"
      :can-redo="canRedo"
      @remove="removeSelected"
      @undo="undo"
      @redo="redo"
    />

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
          :path-width="pathWidth"
          :water-animation="waterAnimation"
          :interactive="!preview"
          @draw="onDraw"
          @shape="onShape"
          @path="onPath"
          @vertex="onVertex"
          @handle="onHandle"
          @width="onWidth"
          @insert-vertex="onInsertVertex"
          @remove-vertex="onRemoveVertex"
          @edit-start="beginEdit"
          @edit-end="endEdit"
          @move="onMove"
          @selection="onSelection"
          @viewport="onViewport"
        />
        <div class="scene-editor__view-bar">
          <v-tooltip location="bottom" :text="preview ? 'Вернуться к редактированию (Esc)' : 'Предпросмотр'">
            <template #activator="{ props: tooltipProps }">
              <v-btn
                v-bind="tooltipProps"
                :icon="preview ? 'mdi-pencil' : 'mdi-eye'"
                size="small"
                variant="flat"
                color="surface-variant"
                @click="preview = !preview"
              />
            </template>
          </v-tooltip>
          <v-switch
            v-if="preview"
            :model-value="waterAnimation"
            label="Анимация воды"
            density="compact"
            hide-details
            color="primary"
            class="scene-editor__water-switch"
            @update:model-value="setWaterAnimation"
          />
        </div>
      </div>

      <aside v-if="!preview" class="scene-editor__panel">
        <v-expansion-panels v-model="openPanels" multiple variant="accordion" class="scene-editor__panels">
          <v-expansion-panel value="location">
            <v-expansion-panel-title class="text-subtitle-2">Локация</v-expansion-panel-title>
            <v-expansion-panel-text>
              <SceneLocationSettings :draft="draft" @meta="state.setMeta" @resolution="rescaleResolution" />
            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel v-if="activeBoard !== null" value="board">
            <v-expansion-panel-title class="text-subtitle-2">
              <span>Поле</span>
              <v-spacer />
              <v-btn
                icon="mdi-arrow-left"
                size="x-small"
                variant="text"
                :disabled="boardIndex <= 0"
                title="Поле влево"
                @click.stop="state.moveBoard(activeBoard.id, -1)"
              />
              <v-btn
                icon="mdi-arrow-right"
                size="x-small"
                variant="text"
                :disabled="boardIndex >= draft.boards.length - 1"
                title="Поле вправо"
                @click.stop="state.moveBoard(activeBoard.id, 1)"
              />
              <v-btn
                icon="mdi-delete"
                size="x-small"
                variant="text"
                color="error"
                :disabled="draft.boards.length <= 1"
                title="Удалить поле"
                @click.stop="removeBoard(activeBoard.id)"
              />
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <SceneBoardSettings
                :key="`${activeBoard.id}-${boardPanelKey}`"
                :board="activeBoard"
                :resolution="draft.resolution"
                :terrain-kind="boardTerrainKind"
                @rename="renameBoard"
                @update="updateBoard"
                @backdrop="updateBackdrop"
                @terrain="fillTerrain"
              />
            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel v-if="selectedElement !== null" value="element">
            <v-expansion-panel-title class="text-subtitle-2">
              <span>{{ elementTitle }}</span>
              <v-tooltip location="top" max-width="320">
                <template #activator="{ props: tooltipProps }">
                  <v-icon v-bind="tooltipProps" icon="mdi-information-outline" size="16" class="ml-2" @click.stop />
                </template>
                <span class="text-caption">
                  Клик по вершине показывает её ручки. Двойной клик по ребру вставляет вершину, Alt+клик по вершине
                  удаляет её, Alt при перетаскивании ручки разрывает зеркальность. Ромб на вершине ленты задаёт ширину.
                </span>
              </v-tooltip>
              <v-spacer />
              <v-btn
                icon="mdi-delete"
                size="x-small"
                variant="text"
                color="error"
                title="Удалить элемент"
                @click.stop="removeSelected"
              />
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <SceneElementSettings
                :element="selectedElement"
                :boards="draft.boards"
                :resolution="draft.resolution"
                @update="updateElementProps"
                @target="updateElementTarget"
                @asset="updateElementAsset"
                @stretch="stretchTerrain"
                @width-all="setWidthAll"
                @smooth="smoothShape"
                @corner="cornerShape"
              />
            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel v-else-if="selection.length > 1" value="multi">
            <v-expansion-panel-title class="text-subtitle-2">Элементы</v-expansion-panel-title>
            <v-expansion-panel-text>
              <div class="text-body-2">Выбрано элементов: {{ selection.length }}</div>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
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
  position: relative;
  flex: 1 1 auto;
  height: 100%;
  min-height: 0;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.25);
}

.scene-editor__view-bar {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 8px;
  pointer-events: none;
}

.scene-editor__view-bar > * {
  pointer-events: auto;
}

.scene-editor__water-switch {
  padding: 0 10px;
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.78);
}

.scene-editor__panel {
  flex: 0 0 380px;
  min-width: 300px;
  height: 100%;
  max-height: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 8px;
  scrollbar-gutter: stable;
  scrollbar-width: auto;
  scrollbar-color: rgba(71, 85, 105, 0.8) rgba(100, 116, 139, 0.18);
}

.scene-editor__panel > * {
  flex: 0 0 auto;
}

.scene-editor__panels {
  flex: 0 0 auto;
}

.scene-editor__panel::-webkit-scrollbar {
  width: 14px;
}

.scene-editor__panel::-webkit-scrollbar-track {
  background: rgba(100, 116, 139, 0.18);
  border-radius: 999px;
}

.scene-editor__panel::-webkit-scrollbar-thumb {
  background: rgba(71, 85, 105, 0.8);
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

@media (max-width: 720px) {
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
    max-height: 60vh;
    overflow-y: auto;
  }
}
</style>
