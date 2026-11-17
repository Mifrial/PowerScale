import type { SceneElementKind } from '@/modules/Roleplay/Scene/Enum/SceneElementKind';
import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRampSlope } from '@/modules/Roleplay/Scene/Dto/SceneRampSlope';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

/**
 * Всё, что нужно для расчёта высот: элемент сцены или элемент черновика.
 *
 * Помощники не зависят от `asset` и `sortOrder`, поэтому принимают только
 * геометрию, вид и свойства.
 */
export interface SceneHeightElement {
  id: number;
  kind: SceneElementKind;
  shape: SceneShape;
  props: SceneElementProps;
}

/** Доли длинной стороны настила, по которым ищется поверхность под ним. */
const DECK_SAMPLES: readonly number[] = [0.5, 0.25, 0.75];

/**
 * Настил: мост, съезд, лестница или проходимая крыша.
 *
 * Проходимая крыша — тот же настил, только накрывает дом: у неё есть верхняя
 * поверхность, зазор под ней и уровень в навигации.
 */
export function isSceneDeck(element: SceneHeightElement): boolean {
  return (
    element.kind === 'support' &&
    (element.props.kind === 'bridge' ||
      element.props.kind === 'ramp' ||
      element.props.kind === 'stairs' ||
      isWalkableRoof(element))
  );
}

/**
 * Наклонный марш: съезд или лестница.
 *
 * Оба садятся низким концом на поверхность, а высокий задаёт `elevation`.
 * Мост — ровный настил, у него наклона нет.
 */
export function isSceneRamp(element: SceneHeightElement): boolean {
  return element.kind === 'support' && (element.props.kind === 'ramp' || element.props.kind === 'stairs');
}

/**
 * Крыша — потолок, а не пол.
 *
 * Её нельзя считать поверхностью под ногами: иначе высота внутри дома стала бы
 * высотой крыши, а по самому накрытию пролёг бы маршрут. Исключение —
 * проходимая крыша.
 */
export function isSceneRoof(element: SceneHeightElement): boolean {
  return element.kind === 'support' && element.props.kind === 'roof';
}

/**
 * Проходимая крыша: накрытие, по которому ходят.
 *
 * Это по-прежнему `support` с `kind = 'roof'`, но с `props.walkable`. Она
 * ведёт себя как настил: верхняя поверхность на `elevation`, зазор под ней до
 * пола, отдельный уровень в навигации. Интерьер под крышей остаётся
 * проходимым — крыша не заменяет пол, а лежит над ним.
 */
export function isWalkableRoof(element: SceneHeightElement): boolean {
  return isSceneRoof(element) && element.props.walkable === true;
}

/**
 * Глубина воды, единицы сцены.
 *
 * `0` — вода вровень с берегом: так выглядят старые сцены, у которых поля нет.
 */
export function sceneWaterDepth(element: SceneHeightElement): number {
  const depth = element.props.depth;
  if (typeof depth !== 'number' || !Number.isFinite(depth)) {
    return 0;
  }

  return Math.max(0, depth);
}

/**
 * Высота верхней поверхности элемента над нулём поля, единицы сцены.
 *
 * Вода опущена на свою глубину (`props.depth`), остальные поверхности — на
 * `props.elevation`, по умолчанию на нуле поля.
 */
export function sceneSurfaceElevation(element: SceneHeightElement): number {
  const elevation = element.props.elevation;
  if (typeof elevation === 'number' && Number.isFinite(elevation)) {
    return elevation;
  }

  if (element.props.kind !== 'water') {
    return 0;
  }

  const depth = sceneWaterDepth(element);

  return depth > 0 ? -depth : 0;
}

/**
 * Толщина настила, единицы сцены.
 *
 * `props.height` у моста — вертикальный размер настила, а не высота объёма.
 * Без явного значения берётся пресет.
 */
export function sceneDeckThickness(element: SceneHeightElement, resolution: number): number {
  const height = element.props.height;
  if (typeof height === 'number' && Number.isFinite(height)) {
    return Math.max(0, height);
  }

  return Math.max(1, resolution) * SCENE_BRIDGE.thicknessIpari;
}

/**
 * Поверхность под точкой, единицы сцены.
 *
 * Слои выбираются по тому же порядку, что и в сетке поверхностей: опора
 * перекрывает террейн, вода — остальной террейн, а остров или настил поверх
 * воды — воду. Иначе река, врезанная в луг, не видна расчёту высот: луг лежит
 * на нуле, и зазор под мостом считался бы от берега, а не от воды. Настил над
 * водой, наоборот, лежит поверх неё: под ногами пол дома, а не вода под ним.
 * Вода может быть ниже нуля поля, поэтому нулём пользуемся только когда под
 * точкой нет ни одной поверхности.
 *
 * `below` — потолок поиска: поверхности на этой отметке и выше лежат не под
 * точкой, а рядом с ней. Проходимая крыша над лестницей не становится её
 * низким концом.
 */
export function sceneSurfaceUnder(
  point: ScenePoint,
  elements: readonly SceneHeightElement[],
  resolution: number,
  excludeId = -1,
  below = Number.POSITIVE_INFINITY,
): number {
  let support = Number.NEGATIVE_INFINITY;
  let water = Number.NEGATIVE_INFINITY;
  let deck = Number.NEGATIVE_INFINITY;
  let terrain = Number.NEGATIVE_INFINITY;
  for (const element of elements) {
    if (element.id === excludeId) {
      continue;
    }
    if (element.kind !== 'terrain' && element.kind !== 'support') {
      continue;
    }
    // Крыша висит над полом: поверхностью под ногами она не становится.
    // Проходимая — становится: она лежит на своей высоте как настил.
    if (isSceneRoof(element) && !isWalkableRoof(element)) {
      continue;
    }
    if (!sceneGeometryService.contains(element.shape, point, 0, sceneRotation(element))) {
      continue;
    }

    const elevation = sceneSurfaceElevation(element);
    // Поверхность не ниже самого настила — не «под» ним, а рядом или выше:
    // проходимая крыша над лестницей не становится её низким концом.
    if (elevation >= below) {
      continue;
    }
    if (element.kind === 'support') {
      support = Math.max(support, elevation);
    } else if (element.props.kind === 'water') {
      water = Math.max(water, elevation);
    } else if (element.props.overWater === true) {
      // Остров или настил лежит поверх воды: под ногами он, а не вода.
      deck = Math.max(deck, elevation);
    } else {
      terrain = Math.max(terrain, elevation);
    }
  }

  if (support > Number.NEGATIVE_INFINITY) {
    return support;
  }
  if (deck > Number.NEGATIVE_INFINITY) {
    return deck;
  }
  if (water > Number.NEGATIVE_INFINITY) {
    return water;
  }

  return terrain > Number.NEGATIVE_INFINITY ? terrain : 0;
}

/**
 * Самая высокая поверхность под настилом, единицы сцены.
 *
 * Настил может лежать над водой, берегом, дорогой или другим настилом.
 * Точки берутся по центру и вдоль длинной стороны: они остаются внутри
 * прямоугольника при любом повороте, в отличие от углов описанного прямоугольника.
 */
export function sceneDeckBaseElevation(
  deck: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): number {
  let base = Number.NEGATIVE_INFINITY;
  const top = sceneSurfaceElevation(deck);
  for (const point of sceneDeckSamples(deck)) {
    base = Math.max(base, sceneSurfaceUnder(point, elements, resolution, deck.id, top));
  }

  return Number.isFinite(base) ? base : 0;
}

/**
 * Самая низкая поверхность под настилом, единицы сцены.
 *
 * Мост через врезанное русло опирается торцами на берег, а серединой висит
 * над водой. Для прохода важна именно вода: зазор под настилом — это просвет
 * в самом низком месте, а не над берегом.
 */
export function sceneDeckLowestBaseElevation(
  deck: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): number {
  let base = Number.POSITIVE_INFINITY;
  const top = sceneSurfaceElevation(deck);
  for (const point of sceneDeckSamples(deck)) {
    base = Math.min(base, sceneSurfaceUnder(point, elements, resolution, deck.id, top));
  }

  return Number.isFinite(base) ? base : 0;
}

/** Точки вдоль длинной стороны настила: центр и две четверти. */
function sceneDeckSamples(deck: SceneHeightElement): ScenePoint[] {
  const rotation = sceneRotation(deck);
  const shape = deck.shape;
  const center = sceneGeometryService.center(shape, rotation);
  const half = shape.type === 'rect' ? (shape.w >= shape.h ? shape.w : shape.h) / 2 : 0;
  const horizontal = shape.type === 'rect' && shape.w >= shape.h;

  return DECK_SAMPLES.map((share) => {
    const offset = (share - 0.5) * half * 2;

    return horizontal ? [center[0] + offset, center[1]] : [center[0], center[1] + offset];
  });
}

/**
 * Наклон съезда.
 *
 * Съезд поднимается к дальнему концу по длинной стороне: низкий конец — там,
 * где начинается местная ось прямоугольника, высокий — напротив. Низкий конец
 * садится на поверхность под ним, высокий берётся из `props.elevation`.
 * Развернуть съезд можно поворотом на 180°.
 */
export function sceneRampSlope(
  element: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): SceneRampSlope | null {
  if (!isSceneRamp(element) || element.shape.type !== 'rect') {
    return null;
  }

  const shape = element.shape;
  const rotation = sceneRotation(element);
  const center = sceneGeometryService.center(shape, rotation);
  const horizontal = shape.w >= shape.h;
  const half = (horizontal ? shape.w : shape.h) / 2;
  const radians = (rotation * Math.PI) / 180;
  const axis: ScenePoint = horizontal
    ? [Math.cos(radians), Math.sin(radians)]
    : [-Math.sin(radians), Math.cos(radians)];
  const from: ScenePoint = [center[0] - axis[0] * half, center[1] - axis[1] * half];
  const to: ScenePoint = [center[0] + axis[0] * half, center[1] + axis[1] * half];
  const low = sceneSurfaceUnder(from, elements, resolution, element.id, sceneSurfaceElevation(element));
  const high = sceneSurfaceElevation(element);

  return high > low ? { from, to, low, high } : null;
}

/** Высота наклонной поверхности в точке: проекция на ось съезда. */
export function sceneSlopeElevation(slope: SceneRampSlope, point: ScenePoint): number {
  const dx = slope.to[0] - slope.from[0];
  const dy = slope.to[1] - slope.from[1];
  const length = Math.hypot(dx, dy);
  if (length <= 0) {
    return slope.high;
  }

  const t = Math.min(
    1,
    Math.max(0, ((point[0] - slope.from[0]) * dx + (point[1] - slope.from[1]) * dy) / (length * length)),
  );

  return slope.low + (slope.high - slope.low) * t;
}

/**
 * Зазор под настилом, единицы сцены.
 *
 * Считается от низа настила до самой высокой поверхности под ним. Значение
 * меньше роста тела означает, что пройти под настилом нельзя; отрицательное —
 * что настил лежит на поверхности.
 */
export function sceneDeckClearance(
  deck: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
): number {
  return (
    sceneSurfaceElevation(deck) -
    sceneDeckThickness(deck, resolution) -
    sceneDeckLowestBaseElevation(deck, elements, resolution)
  );
}
