import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { SceneFootprint, SceneSilhouette } from '@/modules/Roleplay/Scene/Dto/SceneFootprint';
import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import {
  sceneDecorFootprintDefaults,
  sceneDecorRotation,
  scaleSceneFootprint,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';

/** Версия контракта шаблона: незнакомую версию не применяем. */
export const SCENE_OBJECT_TEMPLATE_VERSION = 1;

/**
 * Шаблон объекта в `asset_item.meta`.
 *
 * Это снимок физики декора, а не ссылка: постановка копирует числа в элемент,
 * поэтому правка шаблона не переписывает уже сохранённые карты. Формы записаны
 * в единицах сцены того разрешения, где объект сохраняли, — при постановке на
 * поле с другим разрешением они пересчитываются.
 */
export interface SceneObjectTemplate {
  version: typeof SCENE_OBJECT_TEMPLATE_VERSION;
  kind: 'decor';
  /** Разрешение сцены, в единицах которой записаны формы. */
  resolution: number;
  scale: number;
  footprint: SceneFootprint;
  silhouette: SceneSilhouette;
  speed: number;
  blocksVision: boolean;
  rotation: number;
}

/** Ключ шаблона в `meta`: библиотека общая, толкует данные потребитель. */
const META_KEY = 'sceneObject';

const MAX_SIZE = 100000;
const MAX_POINTS = 512;

/**
 * Снимок физики декора для сохранения в библиотеку.
 *
 * Основание и силуэт материализуются: у старого декора они заданы процентами
 * ширины спрайта, и шаблон должен хранить уже готовые единицы сцены.
 */
export function sceneObjectTemplateOf(
  element: Pick<SceneElement, 'shape' | 'props'>,
  resolution: number,
): SceneObjectTemplate | null {
  if (element.shape.type !== 'point') {
    return null;
  }

  const defaults = sceneDecorFootprintDefaults(element, resolution);

  return {
    version: SCENE_OBJECT_TEMPLATE_VERSION,
    kind: 'decor',
    resolution: Math.max(1, Math.round(resolution)),
    scale: clampInt(element.props.scale ?? 100, 1, MAX_SIZE, 100),
    footprint: defaults.footprint,
    silhouette: defaults.silhouette,
    speed: clampInt(element.props.speed ?? 0, 0, MAX_SIZE, 0),
    blocksVision: element.props.blocksVision !== false,
    rotation: sceneDecorRotation(element),
  };
}

/**
 * Свойства элемента из шаблона ассета.
 *
 * `meta` приходит из библиотеки и считается недоверенным: читаются только
 * известные ключи, числа зажимаются, незнакомая версия или форма отбрасывается
 * целиком — лучше поставить декор без физики, чем с битой.
 */
export function sceneObjectTemplateProps(meta: unknown, targetResolution: number): SceneElementProps | null {
  const template = parseSceneObjectTemplate(meta);
  if (template === null) {
    return null;
  }

  const factor = Math.max(1, targetResolution) / template.resolution;

  return {
    scale: template.scale,
    footprint: scaleSceneFootprint(template.footprint, factor),
    silhouette: {
      ...scaleSceneFootprint(template.silhouette, factor),
      bottom: scaleLength(template.silhouette.bottom, factor),
    },
    speed: template.speed,
    blocksVision: template.blocksVision,
    rotation: template.rotation,
  };
}

/** Шаблон из `meta` или null, если данных нет или они незнакомы. */
export function parseSceneObjectTemplate(meta: unknown): SceneObjectTemplate | null {
  if (!isRecord(meta)) {
    return null;
  }

  const value = meta[META_KEY];
  if (!isRecord(value)) {
    return null;
  }

  if (value.version !== SCENE_OBJECT_TEMPLATE_VERSION || value.kind !== 'decor') {
    return null;
  }

  const footprint = parseFootprint(value.footprint);
  const silhouette = parseSilhouette(value.silhouette);
  if (footprint === null || silhouette === null) {
    return null;
  }

  return {
    version: SCENE_OBJECT_TEMPLATE_VERSION,
    kind: 'decor',
    resolution: clampInt(value.resolution, 1, MAX_SIZE, 64),
    scale: clampInt(value.scale, 1, MAX_SIZE, 100),
    footprint,
    silhouette,
    speed: clampInt(value.speed, 0, MAX_SIZE, 0),
    blocksVision: value.blocksVision !== false,
    rotation: clampInt(value.rotation, 0, 359, 0),
  };
}

/** `meta` для сохранения шаблона в ассет. */
export function sceneObjectMeta(template: SceneObjectTemplate): Record<string, unknown> {
  return { [META_KEY]: template };
}

/** Основание из JSON шаблона. */
function parseFootprint(value: unknown): SceneFootprint | null {
  if (!isRecord(value)) {
    return null;
  }

  const offset = parseOffset(value.offset);

  if (value.shape === 'circle') {
    const radius = clampInt(value.radius, 1, MAX_SIZE, 0);
    if (radius <= 0) {
      return null;
    }

    return offset === null ? { shape: 'circle', radius } : { shape: 'circle', radius, offset };
  }

  if (value.shape !== 'polygon') {
    return null;
  }

  const points = parsePoints(value.points);
  if (points === null) {
    return null;
  }

  const handles = parseHandles(value.handles, points.length);

  return {
    shape: 'polygon',
    points,
    ...(handles === null ? {} : { handles }),
    ...(offset === null ? {} : { offset }),
  };
}

/** Силуэт из JSON шаблона: та же форма плюс низ над землёй. */
function parseSilhouette(value: unknown): SceneSilhouette | null {
  const footprint = parseFootprint(value);
  if (footprint === null || !isRecord(value)) {
    return null;
  }

  return { ...footprint, bottom: clampInt(value.bottom, 0, MAX_SIZE, 0) };
}

/** Список точек: минимум три, каждая пара целых. */
function parsePoints(value: unknown): ScenePoint[] | null {
  if (!Array.isArray(value) || value.length < 3 || value.length > MAX_POINTS) {
    return null;
  }

  const points: ScenePoint[] = [];
  for (const item of value) {
    const point = parsePoint(item);
    if (point === null) {
      return null;
    }

    points.push(point);
  }

  return points;
}

/** Ручки Безье: либо нет, либо по одной на вершину. */
function parseHandles(value: unknown, count: number): SceneHandle[] | null {
  if (value === undefined || value === null) {
    return null;
  }

  if (!Array.isArray(value) || value.length !== count) {
    return null;
  }

  const handles: SceneHandle[] = [];
  for (const item of value) {
    if (!isRecord(item)) {
      return null;
    }

    const inPoint = parsePoint(item.in);
    const outPoint = parsePoint(item.out);
    if (inPoint === null || outPoint === null) {
      return null;
    }

    handles.push({ in: inPoint, out: outPoint });
  }

  return handles;
}

/** Смещение формы от якоря. */
function parseOffset(value: unknown): ScenePoint | null {
  return value === undefined || value === null ? null : parsePoint(value);
}

/** Пара целых чисел в допуске. */
function parsePoint(value: unknown): ScenePoint | null {
  if (!Array.isArray(value) || value.length !== 2) {
    return null;
  }

  const x = finiteInt(value[0]);
  const y = finiteInt(value[1]);
  if (x === null || y === null) {
    return null;
  }

  return [clampInt(x, -MAX_SIZE, MAX_SIZE, 0), clampInt(y, -MAX_SIZE, MAX_SIZE, 0)];
}

/** Целое из числа или числовой строки; null — не число. */
function finiteInt(value: unknown): number | null {
  if (typeof value !== 'number' || !Number.isFinite(value)) {
    return null;
  }

  return Math.round(value);
}

/** Целое в границах; всё нечисловое заменяется запасным значением. */
function clampInt(value: unknown, min: number, max: number, fallback: number): number {
  const parsed = finiteInt(value);

  return parsed === null ? fallback : Math.min(max, Math.max(min, parsed));
}

/** Длина в единицах сцены: ноль допустим. */
function scaleLength(value: number, factor: number): number {
  return Math.min(MAX_SIZE, Math.max(0, Math.round(value * factor)));
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
