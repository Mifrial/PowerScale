import { computed, ref, toRaw } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapConflict } from '@/modules/Roleplay/Map/Dto/MapConflict';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapEdge } from '@/modules/Roleplay/Map/Dto/MapEdge';
import type { MapEdgeEdit } from '@/modules/Roleplay/Map/Dto/MapEdgeEdit';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapLineEdit } from '@/modules/Roleplay/Map/Dto/MapLineEdit';
import type { MapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import type { MapMeta } from '@/modules/Roleplay/Map/Dto/MapMeta';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapNodeEdit } from '@/modules/Roleplay/Map/Dto/MapNodeEdit';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapVariantEdit } from '@/modules/Roleplay/Map/Dto/MapVariantEdit';
import type { MapWarning } from '@/modules/Roleplay/Map/Dto/MapWarning';
import { zeroMapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import { MAP_CANVAS_CONFIG } from '@/modules/Roleplay/Map/Constant/Canvas/MAP_CANVAS_CONFIG';
import { getMapApi } from '@/modules/Roleplay/Map/init';
import { MapApiError } from '@/modules/Roleplay/Map/Service/MapApiError';

const HISTORY_LIMIT = 50;

/** Полный черновик карты: локальные правки, публикация, конфликт и история. */
export function useMapDraft() {
  const draft = ref<MapDetail | null>(null);
  const baseline = ref<MapDetail | null>(null);
  const baselineJson = ref('');
  const saving = ref(false);
  const error = ref<string | null>(null);
  const conflict = ref<MapConflict | null>(null);
  const warnings = ref<MapWarning[]>([]);
  const tempId = ref(0);
  const history = ref<string[]>([]);
  const future = ref<string[]>([]);
  let inTransaction = false;

  const dirty = computed(() => {
    if (!draft.value || !baseline.value) return false;

    return JSON.stringify(draft.value) !== baselineJson.value;
  });

  const canEdit = computed(() => draft.value?.canEdit ?? false);
  const canUndo = computed(() => history.value.length > 0);
  const canRedo = computed(() => future.value.length > 0);

  function setBaseline(detail: MapDetail): void {
    const raw = toRaw(detail);
    const copy = structuredClone(raw);
    baseline.value = copy;
    draft.value = structuredClone(raw);
    baselineJson.value = JSON.stringify(copy);
    conflict.value = null;
    warnings.value = [];
    resetHistory();
  }

  function discard(): void {
    if (!baseline.value) return;
    draft.value = structuredClone(toRaw(baseline.value));
    conflict.value = null;
    error.value = null;
    resetHistory();
  }

  function nextTempId(): number {
    tempId.value -= 1;

    return tempId.value;
  }

  async function save(force = false): Promise<MapSaveResult | null> {
    const current = draft.value;
    const base = baseline.value;
    if (!current || !base) return null;
    saving.value = true;
    error.value = null;
    try {
      const result = await getMapApi().save({
        id: base.id,
        expectedVersion: base.version,
        force,
        map: snapshotOf(current),
      });
      accept(result);

      return result;
    } catch (caught) {
      if (caught instanceof MapApiError && caught.code === 'MAP_VERSION_CONFLICT') {
        conflict.value = conflictOf(caught.details);
      } else {
        error.value = messageOf(caught);
      }

      return null;
    } finally {
      saving.value = false;
    }
  }

  async function saveAsCopy(): Promise<MapSaveResult | null> {
    const current = draft.value;
    if (!current) return null;
    saving.value = true;
    error.value = null;
    try {
      const snapshot = snapshotOf(current);
      snapshot.name = `${current.name} (копия)`;
      snapshot.visibility = 'private';
      const result = await getMapApi().save({ map: snapshot });
      accept(result);

      return result;
    } catch (caught) {
      error.value = messageOf(caught);

      return null;
    } finally {
      saving.value = false;
    }
  }

  async function archive(): Promise<boolean> {
    const base = baseline.value;
    if (!base) return false;
    try {
      await getMapApi().archive(base.id);

      return true;
    } catch (caught) {
      error.value = messageOf(caught);

      return false;
    }
  }

  function clearWarnings(): void {
    warnings.value = [];
  }

  /** Убирает текст ошибки публикации, не трогая черновик. */
  function clearError(): void {
    error.value = null;
  }

  function beginHistory(): void {
    if (inTransaction) return;
    pushHistory();
    inTransaction = true;
  }

  function endHistory(): void {
    inTransaction = false;
  }

  function cancelHistory(): void {
    if (!inTransaction) return;
    history.value.pop();
    inTransaction = false;
  }

  function undo(): void {
    const snapshot = history.value.pop();
    if (snapshot === undefined || !draft.value) return;
    future.value.push(JSON.stringify(draft.value));
    draft.value = JSON.parse(snapshot) as MapDetail;
    inTransaction = false;
  }

  function redo(): void {
    const snapshot = future.value.pop();
    if (snapshot === undefined || !draft.value) return;
    history.value.push(JSON.stringify(draft.value));
    draft.value = JSON.parse(snapshot) as MapDetail;
    inTransaction = false;
  }

  function updateNode(nodeId: number, edit: MapNodeEdit): void {
    const node = findNode(nodeId);
    if (!node) return;
    record();
    node.isPoi = edit.isPoi;
    node.name = edit.name;
    node.shortInfo = edit.shortInfo;
    node.publicInfo = edit.publicInfo;
    node.gmInfo = edit.gmInfo;
    node.linkedMapId = edit.linkedMapId;
    node.icon = edit.icon;
  }

  function addNode(edit: MapNodeEdit, variantId: number, point: MapPoint): number {
    record();
    const node: MapNode = {
      id: nextTempId(),
      isPoi: edit.isPoi,
      name: edit.name,
      shortInfo: edit.shortInfo,
      publicInfo: edit.publicInfo,
      gmInfo: edit.gmInfo,
      linkedMapId: edit.linkedMapId,
      icon: edit.icon,
      placements: [{ variantId, x: point[0], y: point[1], isPresent: true, labelOverride: '' }],
    };
    draft.value?.nodes.push(node);

    return node.id;
  }

  function removeNode(nodeId: number): void {
    const current = draft.value;
    if (!current) return;
    record();
    current.nodes = current.nodes.filter((node) => node.id !== nodeId);
    current.edges = current.edges.filter((edge) => edge.fromNodeId !== nodeId && edge.toNodeId !== nodeId);
    for (const area of current.areas) {
      if (area.nodeId === nodeId) area.nodeId = null;
    }
  }

  function moveNode(nodeId: number, variantId: number, point: MapPoint): void {
    const node = findNode(nodeId);
    if (!node) return;
    record();
    const placement = node.placements.find((candidate) => candidate.variantId === variantId);
    if (placement) {
      placement.x = point[0];
      placement.y = point[1];

      return;
    }

    node.placements.push({ variantId, x: point[0], y: point[1], isPresent: true, labelOverride: '' });
  }

  function updateArea(areaId: number, edit: MapAreaEdit): void {
    const area = findArea(areaId);
    if (!area) return;
    record();
    Object.assign(area, areaFields(edit));
  }

  function addArea(edit: MapAreaEdit, variantId: number, path: MapPoint[]): number {
    record();
    const area: MapArea = {
      id: nextTempId(),
      ...areaFields(edit),
      shapes: path.length >= 3 ? [{ variantId, path: copyPoints(path), isPresent: true }] : [],
    };
    draft.value?.areas.push(area);

    return area.id;
  }

  function removeArea(areaId: number): void {
    const current = draft.value;
    if (!current) return;
    record();
    current.areas = current.areas.filter((area) => area.id !== areaId);
  }

  function translateArea(areaId: number, variantId: number, delta: MapPoint): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape) return;
    record();
    shape.path = shape.path.map((point) => [point[0] + delta[0], point[1] + delta[1]] as MapPoint);
  }

  function setAreaPath(areaId: number, variantId: number, path: MapPoint[]): void {
    if (!findArea(areaId)) return;
    record();
    const shape = ensureAreaShape(areaId, variantId);
    if (!shape) return;
    shape.path = copyPoints(path);
  }

  function setAreaVertex(areaId: number, variantId: number, index: number, point: MapPoint): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape || index < 0 || index >= shape.path.length) return;
    record();
    shape.path[index] = [point[0], point[1]];
  }

  function insertAreaVertex(areaId: number, variantId: number, index: number, point: MapPoint): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape) return;
    record();
    shape.path.splice(Math.min(Math.max(0, index + 1), shape.path.length), 0, [point[0], point[1]]);
  }

  function removeAreaVertex(areaId: number, variantId: number, index: number): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape || shape.path.length <= 3 || index < 0 || index >= shape.path.length) return;
    record();
    shape.path.splice(index, 1);
  }

  function updateLine(lineId: number, edit: MapLineEdit): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    line.terrainCode = edit.terrainCode;
    line.label = edit.label;
    normalizeLine(line);
  }

  function addLine(edit: MapLineEdit, variantId: number, points: MapPoint[]): number {
    record();
    const width = Math.max(1, Math.round(Number(edit.width) || 1));
    const line: MapLine = {
      id: nextTempId(),
      variantId,
      terrainCode: edit.terrainCode,
      points: copyPoints(points),
      widths: points.map(() => width),
      handles: autoHandles(points),
      label: edit.label,
      isPresent: true,
    };
    draft.value?.lines.push(line);

    return line.id;
  }

  function removeLine(lineId: number): void {
    const current = draft.value;
    if (!current) return;
    record();
    current.lines = current.lines.filter((line) => line.id !== lineId);
  }

  function translateLine(lineId: number, delta: MapPoint): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    line.points = line.points.map((point) => [point[0] + delta[0], point[1] + delta[1]] as MapPoint);
  }

  function setLinePoints(lineId: number, points: MapPoint[]): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    normalizeLine(line);
    line.points = copyPoints(points);
  }

  function setLineVertex(lineId: number, index: number, point: MapPoint): void {
    const line = findLine(lineId);
    if (!line || index < 0 || index >= line.points.length) return;
    record();
    line.points[index] = [point[0], point[1]];
  }

  function setLineHandle(lineId: number, index: number, side: 'in' | 'out', offset: MapPoint): void {
    const line = findLine(lineId);
    if (!line || index < 0 || index >= line.points.length) return;
    record();
    normalizeLine(line);
    line.handles[index][side] = [Math.round(offset[0]), Math.round(offset[1])];
  }

  function setLineWidth(lineId: number, index: number, width: number): void {
    const line = findLine(lineId);
    if (!line || index < 0 || index >= line.points.length) return;
    record();
    normalizeLine(line);
    line.widths[index] = Math.min(20000, Math.max(1, Math.round(width)));
  }

  /** Автоматические ручки Безье: плавная кривая по точкам, концы остаются прямыми. */
  function smoothLine(lineId: number): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    normalizeLine(line);
    line.handles = autoHandles(line.points);
  }

  /** Одна ширина на все точки ленты. */
  function setLineWidthAll(lineId: number, width: number): void {
    const line = findLine(lineId);
    if (!line || line.points.length === 0) return;
    record();
    normalizeLine(line);
    const value = Math.min(20000, Math.max(1, Math.round(width)));
    line.widths = line.points.map(() => value);
  }

  function insertLineVertex(lineId: number, index: number, point: MapPoint, width: number, handle: MapLineHandle): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    normalizeLine(line);
    const position = Math.min(Math.max(0, index + 1), line.points.length);
    line.points.splice(position, 0, [Math.round(point[0]), Math.round(point[1])]);
    line.widths.splice(position, 0, Math.max(1, Math.round(width)));
    line.handles.splice(position, 0, {
      in: [Math.round(handle.in[0]), Math.round(handle.in[1])],
      out: [Math.round(handle.out[0]), Math.round(handle.out[1])],
    });
  }

  function removeLineVertex(lineId: number, index: number): void {
    const line = findLine(lineId);
    if (!line || line.points.length <= 2 || index < 0 || index >= line.points.length) return;
    record();
    normalizeLine(line);
    line.points.splice(index, 1);
    line.widths.splice(index, 1);
    line.handles.splice(index, 1);
  }

  function updateEdge(edgeId: number, edit: MapEdgeEdit): void {
    const edge = findEdge(edgeId);
    if (!edge) return;
    record();
    Object.assign(edge, edgeFields(edit));
  }

  function addEdge(edit: MapEdgeEdit, variantId: number): number {
    record();
    const edge: MapEdge = {
      id: nextTempId(),
      variantId,
      ...edgeFields(edit),
      path: [],
    };
    draft.value?.edges.push(edge);

    return edge.id;
  }

  function removeEdge(edgeId: number): void {
    const current = draft.value;
    if (!current) return;
    record();
    current.edges = current.edges.filter((edge) => edge.id !== edgeId);
  }

  function setEdgePath(edgeId: number, path: MapPoint[]): void {
    const edge = findEdge(edgeId);
    if (!edge) return;
    record();
    edge.path = copyPoints(path);
  }

  function setEdgeVertex(edgeId: number, index: number, point: MapPoint): void {
    const edge = findEdge(edgeId);
    if (!edge || index <= 0 || index >= edge.path.length - 1) return;
    record();
    edge.path[index] = [point[0], point[1]];
  }

  function insertEdgeVertex(edgeId: number, index: number, point: MapPoint): void {
    const edge = findEdge(edgeId);
    if (!edge || edge.path.length < 2) return;
    record();
    const position = Math.min(Math.max(1, index + 1), edge.path.length - 1);
    edge.path.splice(position, 0, [point[0], point[1]]);
  }

  function removeEdgeVertex(edgeId: number, index: number): void {
    const edge = findEdge(edgeId);
    if (!edge || index <= 0 || index >= edge.path.length - 1) return;
    record();
    edge.path.splice(index, 1);
  }

  function updateVariant(variantId: number, edit: MapVariantEdit): void {
    const variant = findVariant(variantId);
    if (!variant) return;
    record();
    variant.label = edit.label;
    variant.fromMinutes = edit.fromMinutes === null ? null : Math.round(edit.fromMinutes);
    variant.sortOrder = Math.round(edit.sortOrder);
    variant.millimetersPerPixel = edit.millimetersPerPixel === null ? null : Math.round(edit.millimetersPerPixel);
    variant.isDefault = edit.isDefault;
    if (edit.isBaseFileTouched) {
      variant.baseFile = edit.filePublicId === null
        ? null
        : {
            publicId: edit.filePublicId,
            width: edit.fileWidth ?? null,
            height: edit.fileHeight ?? null,
          };
    }
    applyDefaults(variantId, edit.isDefault);
  }

  /** Дополняет размеры подложки после загрузки картинки, если сервер их не вернул. */
  function setVariantBaseSize(variantId: number, width: number, height: number): void {
    const variant = findVariant(variantId);
    if (!variant || !variant.baseFile) return;
    if (variant.baseFile.width === width && variant.baseFile.height === height) return;
    variant.baseFile.width = width;
    variant.baseFile.height = height;
  }

  function addVariant(edit: MapVariantEdit): number {
    record();
    const variant: MapVariant = {
      id: nextTempId(),
      label: edit.label,
      fromMinutes: edit.fromMinutes === null ? null : Math.round(edit.fromMinutes),
      sortOrder: Math.round(edit.sortOrder),
      baseFile: edit.filePublicId === null
        ? null
        : {
            publicId: edit.filePublicId,
            width: edit.fileWidth ?? null,
            height: edit.fileHeight ?? null,
          },
      millimetersPerPixel: edit.millimetersPerPixel === null ? null : Math.round(edit.millimetersPerPixel),
      isDefault: edit.isDefault,
    };
    draft.value?.variants.push(variant);
    applyDefaults(variant.id, edit.isDefault);

    return variant.id;
  }

  function removeVariant(variantId: number): void {
    const current = draft.value;
    if (!current || current.variants.length <= 1) return;
    record();
    const removed = current.variants.find((variant) => variant.id === variantId);
    current.variants = current.variants.filter((variant) => variant.id !== variantId);
    current.nodes = current.nodes.map((node) => ({
      ...node,
      placements: node.placements.filter((placement) => placement.variantId !== variantId),
    }));
    current.areas = current.areas.map((area) => ({
      ...area,
      shapes: area.shapes.filter((shape) => shape.variantId !== variantId),
    }));
    current.lines = current.lines.filter((line) => line.variantId !== variantId);
    current.edges = current.edges.filter((edge) => edge.variantId !== variantId);
    if (removed?.isDefault && current.variants.length > 0) {
      current.variants[0].isDefault = true;
    }
  }

  function updateMeta(meta: MapMeta): void {
    const current = draft.value;
    if (!current) return;
    record();
    current.name = meta.name;
    current.description = meta.description;
    current.visibility = meta.visibility;
  }

  function accept(result: MapSaveResult): void {
    const { warnings: saveWarnings, ...detail } = result;
    const copy = structuredClone(detail);
    baseline.value = copy;
    draft.value = structuredClone(detail);
    baselineJson.value = JSON.stringify(copy);
    warnings.value = saveWarnings;
    conflict.value = null;
    error.value = null;
    resetHistory();
  }

  function applyDefaults(variantId: number, isDefault: boolean): void {
    const variants = draft.value?.variants;
    if (!variants || variants.length === 0) return;
    if (isDefault) {
      for (const variant of variants) {
        variant.isDefault = variant.id === variantId;
      }

      return;
    }

    if (!variants.some((variant) => variant.isDefault)) {
      const first = variants.find((variant) => variant.id === variantId) ?? variants[0];
      first.isDefault = true;
    }
  }

  function record(): void {
    if (inTransaction) return;
    pushHistory();
  }

  function pushHistory(): void {
    if (!draft.value) return;
    history.value.push(JSON.stringify(draft.value));
    if (history.value.length > HISTORY_LIMIT) history.value.shift();
    future.value = [];
  }

  function resetHistory(): void {
    history.value = [];
    future.value = [];
    inTransaction = false;
  }

  function normalizeLine(line: MapLine): void {
    while (line.widths.length < line.points.length) line.widths.push(1);
    while (line.handles.length < line.points.length) line.handles.push(zeroMapLineHandle());
    line.widths = line.widths.slice(0, line.points.length);
    line.handles = line.handles.slice(0, line.points.length);
  }

  function findNode(nodeId: number): MapNode | undefined {
    return draft.value?.nodes.find((node) => node.id === nodeId);
  }

  function findArea(areaId: number): MapArea | undefined {
    return draft.value?.areas.find((area) => area.id === areaId);
  }

  function findAreaShape(areaId: number, variantId: number) {
    return findArea(areaId)?.shapes.find((shape) => shape.variantId === variantId);
  }

  function ensureAreaShape(areaId: number, variantId: number) {
    const area = findArea(areaId);
    if (!area) return undefined;
    let shape = area.shapes.find((candidate) => candidate.variantId === variantId);
    if (!shape) {
      shape = { variantId, path: [], isPresent: true };
      area.shapes.push(shape);
    }

    return shape;
  }

  function findLine(lineId: number): MapLine | undefined {
    return draft.value?.lines.find((line) => line.id === lineId);
  }

  function findEdge(edgeId: number): MapEdge | undefined {
    return draft.value?.edges.find((edge) => edge.id === edgeId);
  }

  function findVariant(variantId: number): MapVariant | undefined {
    return draft.value?.variants.find((variant) => variant.id === variantId);
  }

  return {
    draft,
    baseline,
    saving,
    error,
    conflict,
    warnings,
    dirty,
    canEdit,
    canUndo,
    canRedo,
    setBaseline,
    discard,
    save,
    saveAsCopy,
    archive,
    clearWarnings,
    clearError,
    beginHistory,
    endHistory,
    cancelHistory,
    undo,
    redo,
    nextTempId,
    updateNode,
    addNode,
    removeNode,
    moveNode,
    updateArea,
    addArea,
    removeArea,
    translateArea,
    setAreaPath,
    setAreaVertex,
    insertAreaVertex,
    removeAreaVertex,
    updateLine,
    addLine,
    removeLine,
    translateLine,
    setLinePoints,
    setLineVertex,
    setLineHandle,
    setLineWidth,
    setLineWidthAll,
    smoothLine,
    insertLineVertex,
    removeLineVertex,
    updateEdge,
    addEdge,
    removeEdge,
    setEdgePath,
    setEdgeVertex,
    insertEdgeVertex,
    removeEdgeVertex,
    updateVariant,
    addVariant,
    removeVariant,
    setVariantBaseSize,
    updateMeta,
  };
}

function snapshotOf(detail: MapDetail): MapSnapshot {
  const raw = toRaw(detail);
  const bounds = variantBounds(detail);

  return {
    name: detail.name,
    description: detail.description,
    visibility: detail.visibility,
    variants: detail.variants.map((variant) => ({
      id: variant.id,
      label: variant.label,
      fromMinutes: variant.fromMinutes,
      sortOrder: variant.sortOrder,
      filePublicId: variant.baseFile?.publicId ?? null,
      millimetersPerPixel: variant.millimetersPerPixel,
      isDefault: variant.isDefault,
    })),
    nodes: raw.nodes.map((node) => ({
      ...node,
      placements: node.placements.map((placement) => {
        const point = clampPoint([placement.x, placement.y], boundsFor(bounds, placement.variantId));

        return { ...placement, x: point[0], y: point[1] };
      }),
    })),
    areas: raw.areas.map((area) => ({
      ...area,
      shapes: area.shapes.map((shape) => ({
        ...shape,
        path: clampPoints(shape.path, boundsFor(bounds, shape.variantId)),
      })),
    })),
    lines: raw.lines.map((line) => lineForSave(line, boundsFor(bounds, line.variantId))),
    edges: raw.edges.map((edge) => ({
      ...edge,
      path: clampPoints(edge.path, boundsFor(bounds, edge.variantId)),
      distanceMeters: edge.distanceMeters === null ? null : Math.max(0, Math.round(edge.distanceMeters)),
    })),
  };
}

interface WorldBounds {
  width: number;
  height: number;
}

function variantBounds(detail: MapDetail): Map<number, WorldBounds> {
  const bounds = new Map<number, WorldBounds>();
  for (const variant of detail.variants) {
    bounds.set(variant.id, {
      width: positiveSize(variant.baseFile?.width, MAP_CANVAS_CONFIG.fallbackWidth),
      height: positiveSize(variant.baseFile?.height, MAP_CANVAS_CONFIG.fallbackHeight),
    });
  }

  return bounds;
}

function boundsFor(bounds: Map<number, WorldBounds>, variantId: number): WorldBounds {
  return (
    bounds.get(variantId) ?? {
      width: MAP_CANVAS_CONFIG.fallbackWidth,
      height: MAP_CANVAS_CONFIG.fallbackHeight,
    }
  );
}

function positiveSize(value: number | null | undefined, fallback: number): number {
  return typeof value === 'number' && value > 0 ? value : fallback;
}

function lineForSave(line: MapLine, bounds: WorldBounds): MapLine {
  const points = clampPoints(line.points, bounds);
  const widths = (line.widths ?? []).map((width) => Math.min(20000, Math.max(1, Math.round(width))));
  const handles = (line.handles ?? []).map((handle) => ({
    in: roundPoint(handle.in),
    out: roundPoint(handle.out),
  }));
  while (widths.length < points.length) widths.push(widths[widths.length - 1] ?? 1);
  while (handles.length < points.length) handles.push({ in: [0, 0], out: [0, 0] });

  return {
    ...line,
    points,
    widths: widths.slice(0, points.length),
    handles: handles.slice(0, points.length),
  };
}

function clampPoint(point: MapPoint, bounds: WorldBounds): MapPoint {
  return [clampCoordinate(point[0], bounds.width), clampCoordinate(point[1], bounds.height)];
}

function clampCoordinate(value: number, max: number): number {
  if (!Number.isFinite(value)) return 0;

  return Math.min(max, Math.max(0, Math.round(value)));
}

function clampPoints(points: MapPoint[], bounds: WorldBounds): MapPoint[] {
  return points.map((point) => clampPoint(point, bounds));
}

function roundPoint(point: MapPoint): MapPoint {
  return [Math.round(point[0]), Math.round(point[1])];
}

function copyPoints(points: MapPoint[]): MapPoint[] {
  return points.map((point) => [point[0], point[1]] as MapPoint);
}

/** Плавные ручки по соседним точкам; концы не изгибаются. */
function autoHandles(points: MapPoint[]): MapLineHandle[] {
  if (points.length < 3) {
    return points.map(() => zeroMapLineHandle());
  }
  const last = points.length - 1;

  return points.map((point, index) => {
    if (index === 0 || index === last) {
      return zeroMapLineHandle();
    }

    const previous = points[index - 1];
    const next = points[index + 1];
    const tangentX = next[0] - previous[0];
    const tangentY = next[1] - previous[1];
    const length = Math.hypot(tangentX, tangentY);
    if (length === 0) {
      return zeroMapLineHandle();
    }

    const unitX = tangentX / length;
    const unitY = tangentY / length;
    const before = Math.hypot(point[0] - previous[0], point[1] - previous[1]) / 3;
    const after = Math.hypot(next[0] - point[0], next[1] - point[1]) / 3;

    return {
      in: [-Math.round(unitX * before), -Math.round(unitY * before)],
      out: [Math.round(unitX * after), Math.round(unitY * after)],
    };
  });
}

function areaFields(edit: MapAreaEdit): Omit<MapArea, 'id' | 'shapes'> {
  return {
    layer: edit.layer,
    terrainCode: edit.layer === 'terrain' ? edit.terrainCode : null,
    kind: edit.layer === 'object' ? edit.kind : 'other',
    label: edit.label,
    nodeId: edit.nodeId,
    blocksMovement: edit.blocksMovement,
  };
}

function edgeFields(edit: MapEdgeEdit): Omit<MapEdge, 'id' | 'variantId' | 'path'> {
  return {
    fromNodeId: edit.fromNodeId,
    toNodeId: edit.toNodeId,
    terrainCode: edit.terrainCode,
    distanceMeters: edit.distanceMeters === null ? null : Math.round(edit.distanceMeters),
    isDirected: edit.isDirected,
    label: edit.label,
  };
}

function conflictOf(details: unknown): MapConflict | null {
  if (typeof details !== 'object' || details === null) return null;
  const record = details as Record<string, unknown>;
  if (typeof record.currentVersion !== 'number') return null;

  return {
    currentVersion: record.currentVersion,
    updatedAt: typeof record.updatedAt === 'number' ? record.updatedAt : 0,
    updatedBy: typeof record.updatedBy === 'number' ? record.updatedBy : null,
  };
}

function messageOf(caught: unknown): string {
  if (caught instanceof MapApiError) return caught.message;
  if (caught instanceof Error) return caught.message;

  return 'Не удалось сохранить карту';
}

/** Публичный API черновика для редактора. */
export type MapDraftApi = ReturnType<typeof useMapDraft>;
