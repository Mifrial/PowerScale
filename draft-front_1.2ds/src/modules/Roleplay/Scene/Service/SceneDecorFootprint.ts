import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneFootprint } from '@/modules/Roleplay/Scene/Dto/SceneFootprint';
import type { SceneHandle } from '@/modules/Roleplay/Scene/Dto/SceneHandle';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';

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
 * Явное поле `props.silhouette` — канон. Без него зона непрозрачности
 * совпадает с основанием: мастер уже нарисовал видимую массу объекта, и тень
 * должна начинаться от неё, а не от круга, посаженного на якорь. Старые сцены
 * без обоих полей живут на долях ширины спрайта.
 *
 * `bottom` возвращается в единицах сцены: у новой модели это явное поле, у
 * старой — доля ширины спрайта. Сетка поверхности переводит его в ипари.
 */
export function sceneDecorSilhouette(
  element: FootprintElement,
  resolution: number,
): { area: SceneWorldFootprint; bottom: number } | null {
  if (element.shape.type !== 'point') {
    return null;
  }

  const center: ScenePoint = [element.shape.x, element.shape.y];
  const rotation = sceneDecorRotation(element);
  const zone = sceneSilhouetteZone(element.props);
  if (zone !== null) {
    const area = footprintWorld(zone.shape, center, rotation);

    return area === null ? null : { area, bottom: Math.max(0, zone.bottom) };
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
 * Зона непрозрачности: явный силуэт или, по умолчанию, основание.
 *
 * `null` — новых полей нет, остаются старые проценты ширины спрайта.
 */
function sceneSilhouetteZone(props: FootprintElement['props']): { shape: SceneFootprint; bottom: number } | null {
  if (props.silhouette !== undefined && props.silhouette !== null) {
    const { bottom, ...shape } = props.silhouette;

    return { shape, bottom };
  }

  if (props.footprint !== undefined && props.footprint !== null) {
    // Видимая масса уже обведена основанием: тень начинается от земли.
    return { shape: props.footprint, bottom: 0 };
  }

  return null;
}

/**
 * Мировая фигура основания для редактора вершин.
 *
 * Круг не имеет вершин, поэтому возвращается только полигон. Ручки Безье —
 * смещения от точек, их достаточно повернуть.
 */
export function sceneFootprintShape(element: FootprintElement, _resolution: number): SceneShape | null {
  if (element.shape.type !== 'point') {
    return null;
  }

  const footprint = element.props.footprint;
  if (footprint === undefined || footprint === null || footprint.shape !== 'polygon') {
    return null;
  }

  return zoneWorldShape([element.shape.x, element.shape.y], sceneDecorRotation(element), footprint);
}

/**
 * Мировая фигура зоны непрозрачности для редактора вершин.
 *
 * Та же механика, что у основания: явный силуэт или, по умолчанию, основание.
 * Круг и старые проценты вершин не имеют.
 */
export function sceneSilhouetteShape(element: FootprintElement, _resolution: number): SceneShape | null {
  if (element.shape.type !== 'point') {
    return null;
  }

  const zone = sceneSilhouetteZone(element.props);
  if (zone === null || zone.shape.shape !== 'polygon') {
    return null;
  }

  return zoneWorldShape([element.shape.x, element.shape.y], sceneDecorRotation(element), zone.shape);
}

/** Полигон зоны в мировых координатах: точки и ручки повёрнуты, якорь и смещение учтены. */
function zoneWorldShape(center: ScenePoint, rotation: number, zone: SceneFootprint & { shape: 'polygon' }): SceneShape {
  const origin = footprintOrigin(zone, center, rotation);

  return {
    type: 'polygon',
    points: rawWorldPoints(zone, origin, rotation),
    handles: zone.handles?.map((handle) => rotateHandle(handle, rotation)),
  };
}

/**
 * Мировая точка в локальную точку основания.
 *
 * Обратный поворот, сдвиг от якоря и смещение самой формы: так перетаскивание
 * вершины на карте попадает в те же числа, что хранит элемент.
 */
export function sceneFootprintLocalPoint(element: FootprintElement, point: ScenePoint): ScenePoint {
  if (element.shape.type !== 'point') {
    return [Math.round(point[0]), Math.round(point[1])];
  }

  return zoneLocalPoint(
    [element.shape.x, element.shape.y],
    sceneDecorRotation(element),
    point,
    element.props.footprint?.offset,
  );
}

/** Мировая точка в локальную точку зоны непрозрачности. */
export function sceneSilhouetteLocalPoint(element: FootprintElement, point: ScenePoint): ScenePoint {
  if (element.shape.type !== 'point') {
    return [Math.round(point[0]), Math.round(point[1])];
  }

  const zone = sceneSilhouetteZone(element.props);

  return zoneLocalPoint([element.shape.x, element.shape.y], sceneDecorRotation(element), point, zone?.shape.offset);
}

/** Обратный поворот точки к локальным координатам формы со смещением. */
function zoneLocalPoint(center: ScenePoint, rotation: number, point: ScenePoint, offset?: ScenePoint): ScenePoint {
  const radians = (rotation * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);
  const dx = point[0] - center[0];
  const dy = point[1] - center[1];
  const shift = offset ?? ([0, 0] as ScenePoint);

  return [Math.round(dx * cosine + dy * sine - shift[0]), Math.round(-dx * sine + dy * cosine - shift[1])];
}

/** Мировое смещение ручки в локальное: поворот без переноса. */
export function sceneFootprintLocalOffset(element: FootprintElement, offset: ScenePoint): ScenePoint {
  const radians = (sceneDecorRotation(element) * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);

  return [Math.round(offset[0] * cosine + offset[1] * sine), Math.round(-offset[0] * sine + offset[1] * cosine)];
}

/**
 * Основание и силуэт по умолчанию для карточки и шаблона.
 *
 * Карточка показывает те же числа, что видит расчёт: явное поле, иначе
 * основание, а у старой модели — доля ширины спрайта в единицах сцены.
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
  const zone = sceneSilhouetteZone(element.props);
  const silhouette =
    zone !== null
      ? { ...zone.shape, bottom: zone.bottom }
      : {
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
  const origin = footprintOrigin(footprint, center, rotation);

  if (footprint.shape === 'circle') {
    return footprint.radius > 0 ? { shape: 'circle', center: origin, radius: footprint.radius } : null;
  }

  if (footprint.points.length < 3) {
    return null;
  }

  const points = rawWorldPoints(footprint, origin, rotation);
  const handles = footprint.handles?.map((handle) => rotateHandle(handle, rotation)) ?? [];
  // Площадь и маршрут идут по кривой, а не по хордам: иначе безье-основание
  // блокирует не то, что нарисовано.
  const outline = sceneCurveService.flattenContour(points, handles);

  return outline.length >= 3 ? { shape: 'polygon', points: outline } : null;
}

/** Начало локальной формы в мировых координатах: якорь плюс смещение. */
function footprintOrigin(footprint: SceneFootprint, center: ScenePoint, rotation: number): ScenePoint {
  const offset = rotatePoint(footprint.offset ?? ([0, 0] as ScenePoint), rotation);

  return [center[0] + offset[0], center[1] + offset[1]];
}

/** Сырые мировые точки формы, без разворота кривых. */
function rawWorldPoints(footprint: SceneFootprint, origin: ScenePoint, rotation: number): ScenePoint[] {
  if (footprint.shape === 'circle') {
    return [];
  }

  return footprint.points.map((point): ScenePoint => {
    const rotated = rotatePoint(point, rotation);

    return [origin[0] + rotated[0], origin[1] + rotated[1]];
  });
}

/** Поворот вектора на угол в градусах. */
function rotatePoint(point: ScenePoint, degrees: number): ScenePoint {
  const radians = (degrees * Math.PI) / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);

  return [point[0] * cosine - point[1] * sine, point[0] * sine + point[1] * cosine];
}

/** Поворот ручки Безье: обе точки — смещения. */
function rotateHandle(handle: SceneHandle, degrees: number): SceneHandle {
  return {
    in: rotatePoint(handle.in, degrees),
    out: rotatePoint(handle.out, degrees),
  };
}

/**
 * Масштабирует форму основания: разрешение поля, шаблон объекта, rescale.
 *
 * Точки и ручки локальные и могут быть отрицательными, поэтому округление —
 * единственная нормализация; ноль и отрицательный радиус недопустимы.
 */
export function scaleSceneFootprint(footprint: SceneFootprint, factor: number): SceneFootprint {
  if (factor === 1) {
    return footprint;
  }

  const offset =
    footprint.offset === undefined
      ? {}
      : {
          offset: [Math.round(footprint.offset[0] * factor), Math.round(footprint.offset[1] * factor)] as ScenePoint,
        };

  if (footprint.shape === 'circle') {
    return {
      shape: 'circle',
      radius: Math.min(100000, Math.max(1, Math.round(footprint.radius * factor))),
      ...offset,
    };
  }

  const handles = footprint.handles?.map((handle): SceneHandle => ({
    in: [Math.round(handle.in[0] * factor), Math.round(handle.in[1] * factor)],
    out: [Math.round(handle.out[0] * factor), Math.round(handle.out[1] * factor)],
  }));

  return {
    shape: 'polygon',
    points: footprint.points.map((point): ScenePoint => [Math.round(point[0] * factor), Math.round(point[1] * factor)]),
    ...(handles === undefined ? {} : { handles }),
    ...offset,
  };
}
