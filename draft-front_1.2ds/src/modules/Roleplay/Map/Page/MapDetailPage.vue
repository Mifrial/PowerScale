<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapHit } from '@/modules/Roleplay/Map/Dto/MapHit';
import type { MapLineEdit } from '@/modules/Roleplay/Map/Dto/MapLineEdit';
import type { MapLineDecor } from '@/modules/Roleplay/Map/Dto/MapLineDecor';
import type { MapGroupEdit } from '@/modules/Roleplay/Map/Dto/MapGroupEdit';
import type { MapMeta } from '@/modules/Roleplay/Map/Dto/MapMeta';
import type { MapNodeEdit } from '@/modules/Roleplay/Map/Dto/MapNodeEdit';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapSize } from '@/modules/Roleplay/Map/Dto/MapSize';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import type { MapVariantEdit } from '@/modules/Roleplay/Map/Dto/MapVariantEdit';
import type { MapDetailMode } from '@/modules/Roleplay/Map/Enum/MapDetailMode';
import { useMapDetail } from '@/modules/Roleplay/Map/Composables/useMapDetail';
import { useMapDraft } from '@/modules/Roleplay/Map/Composables/useMapDraft';
import { useMapEditor } from '@/modules/Roleplay/Map/Composables/useMapEditor';
import { useMapRecognition } from '@/modules/Roleplay/Map/Composables/useMapRecognition';
import { useMapRoute } from '@/modules/Roleplay/Map/Composables/useMapRoute';
import { mapGeometryService } from '@/modules/Roleplay/Map/init';
import MapCanvas from '@/modules/Roleplay/Map/Component/Map/MapCanvas.vue';
import MapToolbar from '@/modules/Roleplay/Map/Component/Map/MapToolbar.vue';
import MapSidebar from '@/modules/Roleplay/Map/Component/Map/MapSidebar.vue';
import MapRecognitionPanel from '@/modules/Roleplay/Map/Component/Map/MapRecognitionPanel.vue';
import MapRoutePanel from '@/modules/Roleplay/Map/Component/Map/MapRoutePanel.vue';
import MapInfoPanel from '@/modules/Roleplay/Map/Component/Map/MapInfoPanel.vue';
import MapNodeDialog from '@/modules/Roleplay/Map/Component/Map/MapNodeDialog.vue';
import MapAreaDialog from '@/modules/Roleplay/Map/Component/Map/MapAreaDialog.vue';
import MapGroupDialog from '@/modules/Roleplay/Map/Component/Map/MapGroupDialog.vue';
import MapLineDialog from '@/modules/Roleplay/Map/Component/Map/MapLineDialog.vue';
import MapVariantDialog from '@/modules/Roleplay/Map/Component/Map/MapVariantDialog.vue';
import MapCalibrationDialog from '@/modules/Roleplay/Map/Component/Map/MapCalibrationDialog.vue';
import MapSettingsDialog from '@/modules/Roleplay/Map/Component/Map/MapSettingsDialog.vue';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import MapConflictDialog from '@/modules/Roleplay/Map/Component/Map/MapConflictDialog.vue';
import MapHelpDialog from '@/modules/Roleplay/Map/Component/Map/MapHelpDialog.vue';

const route = useRoute();
const router = useRouter();
const draftState = useMapDraft();
const detail = useMapDetail({ map: draftState.draft });
const { draft, saving, conflict, dirty, canEdit, error: saveError } = draftState;
const { catalog, variant, selectedVariantId, selected, loading, error: loadError } = detail;
const routeState = useMapRoute({ map: draft, variant });

const mode = ref<MapDetailMode>('view');
const nodeOpen = ref(false);
const nodeTargetId = ref<number | null>(null);
const pendingPoint = ref<MapPoint | null>(null);
const areaOpen = ref(false);
const areaTargetId = ref<number | null>(null);
const pendingPath = ref<MapPoint[] | null>(null);
const lineOpen = ref(false);
const lineTargetId = ref<number | null>(null);
const pendingLine = ref<MapPoint[] | null>(null);
const variantOpen = ref(false);
const variantTargetId = ref<number | null>(null);
const suggestedScale = ref<number | null>(null);
const calibrationOpen = ref(false);
const calibrationPixels = ref(0);
const settingsOpen = ref(false);
const conflictOpen = ref(false);
const helpOpen = ref(false);
/** Показывать подписи всех областей; по умолчанию только крупные. */
const showAreaLabels = ref(false);
const notice = ref<string | null>(null);
const pendingLineDefaults = ref<{
  color: string;
  speed: number;
  width: number;
  colorBlend?: number;
  decor?: MapLineDecor;
} | null>(null);

const editor = useMapEditor({
  map: draft,
  variant,
  selected,
  draft: draftState,
  onOpenNode: (nodeId) => openNode(nodeId),
  onCreateNode: (point) => {
    nodeTargetId.value = null;
    pendingPoint.value = point;
    nodeOpen.value = true;
  },
  onCreateArea: (path) => {
    areaTargetId.value = null;
    pendingPath.value = path;
    areaOpen.value = true;
  },
  onCreateRect: (path) => {
    areaTargetId.value = null;
    pendingPath.value = path;
    areaOpen.value = true;
  },
  onCreateLine: (points) => {
    lineTargetId.value = null;
    pendingLine.value = points;
    lineOpen.value = true;
  },
  onCalibrate: (points) => {
    calibrationPixels.value = mapGeometryService.distance(points[0], points[1]);
    calibrationOpen.value = true;
  },
});

const recognition = useMapRecognition({
  variant,
  viewport: editor.viewport,
  canvasSize: editor.canvasSize,
  worldSize: editor.worldSize,
  draft: draftState,
});
const { previewMask, previewPaths, previewExcluded } = recognition;
const recognitionPress = ref<MapPoint | null>(null);

watch(
  () => [editor.tool.value, mode.value],
  () => {
    if (editor.tool.value !== 'recognize' || mode.value !== 'edit') recognition.clearPreview();
  },
);

const nodeTarget = computed(() => draft.value?.nodes.find((node) => node.id === nodeTargetId.value) ?? null);
const areaTarget = computed(() => draft.value?.areas.find((area) => area.id === areaTargetId.value) ?? null);

const groupOpen = ref(false);
const groupTargetId = ref<number | null>(null);
const groupTarget = computed(() => draft.value?.groups.find((group) => group.id === groupTargetId.value) ?? null);
const groupMembers = computed(() => {
  const variantId = variant.value?.id;
  const groupId = groupTargetId.value;
  if (variantId === undefined || groupId === null) return [];

  return (draft.value?.areas ?? []).filter(
    (area) => area.groupId === groupId && area.shapes.some((shape) => shape.variantId === variantId && shape.isPresent),
  );
});
const lineTarget = computed(() => draft.value?.lines.find((line) => line.id === lineTargetId.value) ?? null);
const variantTarget = computed(
  () => draft.value?.variants.find((candidate) => candidate.id === variantTargetId.value) ?? null,
);

const areaTargetNode = computed(() => {
  const nodeId = areaTarget.value?.nodeId;
  if (nodeId === null || nodeId === undefined) return null;

  return draft.value?.nodes.find((node) => node.id === nodeId) ?? null;
});

const mapOptions = computed<MapSummary[]>(() => catalog.value.filter((candidate) => candidate.id !== draft.value?.id));

const draftSize = computed(() => Math.max(editor.draftArea.value.length, editor.draftLine.value.length));
const lineDefaults = computed(() => pendingLineDefaults.value);
const activeLineIndex = computed(() => {
  const target = editor.activeTarget.value;
  if (
    target === null ||
    (target.kind !== 'line-vertex' &&
      target.kind !== 'line-width' &&
      target.kind !== 'line-handle-in' &&
      target.kind !== 'line-handle-out')
  ) {
    return null;
  }

  return target.index;
});
const rulerLabel = computed(() => {
  const points = editor.rulerPoints.value;
  if (points.length < 2) return null;
  const millimetersPerPixel = variant.value?.millimetersPerPixel ?? null;
  if (millimetersPerPixel === null) {
    return `${Math.round(editor.rulerPixels.value)} px · масштаб не задан`;
  }
  const meters = (editor.rulerPixels.value * millimetersPerPixel) / 1000;
  const text = meters >= 1000 ? `${(meters / 1000).toFixed(2)} км` : `${Math.round(meters)} м`;

  return `${text} · ${Math.round(editor.rulerPixels.value)} px`;
});

function onCanvasPointerDown(
  point: MapPoint,
  screen: { x: number; y: number },
  button: number,
  alt: boolean,
  shift: boolean,
): void {
  if (mode.value === 'route') {
    if (button === 0) routeState.pick(point);

    return;
  }
  if (mode.value === 'edit' && editor.tool.value === 'recognize') {
    recognitionPress.value = point;
  }
  editor.onPointerDown(point, screen, button, alt, shift);
}

function onCanvasPointerMove(point: MapPoint, screen: { x: number; y: number }, alt: boolean, shift: boolean): void {
  if (mode.value === 'route') return;
  editor.onPointerMove(point, screen, alt, shift);
}

function onCanvasPointerUp(point: MapPoint): void {
  if (mode.value === 'route') return;
  if (mode.value === 'edit' && editor.tool.value === 'recognize') {
    const pressed = recognitionPress.value;
    const moved = editor.isMoved.value;
    recognitionPress.value = null;
    editor.onPointerUp(point);
    if (pressed === null || moved) return;
    if (recognition.state.isPicking) {
      recognition.sampleAt(point);
    } else {
      recognition.toggleAt(point);
    }

    return;
  }
  editor.onPointerUp(point);
}

function onResize(size: MapSize): void {
  editor.resize(size);
}

function onBaseSize(size: MapSize): void {
  const current = variant.value;
  if (!current || size.width <= 0 || size.height <= 0) return;
  draftState.setVariantBaseSize(current.id, size.width, size.height);
}

function onRecognitionCreated(count: number): void {
  notice.value = `Создано объектов: ${count}`;
}

function selectHit(hit: MapHit | null): void {
  selected.value = hit;
  if (hit !== null) editor.clearSelection();
}

function openNode(nodeId: number): void {
  nodeTargetId.value = nodeId;
  pendingPoint.value = null;
  nodeOpen.value = true;
}

function openArea(areaId: number): void {
  areaTargetId.value = areaId;
  pendingPath.value = null;
  areaOpen.value = true;
}

function openLine(lineId: number): void {
  lineTargetId.value = lineId;
  pendingLine.value = null;
  pendingLineDefaults.value = null;
  lineOpen.value = true;
}

function openGroup(groupId: number): void {
  groupTargetId.value = groupId;
  groupOpen.value = true;
}

function submitGroup(edit: MapGroupEdit): void {
  const groupId = groupTargetId.value;
  if (groupId === null) return;
  const memberIds = groupMembers.value.map((area) => area.id);
  draftState.beginHistory();
  try {
    if (edit.groupLabel !== null) draftState.updateGroup(groupId, edit.groupLabel);
    if (memberIds.length > 0 && Object.keys(edit.fields).length > 0) {
      draftState.updateAreas(memberIds, edit.fields);
    }
  } finally {
    draftState.endHistory();
  }
  groupOpen.value = false;
}

function ungroupArea(areaId: number): void {
  draftState.setAreaGroup(areaId, null);
}

function openVariant(variantId: number | null): void {
  variantTargetId.value = variantId;
  suggestedScale.value = null;
  variantOpen.value = true;
}

function editHit(hit: MapHit): void {
  if (hit.kind === 'node') openNode(hit.id);
  else if (hit.kind === 'area') openArea(hit.id);
  else openLine(hit.id);
}

function removeHit(hit: MapHit): void {
  if (hit.kind === 'node') draftState.removeNode(hit.id);
  else if (hit.kind === 'area') draftState.removeArea(hit.id);
  else draftState.removeLine(hit.id);
  editor.clearTarget();
  selected.value = null;
}

/** Удаляет всё массовое выделение одним шагом истории. */
function deleteSelection(): void {
  const ids = editor.selectedIds.value;
  if (ids.length === 0) return;
  draftState.removeAreas(ids);
  editor.clearSelection();
  editor.clearTarget();
  selected.value = null;
}

function moveSelected(direction: -1 | 1): void {
  if (selected.value?.kind === 'area') draftState.moveArea(selected.value.id, direction);
  else if (selected.value?.kind === 'line') draftState.moveLine(selected.value.id, direction);
}

function submitNode(edit: MapNodeEdit): void {
  const target = nodeTarget.value;
  if (target) {
    draftState.updateNode(target.id, edit);
  } else if (pendingPoint.value && variant.value) {
    const nodeId = draftState.addNode(edit, variant.value.id, pendingPoint.value);
    selected.value = { kind: 'node', id: nodeId };
  }
  nodeOpen.value = false;
  pendingPoint.value = null;
}

function submitArea(edit: MapAreaEdit): void {
  const currentVariant = variant.value;
  if (!currentVariant) return;
  const target = areaTarget.value;
  const shape = target ? target.shapes.find((candidate) => candidate.variantId === currentVariant.id) : null;
  const path = shape?.path ?? pendingPath.value ?? [];
  const contour = shape ? mapGeometryService.flattenContour(shape.path, shape.handles ?? []) : path;
  const centroid = contour.length >= 3 ? mapGeometryService.polygonCentroid(contour) : null;
  let nodeId = edit.nodeId;

  if (edit.place !== null) {
    if (nodeId !== null) {
      draftState.updateNode(nodeId, edit.place);
    } else if (centroid !== null) {
      nodeId = draftState.addNode(edit.place, currentVariant.id, centroid);
    }
  } else if (nodeId !== null) {
    draftState.removeNode(nodeId);
    nodeId = null;
  }

  const nextEdit: MapAreaEdit = { ...edit, nodeId };
  if (target) {
    draftState.updateArea(target.id, nextEdit);
  } else if (pendingPath.value) {
    const areaId = draftState.addArea(nextEdit, currentVariant.id, pendingPath.value);
    selected.value = { kind: 'area', id: areaId };
    editor.setTool('view');
  }
  areaOpen.value = false;
  pendingPath.value = null;
}

function submitLine(edit: MapLineEdit): void {
  const target = lineTarget.value;
  if (target) {
    draftState.updateLine(target.id, edit);
  } else if (pendingLine.value && variant.value) {
    const lineId = draftState.addLine(edit, variant.value.id, pendingLine.value);
    selected.value = { kind: 'line', id: lineId };
    editor.setTool('view');
    editor.focusLineVertex(lineId, pendingLine.value.length - 1);
  }
  lineOpen.value = false;
  pendingLine.value = null;
  pendingLineDefaults.value = null;
}

function submitVariant(edit: MapVariantEdit): void {
  const target = variantTarget.value;
  if (target) {
    draftState.updateVariant(target.id, edit);
  } else {
    selectedVariantId.value = draftState.addVariant(edit);
  }
  variantOpen.value = false;
  suggestedScale.value = null;
}

function removeNode(nodeId: number): void {
  draftState.removeNode(nodeId);
  nodeOpen.value = false;
  selected.value = null;
}

function removeArea(areaId: number): void {
  draftState.removeArea(areaId);
  areaOpen.value = false;
  selected.value = null;
}

function removeLine(lineId: number): void {
  draftState.removeLine(lineId);
  lineOpen.value = false;
  selected.value = null;
}

function removeVariant(variantId: number): void {
  draftState.removeVariant(variantId);
  variantOpen.value = false;
  if (selectedVariantId.value === variantId) {
    selectedVariantId.value = null;
  }
}

function submitSettings(meta: MapMeta): void {
  draftState.updateMeta(meta);
  settingsOpen.value = false;
}

function applyCalibration(millimetersPerPixel: number): void {
  calibrationOpen.value = false;
  openVariant(variant.value?.id ?? null);
  suggestedScale.value = millimetersPerPixel;
}

function openCalibration(): void {
  mode.value = 'edit';
  cancelDrafts();
  editor.setTool('calibrate');
}

function openBackdrop(): void {
  mode.value = 'edit';
  openVariant(variant.value?.id ?? null);
}

function openHelp(): void {
  helpOpen.value = true;
}

function cancelDrafts(): void {
  pendingLineDefaults.value = null;
  editor.cancelDraft();
}

function branchLine(): void {
  const line =
    lineTarget.value ??
    (selected.value?.kind === 'line' ? draft.value?.lines.find((item) => item.id === selected.value?.id) : null);
  if (!line || !variant.value) return;
  const target = editor.activeTarget.value;
  const index = target?.kind === 'line-vertex' && target.id === line.id ? target.index : line.points.length - 1;
  const point = line.points[index];
  if (!point) return;
  pendingLineDefaults.value = {
    color: line.segments[index - 1]?.color ?? line.color,
    speed: line.segments[index - 1]?.speed ?? line.speed,
    width: Math.max(1, line.widths[index] ?? 1),
    colorBlend: line.colorBlend ?? 16,
    decor: line.decor ?? {},
  };
  mode.value = 'edit';
  selected.value = { kind: 'line', id: line.id };
  editor.startBranch(point);
}

function undoEdit(): void {
  draftState.undo();
  editor.clearTarget();
  editor.clearSelection();
  selected.value = null;
}

function redoEdit(): void {
  draftState.redo();
  editor.clearTarget();
  editor.clearSelection();
  selected.value = null;
}

async function saveDraft(): Promise<void> {
  notice.value = null;
  const result = await draftState.save(false);
  if (!result) {
    if (conflict.value) conflictOpen.value = true;

    return;
  }
  conflictOpen.value = false;
  selected.value = null;
  detail.resetVariant(result);
  notice.value = 'Карта опубликована';
}

async function overwriteDraft(): Promise<void> {
  conflictOpen.value = false;
  const result = await draftState.save(true);
  if (!result) return;
  selected.value = null;
  detail.resetVariant(result);
  notice.value = 'Карта перезаписана';
}

async function copyDraft(): Promise<void> {
  const result = await draftState.saveAsCopy();
  if (!result) return;
  conflictOpen.value = false;
  selected.value = null;
  detail.resetVariant(result);
  await router.replace({ name: 'MapDetail', params: { id: result.id } });
}

async function archiveCurrent(): Promise<void> {
  if (!window.confirm('Отправить карту в архив? Черновик будет потерян.')) return;
  const archived = await draftState.archive();
  if (archived) openMapList();
}

function refreshFromServer(): void {
  if (dirty.value && !window.confirm('Отменить несохранённые изменения и загрузить карту заново?')) return;
  loadDetail();
}

function discardDraft(): void {
  if (!window.confirm('Отменить все несохранённые изменения?')) return;
  draftState.discard();
  selected.value = null;
  editor.clearSelection();
}

function openSettings(): void {
  mode.value = 'edit';
  settingsOpen.value = true;
}

function openMapList(): void {
  void router.push({ name: 'Maps' });
}

async function loadDetail(): Promise<void> {
  const mapId = Number(route.params.id);
  if (!Number.isFinite(mapId)) return;
  const loaded = await detail.load(mapId);
  if (!loaded) return;
  draftState.setBaseline(loaded);
  detail.resetVariant(loaded);
  notice.value = null;
}

function onKeydown(event: KeyboardEvent): void {
  if (isTyping(event.target)) return;
  if (mode.value !== 'edit') return;

  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
    event.preventDefault();
    if (event.shiftKey) redoEdit();
    else undoEdit();

    return;
  }

  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a' && editor.tool.value === 'view') {
    event.preventDefault();
    editor.selectAllObjects();

    return;
  }

  if (event.key === '[' || event.key === ']') {
    if (selected.value === null) return;
    if (selected.value.kind !== 'area' && selected.value.kind !== 'line') return;
    event.preventDefault();
    moveSelected(event.key === ']' ? 1 : -1);

    return;
  }

  if (event.key === 'Delete' || event.key === 'Backspace') {
    if (editor.deleteActive()) {
      event.preventDefault();

      return;
    }
    if (editor.selectedIds.value.length > 0) {
      event.preventDefault();
      deleteSelection();

      return;
    }
    if (selected.value !== null) {
      event.preventDefault();
      removeHit(selected.value);
    }

    return;
  }

  if (event.key !== 'Enter' && event.key !== 'Escape') return;
  if (event.key === 'Escape') editor.clearSelection();
  const hasDialog =
    nodeOpen.value ||
    areaOpen.value ||
    lineOpen.value ||
    variantOpen.value ||
    calibrationOpen.value ||
    settingsOpen.value ||
    helpOpen.value;
  if (hasDialog) return;
  if (event.key === 'Enter') editor.finishDraft();
  else {
    if (editor.tool.value === 'ruler') editor.clearRuler();
    cancelDrafts();
  }
}

function isTyping(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;

  return (
    target instanceof HTMLInputElement ||
    target instanceof HTMLTextAreaElement ||
    target instanceof HTMLSelectElement ||
    target.isContentEditable
  );
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!dirty.value) return;
  event.preventDefault();
  event.returnValue = '';
}

watch(canEdit, (value) => {
  if (!value && mode.value === 'edit') mode.value = 'view';
});

watch(mode, (value) => {
  if (value !== 'edit') {
    editor.cancelDraft();
    pendingLineDefaults.value = null;
  }
  if (value !== 'route') routeState.clear();
});

watch(lineOpen, (value) => {
  if (!value) pendingLineDefaults.value = null;
});

watch(
  () => editor.tool.value,
  (value, previous) => {
    if (previous === 'line' && value !== 'line') pendingLineDefaults.value = null;
  },
);

watch(
  () => route.params.id,
  () => loadDetail(),
);

onBeforeRouteLeave(() => {
  if (!dirty.value) return true;

  return window.confirm('На карте есть несохранённые изменения. Уйти без сохранения?');
});

onMounted(() => {
  loadDetail();
  window.addEventListener('beforeunload', onBeforeUnload);
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  window.removeEventListener('beforeunload', onBeforeUnload);
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <v-container fluid class="d-flex flex-column map-page">
    <div class="d-flex flex-wrap align-center ga-3 mb-3">
      <v-btn variant="text" prepend-icon="mdi-arrow-left" @click="openMapList">Карты</v-btn>
      <h1 class="text-h6">{{ draft?.name ?? 'Карта' }}</h1>
      <v-chip v-if="draft" size="small" variant="tonal">
        {{ draft.visibility === 'public' ? 'Публичная' : 'Приватная' }}
      </v-chip>
      <v-chip v-if="draft && !canEdit" size="small" variant="tonal" color="info">Только просмотр</v-chip>
      <v-chip v-if="loading" size="small" variant="tonal">Загрузка…</v-chip>
      <v-spacer />
      <span class="text-caption text-medium-emphasis">Версия {{ draft?.version ?? '—' }}</span>
    </div>

    <MapToolbar
      v-model:mode="mode"
      :tool="editor.tool.value"
      :variant-id="selectedVariantId"
      :variants="draft?.variants ?? []"
      :can-edit="canEdit"
      :saving="saving"
      :dirty="dirty"
      :draft-size="draftSize"
      :can-undo="draftState.canUndo.value"
      :can-redo="draftState.canRedo.value"
      :labels="showAreaLabels"
      @update:tool="editor.setTool($event)"
      @update:variant-id="detail.selectVariant($event)"
      @toggle-labels="showAreaLabels = !showAreaLabels"
      @fit="editor.fitToView()"
      @zoom="editor.zoomBy($event)"
      @refresh="refreshFromServer"
      @save="saveDraft"
      @discard="discardDraft"
      @create-variant="openVariant(null)"
      @edit-variant="openVariant(variant?.id ?? null)"
      @calibrate="openCalibration"
      @finish-draft="editor.finishDraft()"
      @cancel-draft="cancelDrafts"
      @settings="openSettings"
      @backdrop="openBackdrop"
      @help="openHelp"
      @undo="undoEdit"
      @redo="redoEdit"
    />

    <div class="text-caption text-medium-emphasis mt-2">{{ editor.hint.value }}</div>
    <div v-if="mode === 'edit' && editor.selectedIds.value.length > 0" class="d-flex flex-wrap align-center ga-2 mt-1">
      <v-chip size="small" color="primary" variant="tonal">
        Выбрано: {{ editor.selectedIds.value.length }}
      </v-chip>
      <v-btn size="small" color="error" variant="tonal" prepend-icon="mdi-delete" @click="deleteSelection">
        Удалить
      </v-btn>
      <v-btn size="small" variant="text" @click="editor.clearSelection()">Снять</v-btn>
      <span class="text-caption text-medium-emphasis">
        Shift+рамка — выделить пачку, Shift+клик — добавить или убрать
      </span>
    </div>

    <v-alert v-if="loadError" type="error" variant="tonal" class="mt-3">
      {{ loadError }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="refreshFromServer">Повторить</v-btn>
      </template>
    </v-alert>
    <v-alert v-else-if="saveError" type="error" variant="tonal" class="mt-3">
      {{ saveError }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="draftState.clearError()">Скрыть</v-btn>
      </template>
    </v-alert>
    <v-alert v-if="notice" type="success" variant="tonal" class="mt-3" closable @click:close="notice = null">
      {{ notice }}
    </v-alert>

    <div class="map-workbench mt-3">
      <div class="map-workbench__canvas">
        <MapCanvas
          :map="draft"
          :variant="variant"
          :viewport="editor.viewport.value"
          :revision="draftState.revision.value"
          :show-labels="showAreaLabels"
          :selected="selected"
          :selected-target="editor.activeTarget.value"
          :selected-ids="editor.selectedIds.value"
          :marquee="editor.marquee.value"
          :live-drag="editor.liveDrag.value"
          :route="routeState.route.value"
          :draft-area="editor.draftArea.value"
          :draft-line="editor.draftLine.value"
          :rect-points="editor.rectPoints.value"
          :calibration-points="editor.calibrationPoints.value"
          :ruler-points="editor.rulerPoints.value"
          :ruler-label="rulerLabel"
          :tool="editor.tool.value"
          :cursor="editor.cursor.value"
          @pointer-down="onCanvasPointerDown"
          @pointer-move="onCanvasPointerMove"
          @pointer-up="onCanvasPointerUp"
          @pointer-dblclick="editor.onDoubleClick"
          @wheel="editor.zoomAt"
          @resize="onResize"
          :recognition-mask="previewMask"
          :recognition-paths="previewPaths"
          :recognition-excluded="previewExcluded"
          @base-size="onBaseSize"
        />
      </div>

      <div class="map-workbench__panel">
        <MapSidebar
          v-if="mode === 'edit' && draft && editor.tool.value !== 'recognize'"
          :map="draft"
          :variant="variant"
          :selected="selected"
          @select="selectHit"
          @edit="editHit"
          @create="editor.setTool($event)"
          @edit-group="openGroup"
          @ungroup="ungroupArea"
        />
        <MapRecognitionPanel
          v-if="mode === 'edit' && draft && editor.tool.value === 'recognize'"
          :recognition="recognition"
          :map-fill-opacity="draft.fillOpacity"
          :disabled="!canEdit"
          @created="onRecognitionCreated"
        />
        <MapRoutePanel
          v-if="mode === 'route'"
          :from="routeState.from.value"
          :to="routeState.to.value"
          :route="routeState.route.value"
          :error="routeState.error.value"
          :loading="false"
          @build="routeState.build"
          @clear="routeState.clear"
        />
        <v-card
          v-if="editor.tool.value === 'ruler' && editor.rulerPoints.value.length > 0"
          variant="outlined"
          class="mt-3"
        >
          <v-card-title class="text-body-2">Линейка</v-card-title>
          <v-card-text class="text-body-2">
            <div>Точек: {{ editor.rulerPoints.value.length }}</div>
            <div>{{ rulerLabel ?? 'Кликните вторую точку' }}</div>
          </v-card-text>
          <v-card-actions>
            <v-btn size="small" variant="text" @click="editor.clearRuler()">Очистить</v-btn>
          </v-card-actions>
        </v-card>
        <MapInfoPanel
          v-if="draft && selected"
          class="mt-3"
          :map="draft"
          :selected="selected"
          :active-index="activeLineIndex"
          @edit="editHit"
          @remove="removeHit"
          @branch="branchLine"
          @smooth="editor.smoothLine()"
          @corner="editor.cornerLine()"
          @width="(lineId, index, width) => draftState.setLineWidth(lineId, index, width)"
          @uniform-width="(lineId, width) => draftState.setLineWidthAll(lineId, width)"
          @raise="moveSelected(1)"
          @lower="moveSelected(-1)"
          @close="selectHit(null)"
        />
        <v-alert v-else-if="mode !== 'edit' && !loading" type="info" variant="tonal" class="mt-3">
          Выберите место, объект, поверхность или ленту на карте.
        </v-alert>
      </div>
    </div>

    <MapNodeDialog
      v-model:open="nodeOpen"
      :node="nodeTarget"
      :map-options="mapOptions"
      :submitting="saving"
      @submit="submitNode"
      @remove="removeNode"
    />

    <MapAreaDialog
      v-model:open="areaOpen"
      :area="areaTarget"
      :node="areaTargetNode"
      :groups="draft?.groups ?? []"
      :map-options="mapOptions"
      :map-fill-opacity="draft?.fillOpacity ?? MAP_FILL_OPACITY.default"
      :submitting="saving"
      @submit="submitArea"
      @remove="removeArea"
    />

    <MapGroupDialog
      v-model:open="groupOpen"
      :group="groupTarget"
      :members="groupMembers"
      :map-fill-opacity="draft?.fillOpacity ?? MAP_FILL_OPACITY.default"
      :submitting="saving"
      @submit="submitGroup"
    />

    <MapLineDialog
      v-model:open="lineOpen"
      :line="lineTarget"
      :defaults="lineDefaults"
      :map-fill-opacity="draft?.fillOpacity ?? MAP_FILL_OPACITY.default"
      :submitting="saving"
      @submit="submitLine"
      @remove="removeLine"
    />

    <MapVariantDialog
      v-model:open="variantOpen"
      :variant="variantTarget"
      :can-delete="(draft?.variants.length ?? 0) > 1"
      :submitting="saving"
      :suggested-millimeters-per-pixel="suggestedScale"
      @submit="submitVariant"
      @remove="removeVariant"
    />

    <MapCalibrationDialog
      v-model:open="calibrationOpen"
      :pixels="calibrationPixels"
      :submitting="false"
      @apply="applyCalibration"
    />

    <MapSettingsDialog
      v-if="draft"
      v-model:open="settingsOpen"
      :map="draft"
      :submitting="false"
      @submit="submitSettings"
      @archive="archiveCurrent"
    />

    <MapConflictDialog
      v-model:open="conflictOpen"
      :conflict="conflict"
      :saving="saving"
      @overwrite="overwriteDraft"
      @copy="copyDraft"
    />

    <MapHelpDialog v-model:open="helpOpen" />
  </v-container>
</template>

<style scoped>
.map-page {
  height: 100%;
}

.map-workbench {
  display: flex;
  gap: 16px;
  min-height: 480px;
  height: calc(100vh - 260px);
}

.map-workbench__canvas {
  flex: 1 1 auto;
  min-width: 0;
}

.map-workbench__panel {
  flex: 0 0 320px;
  max-width: 320px;
  overflow-y: auto;
}
</style>
