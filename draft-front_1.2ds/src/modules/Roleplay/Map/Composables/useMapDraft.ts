import { computed, ref, toRaw } from 'vue';
import type { MapArea } from '@/modules/Roleplay/Map/Dto/MapArea';
import type { MapAreaDecor } from '@/modules/Roleplay/Map/Dto/MapAreaDecor';
import type { MapAreaEdit } from '@/modules/Roleplay/Map/Dto/MapAreaEdit';
import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';
import type { MapConflict } from '@/modules/Roleplay/Map/Dto/MapConflict';
import type { MapDetail } from '@/modules/Roleplay/Map/Dto/MapDetail';
import type { MapLine } from '@/modules/Roleplay/Map/Dto/MapLine';
import type { MapLineDecor } from '@/modules/Roleplay/Map/Dto/MapLineDecor';
import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapLineEdit } from '@/modules/Roleplay/Map/Dto/MapLineEdit';
import type { MapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import type { MapLineSegment } from '@/modules/Roleplay/Map/Dto/MapLineSegment';
import type { MapMeta } from '@/modules/Roleplay/Map/Dto/MapMeta';
import type { MapNode } from '@/modules/Roleplay/Map/Dto/MapNode';
import type { MapNodeEdit } from '@/modules/Roleplay/Map/Dto/MapNodeEdit';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';
import type { MapSaveResult } from '@/modules/Roleplay/Map/Dto/MapSaveResult';
import type { MapSnapshot } from '@/modules/Roleplay/Map/Dto/MapSnapshot';
import type { MapVariant } from '@/modules/Roleplay/Map/Dto/MapVariant';
import type { MapVariantEdit } from '@/modules/Roleplay/Map/Dto/MapVariantEdit';
import type { MapWarning } from '@/modules/Roleplay/Map/Dto/MapWarning';
import { zeroMapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import { zeroMapLineHandle } from '@/modules/Roleplay/Map/Dto/MapLineHandle';
import { MAP_FILL_OPACITY } from '@/modules/Roleplay/Map/Constant/Map/MAP_FILL_OPACITY';
import {
  MAP_LINE_DEFAULT_COLOR_BLEND,
  MAP_LINE_FLOW_DEFAULTS,
} from '@/modules/Roleplay/Map/Constant/Map/MAP_LINE_DECOR';
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
    for (const area of draft.value?.areas ?? []) {
      area.shapes.forEach(normalizeAreaShape);
    }
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
    const current = draft.value;
    if (!current) return 0;
    const area: MapArea = {
      id: nextTempId(),
      ...areaFields(edit),
      sortOrder: nextSortOrder(current.areas),
      shapes:
        path.length >= 3
          ? [
              {
                variantId,
                path: copyPoints(path),
                handles: path.map(() => zeroMapHandle()),
                isPresent: true,
              },
            ]
          : [],
    };
    current.areas.push(area);

    return area.id;
  }

  /** Поднимает или опускает область внутри её слоя. */
  function moveArea(areaId: number, direction: -1 | 1): void {
    const current = draft.value;
    const area = findArea(areaId);
    if (!current || !area) return;
    const group = current.areas
      .filter((candidate) => candidate.layer === area.layer)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    const index = group.findIndex((candidate) => candidate.id === areaId);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= group.length) return;
    record();
    [group[index], group[target]] = [group[target], group[index]];
    group.forEach((candidate, order) => {
      candidate.sortOrder = order;
    });
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
    normalizeAreaShape(shape);
  }

  function setAreaVertex(areaId: number, variantId: number, index: number, point: MapPoint): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape || index < 0 || index >= shape.path.length) return;
    record();
    shape.path[index] = [point[0], point[1]];
  }

  function insertAreaVertex(
    areaId: number,
    variantId: number,
    index: number,
    point: MapPoint,
    handle: MapHandle = zeroMapHandle(),
  ): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape) return;
    record();
    normalizeAreaShape(shape);
    const position = Math.min(Math.max(0, index + 1), shape.path.length);
    shape.path.splice(position, 0, [point[0], point[1]]);
    shape.handles.splice(position, 0, handle);
  }

  function removeAreaVertex(areaId: number, variantId: number, index: number): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape || shape.path.length <= 3 || index < 0 || index >= shape.path.length) return;
    record();
    normalizeAreaShape(shape);
    shape.path.splice(index, 1);
    shape.handles.splice(index, 1);
  }

  /** Ручка Безье вершины контура. */
  function setAreaHandle(areaId: number, variantId: number, index: number, side: 'in' | 'out', offset: MapPoint): void {
    const shape = findAreaShape(areaId, variantId);
    if (!shape || index < 0 || index >= shape.path.length) return;
    record();
    normalizeAreaShape(shape);
    shape.handles[index] = {
      ...shape.handles[index],
      [side]: [offset[0], offset[1]],
    };
  }

  function updateLine(lineId: number, edit: MapLineEdit): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    line.color = edit.color;
    line.speed = clampSpeed(edit.speed);
    line.segments = segmentStyles(edit.segments, line.points.length, edit.color, line.speed);
    line.colorBlend = clampColorBlend(edit.colorBlend);
    line.decor = normalizeDecor(edit.decor);
    line.label = edit.label;
    if (edit.widthAll) {
      line.widths = line.points.map(() => clampWidth(edit.width));
    }
    normalizeLine(line);
  }

  function addLine(edit: MapLineEdit, variantId: number, points: MapPoint[]): number {
    record();
    const current = draft.value;
    if (!current) return 0;
    const width = clampWidth(edit.width);
    const line: MapLine = {
      id: nextTempId(),
      variantId,
      color: edit.color,
      speed: clampSpeed(edit.speed),
      points: copyPoints(points),
      widths: points.map(() => width),
      handles: autoHandles(points),
      segments: segmentStyles(edit.segments, points.length, edit.color, clampSpeed(edit.speed)),
      colorBlend: clampColorBlend(edit.colorBlend),
      decor: normalizeDecor(edit.decor),
      label: edit.label,
      isPresent: true,
      sortOrder: nextSortOrder(current.lines),
    };
    current.lines.push(line);

    return line.id;
  }

  /** Поднимает или опускает ленту в её варианте. */
  function moveLine(lineId: number, direction: -1 | 1): void {
    const current = draft.value;
    const line = findLine(lineId);
    if (!current || !line) return;
    const group = current.lines
      .filter((candidate) => candidate.variantId === line.variantId)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    const index = group.findIndex((candidate) => candidate.id === lineId);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= group.length) return;
    record();
    [group[index], group[target]] = [group[target], group[index]];
    group.forEach((candidate, order) => {
      candidate.sortOrder = order;
    });
  }

  /** Стиль одного сегмента ленты. */
  function setLineSegmentStyle(lineId: number, index: number, segment: MapLineSegment): void {
    const line = findLine(lineId);
    if (!line || index < 0 || index >= line.points.length - 1) return;
    record();
    normalizeLine(line);
    const base = segmentStyleOf(line);
    if (line.segments.length === 0) {
      line.segments = line.points.slice(1).map(() => ({ ...base }));
    }
    line.segments[index] = { speed: clampSpeed(segment.speed), color: segment.color };
  }

  /** Возвращает все сегменты ленты к цвету и скорости по умолчанию. */
  function resetLineSegments(lineId: number): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    line.segments = [];
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
    const value = clampWidth(width);
    line.widths = line.points.map(() => value);
  }

  /** Масштабирует все ширины ленты относительно перетаскиваемой точки. */
  function scaleLineWidths(lineId: number, index: number, width: number): void {
    const line = findLine(lineId);
    if (!line || index < 0 || index >= line.points.length) return;
    const base = line.widths[index] ?? 1;
    const target = clampWidth(width);
    if (base <= 0 || target === base) return;
    record();
    normalizeLine(line);
    const factor = target / base;
    line.widths = line.widths.map((value) => clampWidth(Math.round(value * factor)));
    line.widths[index] = target;
  }

  function insertLineVertex(
    lineId: number,
    index: number,
    point: MapPoint,
    width: number,
    handle: MapLineHandle,
  ): void {
    const line = findLine(lineId);
    if (!line) return;
    record();
    normalizeLine(line);
    const position = Math.min(Math.max(0, index + 1), line.points.length);
    if (line.segments.length > 0) {
      const source = Math.max(0, Math.min(position - 1, line.segments.length - 1));
      line.segments.splice(source, 0, { ...(line.segments[source] ?? segmentStyleOf(line)) });
    }
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
    if (line.segments.length > 0) {
      line.segments.splice(Math.min(index, line.segments.length - 1), 1);
    }
    line.points.splice(index, 1);
    line.widths.splice(index, 1);
    line.handles.splice(index, 1);
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
      variant.baseFile =
        edit.filePublicId === null
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
      baseFile:
        edit.filePublicId === null
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
    current.fillOpacity = meta.fillOpacity;
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
    const segments = Math.max(0, line.points.length - 1);
    if (line.segments.length > 0) {
      while (line.segments.length < segments) {
        line.segments.push({ ...(line.segments.at(-1) ?? segmentStyleOf(line)) });
      }
      line.segments = line.segments.slice(0, segments);
    }
  }

  function normalizeAreaShape(shape: MapAreaShape): void {
    shape.handles = shape.handles ?? [];
    while (shape.handles.length < shape.path.length) {
      shape.handles.push(zeroMapHandle());
    }
    shape.handles = shape.handles.slice(0, shape.path.length);
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
      shape = { variantId, path: [], handles: [], isPresent: true };
      area.shapes.push(shape);
    }
    normalizeAreaShape(shape);

    return shape;
  }

  function findLine(lineId: number): MapLine | undefined {
    return draft.value?.lines.find((line) => line.id === lineId);
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
    moveArea,
    translateArea,
    setAreaPath,
    setAreaVertex,
    setAreaHandle,
    insertAreaVertex,
    removeAreaVertex,
    updateLine,
    addLine,
    removeLine,
    moveLine,
    setLineSegmentStyle,
    resetLineSegments,
    translateLine,
    setLinePoints,
    setLineVertex,
    setLineHandle,
    setLineWidth,
    setLineWidthAll,
    scaleLineWidths,
    smoothLine,
    insertLineVertex,
    removeLineVertex,
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
    fillOpacity: detail.fillOpacity,
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
      decor: normalizeAreaDecor(area.decor),
      shapes: area.shapes.map((shape) => {
        const path = clampPoints(shape.path, boundsFor(bounds, shape.variantId));
        const handles = (shape.handles ?? []).map((handle) => ({
          in: roundPoint(handle.in),
          out: roundPoint(handle.out),
        }));
        while (handles.length < path.length) handles.push({ in: [0, 0], out: [0, 0] });

        return { ...shape, path, handles: handles.slice(0, path.length) };
      }),
    })),
    lines: raw.lines.map((line) => lineForSave(line, boundsFor(bounds, line.variantId))),
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
    segments: segmentStyles(line.segments, points.length, line.color, line.speed),
    colorBlend: clampColorBlend(line.colorBlend),
    decor: normalizeDecor(line.decor),
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
    color: edit.color,
    decor: normalizeAreaDecor(edit.decor),
    speed: clampSpeed(edit.speed),
    label: edit.label,
    nodeId: edit.nodeId,
    sortOrder: edit.sortOrder,
  };
}

/** Базовый стиль ленты для новых сегментов. */
function segmentStyleOf(line: MapLine): MapLineSegment {
  return { speed: line.speed, color: line.color };
}

/** Стили сегментов: пусто или ровно по числу сегментов. */
function segmentStyles(
  styles: MapLineSegment[] | undefined,
  points: number,
  color: string,
  speed: number,
): MapLineSegment[] {
  const expected = Math.max(0, points - 1);
  const source = styles ?? [];
  if (source.length === 0) return [];

  return Array.from({ length: expected }, (_, index) => {
    const style = source[index] ?? source.at(-1);

    return style ? { speed: clampSpeed(style.speed), color: style.color ?? color } : { speed, color };
  });
}

function clampSpeed(value: number): number {
  if (!Number.isFinite(value)) return 5000;

  return Math.min(10000, Math.max(0, Math.round(value)));
}

function clampWidth(value: number): number {
  if (!Number.isFinite(value)) return 1;

  return Math.min(20000, Math.max(1, Math.round(value)));
}

function clampColorBlend(value: number | undefined): number {
  if (value === undefined || !Number.isFinite(value)) return MAP_LINE_DEFAULT_COLOR_BLEND;

  return Math.min(1000, Math.max(0, Math.round(value)));
}

function clampRange(value: number, min: number, max: number, fallback: number): number {
  if (!Number.isFinite(value)) return fallback;

  return Math.min(max, Math.max(min, Math.round(value)));
}

function normalizeDecor(decor: MapLineDecor | null | undefined): MapLineDecor {
  const result: MapLineDecor = {};
  if (decor?.fillOpacity !== undefined) {
    result.fillOpacity = clampRange(decor.fillOpacity, 0, 100, MAP_FILL_OPACITY.default);
  }
  if (decor?.depth !== undefined && decor.depth > 0) {
    result.depth = clampRange(decor.depth, 0, 100, 0);
  }

  const flow = decor?.flow;
  if (!flow?.enabled) return result;
  result.flow = {
    enabled: true,
    speed: clampRange(flow.speed, 1, 600, MAP_LINE_FLOW_DEFAULTS.speed),
    density: clampRange(flow.density, 0, 100, MAP_LINE_FLOW_DEFAULTS.density),
    opacity: clampRange(flow.opacity, 0, 100, MAP_LINE_FLOW_DEFAULTS.opacity),
    color: /^#[0-9a-fA-F]{6}$/.test(flow.color) ? flow.color : MAP_LINE_FLOW_DEFAULTS.color,
    direction: flow.direction === -1 ? -1 : 1,
    seed: clampRange(flow.seed, 0, 1000000, MAP_LINE_FLOW_DEFAULTS.seed),
  };

  return result;
}

function normalizeAreaDecor(decor: MapAreaDecor | null | undefined): MapAreaDecor {
  if (decor?.fillOpacity === undefined) return {};

  return { fillOpacity: clampRange(decor.fillOpacity, 0, 100, MAP_FILL_OPACITY.default) };
}

function nextSortOrder(items: { sortOrder: number }[]): number {
  if (items.length === 0) return 0;

  return Math.max(...items.map((item) => item.sortOrder)) + 1;
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
