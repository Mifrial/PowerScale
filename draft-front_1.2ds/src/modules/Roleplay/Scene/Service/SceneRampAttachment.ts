import type { SceneElementProps } from '@/modules/Roleplay/Scene/Dto/SceneElementProps';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import {
  sceneDeckThickness,
  sceneSurfaceElevation,
  sceneSurfaceUnder,
  type SceneHeightElement,
} from '@/modules/Roleplay/Scene/Service/SceneHeight';

/** Прямоугольник съезда. */
type DeckRect = Extract<SceneShape, { type: 'rect' }>;

/** Торец настила: начало или конец длинной оси в локальной рамке. */
export type SceneDeckEnd = 'start' | 'end';

/** Сторона света, куда уходит съезд от торца. */
export type SceneCompass =
  'north' | 'north-east' | 'east' | 'south-east' | 'south' | 'south-west' | 'west' | 'north-west';

/** Готовый съезд к торцу настила. */
export interface SceneRampAttachment {
  /** Прямоугольник съезда в мировых координатах. */
  shape: DeckRect;
  /** Свойства съезда: высокий конец, толщина, скорость и поворот. */
  props: SceneElementProps;
  /** Длина съезда вдоль уклона, единицы сцены. */
  length: number;
  /** Ширина съезда, единицы сцены: короткая сторона настила. */
  width: number;
  /** Торец, к которому приставлен съезд. */
  end: SceneDeckEnd;
  /** Куда съезд уходит от настила. */
  direction: SceneCompass;
  /** Подъём съезда: от поверхности у торца до верха настила. */
  rise: number;
}

const COMPASS: readonly SceneCompass[] = [
  'north',
  'north-east',
  'east',
  'south-east',
  'south',
  'south-west',
  'west',
  'north-west',
];

/**
 * Сторона света по вектору в мировых координатах.
 *
 * Ось `y` на экране растёт вниз, поэтому север — это `-y`. Сектор — 45°:
 * «север» покрывает не только строгий `-y`, но и ближайшие румбы.
 */
export function sceneCompass(direction: ScenePoint): SceneCompass {
  const bearing = ((Math.atan2(direction[0], -direction[1]) * 180) / Math.PI + 360) % 360;

  return COMPASS[Math.round(bearing / 45) % 8];
}

/**
 * Съезд к торцу настила.
 *
 * Высокий конец съезда ставится ровно на торец моста, низкий уходит наружу по
 * длинной оси. Длина — 1:3 к подъёму, но не короче двух ипари: у низкого
 * настила съезд всё равно остаётся читаемым. Ширина равна короткой стороне
 * настила, толщина и скорость наследуются от него.
 *
 * `null` — торец не подходит: элемент не мост, не прямоугольный или лежит на
 * поверхности, так что поднимать некуда.
 */
export function sceneRampAttachment(
  deck: SceneHeightElement,
  elements: readonly SceneHeightElement[],
  resolution: number,
  end: SceneDeckEnd,
): SceneRampAttachment | null {
  if (deck.kind !== 'support' || deck.props.kind !== 'bridge' || deck.shape.type !== 'rect') {
    return null;
  }

  const shape = deck.shape;
  const rotation = sceneRotation(deck);
  const center = sceneGeometryService.center(shape, rotation);
  const horizontal = shape.w >= shape.h;
  const half = (horizontal ? shape.w : shape.h) / 2;
  const radians = (rotation * Math.PI) / 180;
  const axis: ScenePoint = horizontal
    ? [Math.cos(radians), Math.sin(radians)]
    : [-Math.sin(radians), Math.cos(radians)];
  const sign = end === 'end' ? 1 : -1;
  const edge: ScenePoint = [center[0] + axis[0] * half * sign, center[1] + axis[1] * half * sign];
  const elevation = sceneSurfaceElevation(deck);
  const base = sceneSurfaceUnder(edge, elements, resolution, deck.id, elevation);
  const rise = elevation - base;
  if (rise <= 0) {
    return null;
  }

  const width = Math.max(1, Math.min(shape.w, shape.h));
  const length = Math.max(
    Math.max(1, resolution) * SCENE_BRIDGE.rampMinLengthIpari,
    Math.round(rise * SCENE_BRIDGE.rampRunPerRise),
  );
  // Ось съезда идёт от низкого конца к высокому, то есть навстречу наружному
  // направлению торца: высокий конец остаётся на мосту.
  const rampRotation = normalizeAngle((Math.atan2(-axis[1] * sign, -axis[0] * sign) * 180) / Math.PI);
  // Центр — на полдлины наружу: высокий конец ложится ровно на торец.
  const rampCenter: ScenePoint = [edge[0] + axis[0] * sign * (length / 2), edge[1] + axis[1] * sign * (length / 2)];
  const direction: ScenePoint = [axis[0] * sign, axis[1] * sign];

  return {
    shape: {
      type: 'rect',
      x: round(rampCenter[0] - length / 2),
      y: round(rampCenter[1] - width / 2),
      w: length,
      h: width,
    },
    props: {
      kind: 'ramp',
      elevation: Math.round(elevation),
      height: Math.round(sceneDeckThickness(deck, resolution)),
      speed: deck.props.speed ?? SCENE_SURFACE.speeds.bridge,
      rotation: rampRotation,
    },
    length,
    width,
    end,
    direction: sceneCompass(direction),
    rise,
  };
}

/** Угол в 0–359, чтобы поворот не зависел от знака. */
function normalizeAngle(degrees: number): number {
  return ((Math.round(degrees) % 360) + 360) % 360;
}

/** Координата до целого: элементы сцены хранятся в целых единицах. */
function round(value: number): number {
  return Math.round(value);
}
