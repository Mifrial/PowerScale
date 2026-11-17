import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneRampSlope } from '@/modules/Roleplay/Scene/Dto/SceneRampSlope';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import {
  sceneDeckBaseElevation,
  sceneDeckThickness,
  sceneRampSlope,
  sceneSurfaceElevation,
  sceneSurfaceUnder,
  type SceneHeightElement,
} from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

/** Прямоугольник настила. */
type DeckRect = Extract<SceneShape, { type: 'rect' }>;

/**
 * Рамка настила: прямоугольник и поворот вокруг его центра.
 *
 * Поворот общий для рендера и навигации. Перила и торцы считаются в локальной
 * рамке: до поворота настил лежит осями по x и y, длинная сторона — вдоль
 * локального x, если `w >= h`, иначе вдоль локального y.
 */
export interface SceneDeckFrame {
  shape: DeckRect;
  rotation: number;
}

/** Рамка настила по элементу; `null` — элемент не прямоугольный. */
export function sceneDeckFrame(element: Pick<SceneElement, 'kind' | 'shape' | 'props'>): SceneDeckFrame | null {
  if (element.shape.type !== 'rect') {
    return null;
  }

  return { shape: element.shape, rotation: sceneRotation(element) };
}

/** Ширина полосы перил в единицах сцены: та же, что рисует `SceneBridgeService`. */
export function sceneDeckRailWidth(frame: SceneDeckFrame): number {
  const short = Math.min(frame.shape.w, frame.shape.h);

  return Math.max(1, short * SCENE_BRIDGE.railWidth);
}

/** Высота перил над настилом в единицах сцены. */
export function sceneDeckRailHeight(resolution: number): number {
  return Math.max(1, resolution) * SCENE_BRIDGE.railHeightIpari;
}

/** Мировые координаты → локальная рамка настила, без поворота. */
export function sceneDeckLocalPoint(frame: SceneDeckFrame, point: ScenePoint): ScenePoint {
  if (frame.rotation === 0) {
    return point;
  }

  const center: ScenePoint = [frame.shape.x + frame.shape.w / 2, frame.shape.y + frame.shape.h / 2];

  return rotate(point, center, -frame.rotation);
}

/**
 * Точка лежит в полосе перил, локальные координаты от левого верхнего угла.
 *
 * Ожидается, что точка внутри настила: вне его проверка ничего не значит.
 */
export function sceneDeckRailAt(frame: SceneDeckFrame, point: ScenePoint, resolution: number, tolerance = 0): boolean {
  const local = sceneDeckLocalPoint(frame, point);
  const x = local[0] - frame.shape.x;
  const y = local[1] - frame.shape.y;
  const rail = sceneDeckRailWidth(frame);

  if (frame.shape.w >= frame.shape.h) {
    return y <= rail + tolerance || y >= frame.shape.h - rail - tolerance;
  }

  return x <= rail + tolerance || x >= frame.shape.w - rail - tolerance;
}

/**
 * Ребро — торец настила, то есть короткая сторона.
 *
 * Перила стоят по длинным сторонам, торцы открыты. Ребро классифицируется по
 * направлению, а не по положению: так правило работает и у настила, обрезанного
 * краем поля, и у повёрнутого настила.
 */
export function sceneDeckEndEdge(frame: SceneDeckFrame, a: ScenePoint, b: ScenePoint, tolerance = 0.2): boolean {
  const longAxis = ((frame.rotation + (frame.shape.w >= frame.shape.h ? 0 : 90)) * Math.PI) / 180;
  const edge = Math.atan2(b[1] - a[1], b[0] - a[0]);

  // Поперёк длинной оси: косинус разницы близок к нулю.
  return Math.abs(Math.cos(edge - longAxis)) <= tolerance;
}

/**
 * Полосы перил в мировых координатах: две длинные стороны, повёрнутые вокруг
 * центра настила.
 *
 * `pad` расширяет полосу наружу и внутрь: навигация вычитает расширенную
 * полосу из настила, чтобы тело не задевало перила. Сами полосы уровня перил
 * берутся без запаса — запас иначе накрыл бы землю за краем настила.
 */
export function sceneDeckRailPolygons(frame: SceneDeckFrame, resolution: number, pad = 0): ScenePoint[][] {
  const shape = frame.shape;
  const rail = sceneDeckRailWidth(frame);
  const bands: { x: number; y: number; w: number; h: number }[] = [];
  if (shape.w >= shape.h) {
    bands.push({ x: shape.x, y: shape.y - pad, w: shape.w, h: rail + pad });
    bands.push({ x: shape.x, y: shape.y + shape.h - rail - pad, w: shape.w, h: rail + pad });
  } else {
    bands.push({ x: shape.x - pad, y: shape.y, w: rail + pad, h: shape.h });
    bands.push({ x: shape.x + shape.w - rail - pad, y: shape.y, w: rail + pad, h: shape.h });
  }

  const center: ScenePoint = [shape.x + shape.w / 2, shape.y + shape.h / 2];

  return bands.map((band) =>
    (
      [
        [band.x, band.y],
        [band.x + band.w, band.y],
        [band.x + band.w, band.y + band.h],
        [band.x, band.y + band.h],
      ] as ScenePoint[]
    ).map((corner) => rotate(corner, center, frame.rotation)),
  );
}

/** Наклон перил: тот же съезд, поднятый на высоту перил. */
export function sceneDeckRailSlope(slope: SceneRampSlope, resolution: number): SceneRampSlope {
  const height = sceneDeckRailHeight(resolution);

  return { from: slope.from, to: slope.to, low: slope.low + height, high: slope.high + height };
}

/**
 * Доля длины съезда, под которой не пройти.
 *
 * Съезд — клин: низкий конец лежит на земле, высокий поднят. Просвет
 * появляется только там, где низ настила выше роста тела. Доля считается от
 * низкого конца; 0 — под съездом можно пройти везде, 1 — нигде.
 */
export function sceneRampBlockedShare(
  element: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): number {
  const slope = sceneRampSlope(element, elements, resolution);
  if (slope === null) {
    return 0;
  }

  const rise = slope.high - slope.low;
  if (rise <= 0) {
    return 0;
  }

  const base = Math.max(1, resolution);
  const required =
    sceneDeckBaseElevation(element, elements, resolution) +
    sceneDeckThickness(element, resolution) +
    base * SCENE_NAVIGATION.bodyHeightIpari;

  return Math.min(1, Math.max(0, (required - slope.low) / rise));
}

/**
 * Низкая часть съезда, под которой не пройти.
 *
 * Съезд — сплошной клин: у низкого конца он лежит на земле, у высокого под ним
 * есть просвет. Зона считается по длинной оси: пока низ настила ниже роста тела
 * над поверхностью, базовый слой под этой частью закрыт. Возвращается полигон в
 * мировых координатах или `null`, если под съездом пройти можно везде.
 */
export function sceneRampBaseBlocker(
  element: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
  pad = 0,
): ScenePoint[] | null {
  const frame = sceneDeckFrame(element);
  if (frame === null) {
    return null;
  }

  const blocked = sceneRampBlockedShare(element, elements, resolution);
  if (blocked <= 0) {
    return null;
  }

  const shape = frame.shape;
  const horizontal = shape.w >= shape.h;
  // Низкий конец — вход на съезд: его не расширяем, иначе запас отодвигает
  // тело от портала и маршрут идёт сквозь запретную зону. Запас нужен с боков
  // и со стороны высокого конца.
  const band = horizontal
    ? { x: shape.x, y: shape.y - pad, w: shape.w * blocked + pad, h: shape.h + pad * 2 }
    : { x: shape.x - pad, y: shape.y, w: shape.w + pad * 2, h: shape.h * blocked + pad };
  const center: ScenePoint = [shape.x + shape.w / 2, shape.y + shape.h / 2];

  return (
    [
      [band.x, band.y],
      [band.x + band.w, band.y],
      [band.x + band.w, band.y + band.h],
      [band.x, band.y + band.h],
    ] as ScenePoint[]
  ).map((corner) => rotate(corner, center, frame.rotation));
}

/**
 * Участки под настилом, где пройти нельзя.
 *
 * Настил может висеть над водой только серединой, а торцами лежать на берегу.
 * Тогда под ним есть просвет, но не везде: закрываем именно те полосы, где
 * низ настила ближе роста тела к поверхности под ним. Полосы считаются вдоль
 * длинной стороны и возвращаются полигонами в мировых координатах.
 *
 * Пустой список — под настилом пройти можно везде.
 */
export function sceneDeckBaseBlockers(
  element: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
  pad = 0,
): ScenePoint[][] {
  const frame = sceneDeckFrame(element);
  if (frame === null) {
    return [];
  }

  const shape = frame.shape;
  const horizontal = shape.w >= shape.h;
  const length = horizontal ? shape.w : shape.h;
  const center: ScenePoint = [shape.x + shape.w / 2, shape.y + shape.h / 2];
  const radians = (frame.rotation * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);
  const axis: ScenePoint = horizontal ? [cosine, sine] : [-sine, cosine];
  const underside = sceneSurfaceElevation(element) - sceneDeckThickness(element, resolution);
  const body = Math.max(1, resolution) * SCENE_NAVIGATION.bodyHeightIpari;
  // Шаг вдоль настила — половина ипари: берег и вода успевают разойтись.
  const step = Math.max(1, resolution / 2);
  const count = Math.max(1, Math.ceil(length / step));
  const bands: { from: number; to: number }[] = [];
  let start: number | null = null;

  for (let index = 0; index <= count; index += 1) {
    const offset = -length / 2 + (length * index) / count;
    const point: ScenePoint = [center[0] + axis[0] * offset, center[1] + axis[1] * offset];
    const blocked = underside - sceneSurfaceUnder(point, elements, resolution, element.id, underside) < body;
    if (blocked && start === null) {
      start = offset;
    }
    if (!blocked && start !== null) {
      bands.push({ from: start, to: offset });
      start = null;
    }
  }

  if (start !== null) {
    bands.push({ from: start, to: length / 2 });
  }

  return bands.map((band) => {
    const rect = horizontal
      ? { x: center[0] + band.from, y: shape.y - pad, w: band.to - band.from, h: shape.h + pad * 2 }
      : { x: shape.x - pad, y: center[1] + band.from, w: shape.w + pad * 2, h: band.to - band.from };

    return (
      [
        [rect.x, rect.y],
        [rect.x + rect.w, rect.y],
        [rect.x + rect.w, rect.y + rect.h],
        [rect.x, rect.y + rect.h],
      ] as ScenePoint[]
    ).map((corner) => rotate(corner, center, frame.rotation));
  });
}

/** Поворот точки вокруг центра на угол в градусах. */
function rotate(point: ScenePoint, center: ScenePoint, degrees: number): ScenePoint {
  if (degrees === 0) {
    return point;
  }

  const radians = (degrees * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);
  const dx = point[0] - center[0];
  const dy = point[1] - center[1];

  return [center[0] + dx * cosine - dy * sine, center[1] + dx * sine + dy * cosine];
}
