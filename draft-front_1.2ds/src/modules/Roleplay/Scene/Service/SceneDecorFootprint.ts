import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneFootprint } from '@/modules/Roleplay/Scene/Dto/SceneFootprint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';

/** Мировое основание или силуэт: форма уже перенесена и повёрнута. */
export type SceneWorldFootprint =
  { shape: 'circle'; center: ScenePoint; radius: number } | { shape: 'polygon'; points: ScenePoint[] };

/** Минимум для расчёта: фигура и свойства. */
type FootprintElement = Pick<SceneElement, 'shape' | 'props'>;

/**
 * Основание точечного декора в мировых координатах.
 *
 * Новое поле `props.footprint` — круг или полигон в единицах сцены, локально
 * от якоря. Старые сцены поля не имеют: для них остаётся прежний круг, доля
 * ширины спрайта. Прозрачные поля картинки в новую модель не попадают.
 */
export function sceneDecorFootprint(element: FootprintElement, resolution: number): SceneWorldFootprint | null {
  if (element.shape.type !== 'point') {
    return null;
  }

  const center: ScenePoint = [element.shape.x, element.shape.y];
  const footprint = element.props.footprint;
  if (footprint !== undefined && footprint !== null) {
    return footprintWorld(footprint, center, sceneDecorRotation(element));
  }

  const size = spriteSize(element, resolution);
  const radius = size * ((element.props.baseRadius ?? SCENE_SURFACE.decorBaseRadius) / 100);

  return radius > 0 ? { shape: 'circle', center, radius } : null;
}

/**
 * Силуэт точечного декора в мировых координатах и его низ.
 *
 * `bottom` возвращается в единицах сцены: у старой модели это доля ширины
 * спрайта, у новой — явное поле. Сетка поверхности переводит его в ипари.
 */
export function sceneDecorSilhouette(
  element: FootprintElement,
  resolution: number,
): { area: SceneWorldFootprint; bottom: number } | null {
  if (element.shape.type !== 'point') {
    return null;
  }

  const center: ScenePoint = [element.shape.x, element.shape.y];
  const silhouette = element.props.silhouette;
  if (silhouette !== undefined && silhouette !== null) {
    const area = footprintWorld(silhouette, center, sceneDecorRotation(element));

    return area === null ? null : { area, bottom: Math.max(0, silhouette.bottom) };
  }

  const size = spriteSize(element, resolution);
  const radius = size * ((element.props.sightRadius ?? SCENE_SURFACE.decorSightRadius) / 100);
  if (radius <= 0) {
    return null;
  }

  return {
    area: { shape: 'circle', center, radius },
    bottom: size * ((element.props.sightBottom ?? SCENE_SURFACE.decorSightBottom) / 100),
  };
}

/**
 * Основание и силуэт по умолчанию для карточки и шаблона.
 *
 * Карточка показывает те же числа, что видит расчёт: явное поле, иначе —
 * старую долю спрайта, переведённую в единицы сцены.
 */
export function sceneDecorFootprintDefaults(
  element: FootprintElement,
  resolution: number,
): { footprint: SceneFootprint; silhouette: SceneFootprint & { bottom: number } } {
  const size = spriteSize(element, resolution);
  const footprint = element.props.footprint ?? {
    shape: 'circle' as const,
    radius: Math.round(size * ((element.props.baseRadius ?? SCENE_SURFACE.decorBaseRadius) / 100)),
  };
  const silhouette = element.props.silhouette ?? {
    shape: 'circle' as const,
    radius: Math.round(size * ((element.props.sightRadius ?? SCENE_SURFACE.decorSightRadius) / 100)),
    bottom: Math.round(size * ((element.props.sightBottom ?? SCENE_SURFACE.decorSightBottom) / 100)),
  };

  return { footprint, silhouette };
}

/** Ширина спрайта в единицах сцены: `scale` — процент от разрешения. */
export function spriteSize(element: Pick<SceneElement, 'props'>, resolution: number): number {
  return Math.max(1, resolution) * (Math.max(1, element.props.scale ?? 100) / 100);
}

/** Поворот декора в 0–359: у декора `rotation` — это поворот спрайта. */
export function sceneDecorRotation(element: Pick<SceneElement, 'props'>): number {
  const rotation = element.props.rotation;
  if (typeof rotation !== 'number' || !Number.isFinite(rotation)) {
    return 0;
  }

  return ((Math.round(rotation) % 360) + 360) % 360;
}

/** Локальная форма в мировых координатах. */
function footprintWorld(footprint: SceneFootprint, center: ScenePoint, rotation: number): SceneWorldFootprint | null {
  if (footprint.shape === 'circle') {
    return footprint.radius > 0 ? { shape: 'circle', center, radius: footprint.radius } : null;
  }

  if (footprint.points.length < 3) {
    return null;
  }

  const radians = (rotation * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);

  return {
    shape: 'polygon',
    points: footprint.points.map((point): ScenePoint => [
      center[0] + point[0] * cosine - point[1] * sine,
      center[1] + point[0] * sine + point[1] * cosine,
    ]),
  };
}
