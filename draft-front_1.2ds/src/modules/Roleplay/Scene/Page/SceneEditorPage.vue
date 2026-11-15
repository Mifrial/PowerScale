<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import SceneBoardSettings from '@/modules/Roleplay/Scene/Component/Scene/SceneBoardSettings.vue';
import SceneBoardTabs from '@/modules/Roleplay/Scene/Component/Scene/SceneBoardTabs.vue';
import SceneConflictDialog from '@/modules/Roleplay/Scene/Component/Scene/SceneConflictDialog.vue';
import SceneCanvas from '@/modules/Roleplay/Scene/Component/Scene/SceneCanvas.vue';
import SceneElementSettings from '@/modules/Roleplay/Scene/Component/Scene/SceneElementSettings.vue';
import SceneLocationSettings from '@/modules/Roleplay/Scene/Component/Scene/SceneLocationSettings.vue';
import SceneOverlayControls from '@/modules/Roleplay/Scene/Component/Scene/SceneOverlayControls.vue';
import ScenePreviewActorPanel from '@/modules/Roleplay/Scene/Component/Scene/ScenePreviewActorPanel.vue';
import SceneObjectSaveDialog from '@/modules/Roleplay/Scene/Component/Scene/SceneObjectSaveDialog.vue';
import SceneObjectPalette from '@/modules/Roleplay/Scene/Component/Scene/SceneObjectPalette.vue';
import SceneToolbar from '@/modules/Roleplay/Scene/Component/Scene/SceneToolbar.vue';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneConflict } from '@/modules/Roleplay/Scene/Dto/SceneConflict';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneOverlay } from '@/modules/Roleplay/Scene/Dto/SceneOverlay';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneToolDefinition } from '@/modules/Roleplay/Scene/Dto/SceneToolDefinition';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneVertexPart } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneDecorZone } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneHandleSide } from '@/modules/Roleplay/Scene/Enum/SceneHandleSide';
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import { SCENE_ELEMENT_KIND_LABELS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ELEMENT_KIND_LABELS';
import { SCENE_ASSET_COLLECTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ASSET_COLLECTIONS';
import { SCENE_EMPTY_DETAIL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_EMPTY_DETAIL';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';
import { SCENE_WALL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WALL';
import { useSceneDraft } from '@/modules/Roleplay/Scene/Composables/useSceneDraft';
import { useSceneFullscreen } from '@/modules/Roleplay/Scene/Composables/useSceneFullscreen';
import { useScenePreviewActors } from '@/modules/Roleplay/Scene/Composables/useScenePreviewActors';
import { getSceneApi } from '@/modules/Roleplay/Scene/init';
import { getAssetApi } from '@/modules/Library/Asset/init';
import { SceneApiError } from '@/modules/Roleplay/Scene/Service/SceneApiError';
import { SceneDraftMapper } from '@/modules/Roleplay/Scene/Service/SceneDraftMapper';
import { sceneWallService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallService';
import { sceneRampAttachment, type SceneDeckEnd } from '@/modules/Roleplay/Scene/Service/SceneRampAttachment';
import {
  sceneFootprintLocalPoint,
  sceneSilhouetteLocalPoint,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import {
  sceneObjectMeta,
  sceneObjectTemplateOf,
  sceneObjectTemplateProps,
} from '@/modules/Roleplay/Scene/Service/SceneObjectTemplate';

const AssetLibraryDialog = defineAsyncComponent(
  () => import('@/modules/Library/Asset/Component/AssetLibraryDialog.vue'),
);

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
/** Обводка основания выбранного декора: клики собираются в полигон. */
const outlineTarget = ref<SceneDecorZone | null>(null);
/** Сохранение декора как объекта библиотеки. */
const objectSaveOpen = ref(false);
/** Выбор готового объекта из библиотеки. */
const objectApplyOpen = ref(false);
/** Готовые объекты палитры и объект, ждущий клика по карте. */
const objectList = ref<AssetItem[]>([]);
const objectListLoading = ref(false);
const objectListError = ref<string | null>(null);
const pendingObject = ref<AssetItem | null>(null);
const conflict = ref<SceneConflict | null>(null);
const viewport = ref<SceneViewport | null>(null);
const boardPanelKey = ref(0);
const openPanels = ref<string[]>(['board']);
const preview = ref(false);
const {
  full: previewFull,
  toggle: toggleFullscreen,
  close: closeFullscreen,
} = useSceneFullscreen({
  target: () => document.querySelector<HTMLElement>('.scene-editor'),
  fit: () => canvas.value?.fit(),
});
const overlayCosts = ref(false);
const overlayVisibility = ref(false);
const overlay = computed<SceneOverlay | null>(() =>
  preview.value && (overlayCosts.value || overlayVisibility.value)
    ? { costs: overlayCosts.value, visibility: overlayVisibility.value, observer: null, radius: null }
    : null,
);
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
const {
  actors: previewActorList,
  selectedId: previewSelectedId,
  selectedActor: previewSelectedActor,
  route: previewRoute,
  levelMenu: previewLevelMenu,
  select: selectPreviewActor,
  setSightRadius: setPreviewSightRadius,
  onMapClick: onPreviewClick,
  chooseLevel: previewChooseLevel,
} = useScenePreviewActors({
  enabled: () => preview.value,
  board: () => activeViewBoard.value,
  elements: () => viewElements.value,
  resolution: () => draft.value.resolution,
});
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

watch(
  () => openPanels.value.includes('objects'),
  (isOpen) => {
    if (isOpen && objectList.value.length === 0 && !objectListLoading.value) {
      void loadObjects();
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
  const pending = pendingObject.value;
  if (pending !== null) {
    placeObject(pending, from);

    return;
  }

  const definition = toolDefinition.value;
  if (definition === null) return;
  if (definition.kind === 'opening') {
    insertOpeningOnWall(definition, from, to);

    return;
  }

  if (definition.kind === 'obstacle' && definition.shapeType === 'wall') {
    state.createFromDrag(definition, snapWallPoint(from), snapWallPoint(to), presetExtra(definition));

    return;
  }

  state.createFromDrag(definition, from, to);
}

/**
 * Проём ставится на стену, а не в пустое поле.
 *
 * Протяжка вдоль стены задаёт ширину; короткий клик — ширину пресета по
 * центру проекции. Ширина и смещение считаются в целых единицах сцены.
 */
function insertOpeningOnWall(definition: SceneToolDefinition, from: ScenePoint, to: ScenePoint): void {
  const board = activeBoard.value;
  if (board === null) {
    return;
  }

  const resolution = draft.value.resolution;
  const width = Math.max(1, Math.round(resolution * (definition.openingWidthIpari ?? SCENE_WALL.doorWidthIpari)));
  const minWidth = Math.max(1, Math.round(resolution * SCENE_WALL.minOpeningIpari));
  const tolerance = viewport.value === null ? 0 : 8 / viewport.value.zoom;
  const placement = sceneWallService.place(draft.value.elements, board.id, from, to, width, minWidth, tolerance);
  if (placement === null) {
    error.value = 'Проём ставится на стену: подведите его к полосе стены';

    return;
  }

  const extra = presetExtra(definition) ?? {};
  state.createElement(definition, placement.shape, {
    ...extra,
    wallId: placement.wallId,
    offset: placement.offset,
  });
  error.value = null;
}

/** Точка стены притягивается к концу уже нарисованной стены или к сетке. */
function snapWallPoint(point: ScenePoint): ScenePoint {
  const board = activeBoard.value;
  if (board === null) {
    return point;
  }

  const tolerance = viewport.value === null ? 0 : 12 / viewport.value.zoom;

  return sceneWallService.snapPoint(draft.value.elements, board.id, point, tolerance, board.gridStep);
}

/** Готовая фигура из кликов: полигон террейна или обводка основания. */
function onShape(shape: SceneShape): void {
  if (outlineTarget.value !== null) {
    applyOutline(shape);

    return;
  }

  const definition = toolDefinition.value;
  if (definition === null) return;
  state.createElement(definition, shape, presetExtra(definition));
}

/**
 * Свойства пресета в единицах сцены: кромка, глубина и высота заданы в ипари.
 *
 * Пресет один на все фигуры, а разрешение локации меняется, поэтому перевод
 * в единицы делается в момент создания, а не в константе.
 */
function presetExtra(definition: SceneToolDefinition): SceneElementProps | undefined {
  const extra: SceneElementProps = {};
  if (definition.shoreWidthIpari !== undefined) {
    extra.shoreWidth = Math.max(1, Math.round(draft.value.resolution * definition.shoreWidthIpari));
  }
  if (definition.depthIpari !== undefined) {
    extra.depth = Math.max(0, Math.round(draft.value.resolution * definition.depthIpari));
  }
  if (definition.heightIpari !== undefined) {
    extra.height = Math.max(1, Math.round(draft.value.resolution * definition.heightIpari));
  }
  if (definition.sillIpari !== undefined) {
    extra.sill = Math.max(0, Math.round(draft.value.resolution * definition.sillIpari));
  }
  if (definition.lintelIpari !== undefined) {
    extra.lintel = Math.max(0, Math.round(draft.value.resolution * definition.lintelIpari));
  }

  return Object.keys(extra).length === 0 ? undefined : extra;
}

/** Начинает обводку зоны выбранного декора: основания или силуэта. */
function startOutline(target: SceneDecorZone): void {
  if (selectedElement.value === null || selectedElement.value.shape.type !== 'point') {
    error.value = 'Обвести зону можно только у точечного декора';

    return;
  }

  outlineTarget.value = target;
  tool.value = 'select';
  error.value = null;
  notice.value =
    target === 'footprint'
      ? 'Обведите основание: клики по карте, двойной клик — замкнуть, Esc — отмена'
      : 'Обведите зону непрозрачности: клики по карте, двойной клик — замкнуть, Esc — отмена';
}

/**
 * Применяет нарисованный полигон как зону декора.
 *
 * Клики приходят в мировых координатах; в свойстве хранится локальный контур
 * от якоря, повёрнутый на `props.rotation`. Поэтому точка переводится обратным
 * поворотом — тогда зона едет вместе со спрайтом.
 */
function applyOutline(shape: SceneShape): void {
  const element = selectedElement.value;
  const zone = outlineTarget.value;
  outlineTarget.value = null;
  notice.value = null;
  if (element === null || element.shape.type !== 'point' || shape.type !== 'polygon' || zone === null) {
    return;
  }

  if (zone === 'footprint') {
    const offset = element.props.footprint?.offset;
    const points = shape.points.map((point): ScenePoint => sceneFootprintLocalPoint(element, point));
    const footprint = { shape: 'polygon' as const, points, ...(offset === undefined ? {} : { offset }) };
    state.updateElementProps(element.id, { ...element.props, footprint });
    notice.value = 'Основание обведено';

    return;
  }

  const offset = element.props.silhouette?.offset;
  const bottom = element.props.silhouette?.bottom ?? 0;
  const top = element.props.silhouette?.top;
  const points = shape.points.map((point): ScenePoint => sceneSilhouetteLocalPoint(element, point));
  const silhouette = {
    shape: 'polygon' as const,
    points,
    bottom,
    ...(offset === undefined ? {} : { offset }),
    ...(top === undefined ? {} : { top }),
  };
  state.updateElementProps(element.id, { ...element.props, silhouette });
  notice.value = 'Зона непрозрачности обведена';
}

/** Готовая лента из кликов. */
function onPath(points: ScenePoint[]): void {
  const definition = toolDefinition.value;
  if (definition === null) return;
  state.createPath(definition, points, pathWidth.value, presetExtra(definition));
}

/** Перетаскивание вершины полигона, ленты или зоны декора. */
function onVertex(elementId: number, index: number, point: ScenePoint, part: SceneVertexPart = 'shape'): void {
  if (part !== 'shape') {
    state.moveZoneVertex(elementId, part, index, point);

    return;
  }

  state.moveVertex(elementId, index, point);
}

/** Перетаскивание ручки Безье. */
function onHandle(
  elementId: number,
  index: number,
  side: SceneHandleSide,
  offset: ScenePoint,
  mirror: boolean,
  part: SceneVertexPart = 'shape',
): void {
  if (part !== 'shape') {
    state.moveZoneHandle(elementId, part, index, side, offset, mirror);

    return;
  }

  state.moveHandle(elementId, index, side, offset, mirror);
}

/** Перетаскивание ручки ширины ленты. */
function onWidth(elementId: number, index: number, width: number): void {
  state.setPathWidth(elementId, index, width);
}

/** Двойной клик по контуру вставляет вершину. */
function onInsertVertex(
  elementId: number,
  point: ScenePoint,
  tolerance: number,
  part: SceneVertexPart = 'shape',
): void {
  if (part !== 'shape') {
    state.insertZoneVertex(elementId, part, point, tolerance);

    return;
  }

  state.insertVertex(elementId, point, tolerance);
}

/** Alt+клик по вершине удаляет её. */
function onRemoveVertex(elementId: number, index: number, part: SceneVertexPart = 'shape'): void {
  if (part !== 'shape') {
    state.removeZoneVertex(elementId, part, index);

    return;
  }

  state.removeVertex(elementId, index);
}

/** Сглаживает полигон зоны декора. */
function smoothZone(zone: SceneDecorZone): void {
  if (selectedElement.value === null) return;
  state.smoothZone(selectedElement.value.id, zone);
}

/** Убирает кривизну зоны декора. */
function cornerZone(zone: SceneDecorZone): void {
  if (selectedElement.value === null) return;
  state.cornerZone(selectedElement.value.id, zone);
}

/** Шаблон выбранного декора: снимок основания, силуэта и скорости. */
const objectTemplate = computed(() =>
  selectedElement.value === null ? null : sceneObjectTemplateOf(selectedElement.value, draft.value.resolution),
);
const objectMeta = computed(() => (objectTemplate.value === null ? null : sceneObjectMeta(objectTemplate.value)));
const objectFileId = computed(() => selectedElement.value?.assetPublicId ?? null);
const objectSuggestedName = computed(() => {
  const label = selectedElement.value?.props.label?.trim() ?? '';

  return label === '' ? 'Объект' : label;
});

/** Открывает диалог сохранения объекта. */
function startSaveObject(): void {
  if (selectedElement.value === null) return;
  if (selectedElement.value.assetPublicId === null) {
    error.value = 'У декора нет ассета: сначала выберите спрайт';

    return;
  }

  error.value = null;
  objectSaveOpen.value = true;
}

/** Открывает библиотеку готовых объектов. */
function startApplyObject(): void {
  if (selectedElement.value === null) return;
  error.value = null;
  objectApplyOpen.value = true;
}

function onObjectSaved(item: AssetItem): void {
  notice.value = `Объект «${item.name}» сохранён в библиотеку`;
  void loadObjects();
}

/**
 * Готовые объекты коллекции декора.
 *
 * Палитра показывает и общие, и личные записи: объект — это шаблон, а не
 * файл, поэтому делить его по владельцу незачем. Библиотека отдаёт только
 * записи с `meta`, без шаблона ставить нечего.
 */
async function loadObjects(): Promise<void> {
  objectListLoading.value = true;
  objectListError.value = null;
  try {
    const collection = SCENE_ASSET_COLLECTIONS.decor;
    const [personal, shared] = await Promise.all([
      getAssetApi().findPage({
        collectionModule: collection.module,
        collectionCode: collection.code,
        scope: 'personal',
        hasMeta: true,
        limit: 100,
        offset: 0,
      }),
      getAssetApi().findPage({
        collectionModule: collection.module,
        collectionCode: collection.code,
        scope: 'shared',
        hasMeta: true,
        limit: 100,
        offset: 0,
      }),
    ]);
    objectList.value = [...personal.items, ...shared.items].sort((left, right) =>
      left.name.localeCompare(right.name, 'ru'),
    );
  } catch (caught) {
    objectListError.value =
      caught instanceof SceneApiError ? caught.message : 'Не удалось загрузить объекты библиотеки';
  } finally {
    objectListLoading.value = false;
  }
}

/** Вооружает постановку выбранным объектом. */
function pickObject(item: AssetItem): void {
  if (sceneObjectTemplateProps(item.meta, draft.value.resolution) === null) {
    error.value = `У ассета «${item.name}» нет шаблона объекта`;

    return;
  }

  pendingObject.value = item;
  tool.value = 'decor';
  error.value = null;
  notice.value = `Клик по карте поставит «${item.name}»; Esc — отмена`;
}

/** Снимает вооружённую постановку. */
function cancelObject(): void {
  pendingObject.value = null;
  if (tool.value === 'decor') {
    tool.value = 'select';
  }

  notice.value = null;
}

/** Ставит объект в точку карты: спрайт и физика одним шагом истории. */
function placeObject(item: AssetItem, point: ScenePoint): void {
  const definition = SCENE_TOOLS.find((entry) => entry.id === 'decor');
  const props = sceneObjectTemplateProps(item.meta, draft.value.resolution);
  const board = activeBoard.value;
  if (definition === undefined || props === null || board === null) {
    cancelObject();

    return;
  }

  const shape: SceneShape = {
    type: 'point',
    x: Math.min(board.boundWidth, Math.max(0, Math.round(point[0]))),
    y: Math.min(board.boundHeight, Math.max(0, Math.round(point[1]))),
  };

  state.beginEdit();
  const elementId = state.createElement(definition, shape, props);
  if (elementId !== null && item.filePublicId !== null) {
    state.setElementAsset(elementId, item.filePublicId, item.contentBox ?? null);
  }
  state.endEdit();

  pendingObject.value = null;
  tool.value = 'select';
  notice.value = `Объект «${item.name}» поставлен`;
}

/** Применяет шаблон объекта: спрайт и физику одним шагом истории. */
function onObjectPicked(item: AssetItem): void {
  const element = selectedElement.value;
  if (element === null) return;
  const props = sceneObjectTemplateProps(item.meta, draft.value.resolution);
  if (props === null) {
    error.value = `У ассета «${item.name}» нет шаблона объекта`;

    return;
  }

  state.beginEdit();
  state.updateElementProps(element.id, { ...element.props, ...props });
  if (item.filePublicId !== null) {
    state.setElementAsset(element.id, item.filePublicId, item.contentBox ?? null);
  }
  state.endEdit();
  error.value = null;
  notice.value = `Объект «${item.name}» применён`;
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

/** Одна ширина на все вершины ленты, толщина стены или длина проёма. */
function setWidthAll(width: number): void {
  if (selectedElement.value === null) return;
  state.setPathWidthAll(selectedElement.value.id, width);
}

/** Вставляет дверь или окно в середину выбранной стены. */
function insertWallOpening(kind: 'door' | 'window'): void {
  if (selectedElement.value === null) return;
  const elementId = state.insertOpening(selectedElement.value.id, kind);
  if (elementId === null) {
    error.value = 'Дверь и окно ставятся в стену';

    return;
  }

  notice.value = kind === 'window' ? 'Окно вставлено в стену' : 'Дверь вставлена в стену';
  error.value = null;
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

/**
 * Приставляет съезд к торцу выбранного моста.
 *
 * Геометрия считается по настилу: высокий конец съезда ложится ровно на торец,
 * длина — 1:3 к подъёму, ширина и скорость наследуются. Так «горбатый» мост
 * собирается из трёх элементов без ручной подгонки координат.
 */
function attachRamp(end: SceneDeckEnd): void {
  if (selectedElement.value === null) return;
  const attachment = sceneRampAttachment(selectedElement.value, draft.value.elements, draft.value.resolution, end);
  const definition = SCENE_TOOLS.find((item) => item.id === 'ramp');
  if (attachment === null || definition === undefined) {
    error.value = 'К этому торцу съезд не поставить: настил лежит на поверхности';

    return;
  }

  state.createElement(definition, attachment.shape, attachment.props);
  error.value = null;
  notice.value = 'Съезд приставлен к торцу';
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
  outlineTarget.value = null;
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

function updateElementAsset(
  publicId: string | null,
  contentBox: ImageContentBox | null,
  preset: SceneElementProps | null,
): void {
  const element = selectedElement.value;
  if (element === null) return;

  state.beginEdit();
  if (preset !== null) {
    state.updateElementProps(element.id, { ...element.props, ...preset });
  }
  state.setElementAsset(element.id, publicId, contentBox);
  state.endEdit();
}

function removeSelected(): void {
  state.removeSelected();
}

function onViewport(next: SceneViewport): void {
  viewport.value = next;
}

/** Предпросмотр на весь экран доступен только из предпросмотра. */
function togglePreviewFull(): void {
  if (!preview.value) return;

  void toggleFullscreen();
}

function togglePreview(): void {
  if (preview.value && previewFull.value) {
    void closeFullscreen();
  }
  preview.value = !preview.value;
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
    if (pendingObject.value !== null) {
      cancelObject();

      return;
    }

    if (outlineTarget.value !== null) {
      outlineTarget.value = null;
      notice.value = null;

      return;
    }

    if (previewFull.value) {
      void closeFullscreen();

      return;
    }

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
  <v-container fluid class="scene-editor" :class="{ 'scene-editor--full': previewFull }">
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
          :overlay="overlay"
          :actors="previewActorList"
          :selected-actor-id="previewSelectedId"
          :route="previewRoute"
          :level-menu="previewLevelMenu"
          :shape-override="outlineTarget !== null ? 'polygon' : null"
          @point="onPreviewClick"
          @level="previewChooseLevel"
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
        <SceneOverlayControls v-if="preview" v-model:costs="overlayCosts" v-model:visibility="overlayVisibility" />
        <ScenePreviewActorPanel
          v-if="preview"
          :actors="previewActorList"
          :selected="previewSelectedActor"
          :resolution="draft.resolution"
          @select="selectPreviewActor"
          @sight="setPreviewSightRadius"
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
                @click="togglePreview"
              />
            </template>
          </v-tooltip>
          <v-tooltip
            v-if="preview"
            location="bottom"
            :text="previewFull ? 'Выйти из полноэкранного режима' : 'На весь экран'"
          >
            <template #activator="{ props: tooltipProps }">
              <v-btn
                v-bind="tooltipProps"
                :icon="previewFull ? 'mdi-fullscreen-exit' : 'mdi-fullscreen'"
                size="small"
                variant="flat"
                color="surface-variant"
                @click="togglePreviewFull"
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

          <v-expansion-panel value="objects">
            <v-expansion-panel-title class="text-subtitle-2">Объекты</v-expansion-panel-title>
            <v-expansion-panel-text>
              <SceneObjectPalette
                :objects="objectList"
                :loading="objectListLoading"
                :error="objectListError"
                :pending-id="pendingObject?.id ?? null"
                :resolution="draft.resolution"
                @pick="pickObject"
                @refresh="loadObjects"
                @cancel="cancelObject"
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
                :elements="draft.elements"
                :resolution="draft.resolution"
                @update="updateElementProps"
                @target="updateElementTarget"
                @asset="updateElementAsset"
                @stretch="stretchTerrain"
                @width-all="setWidthAll"
                @insert-opening="insertWallOpening"
                @smooth="smoothShape"
                @corner="cornerShape"
                @attach-ramp="attachRamp"
                @outline="startOutline"
                @footprint-smooth="smoothZone('footprint')"
                @footprint-corner="cornerZone('footprint')"
                @silhouette-smooth="smoothZone('silhouette')"
                @silhouette-corner="cornerZone('silhouette')"
                @save-object="startSaveObject"
                @apply-object="startApplyObject"
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

    <SceneObjectSaveDialog
      v-model="objectSaveOpen"
      :file-public-id="objectFileId"
      :collection-module="SCENE_ASSET_COLLECTIONS.decor.module"
      :collection-code="SCENE_ASSET_COLLECTIONS.decor.code"
      :collection-title="SCENE_ASSET_COLLECTIONS.decor.title"
      :meta="objectMeta"
      :suggested-name="objectSuggestedName"
      @saved="onObjectSaved"
    />

    <AssetLibraryDialog
      v-model="objectApplyOpen"
      has-meta
      :collection-module="SCENE_ASSET_COLLECTIONS.decor.module"
      :collection-code="SCENE_ASSET_COLLECTIONS.decor.code"
      :collection-title="SCENE_ASSET_COLLECTIONS.decor.title"
      @select="onObjectPicked"
    />

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

.scene-editor--full {
  position: fixed;
  inset: 0;
  z-index: 4000;
  width: auto;
  max-width: none;
  height: 100dvh;
  margin: 0;
  padding: 10px;
  background: #0b1220;
}

.scene-editor--full .scene-editor__header,
.scene-editor--full .scene-board-tabs {
  display: none;
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
