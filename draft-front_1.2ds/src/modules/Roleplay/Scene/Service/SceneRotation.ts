import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';

/** Минимум, который нужен повороту: вид, фигура и свойства. */
type RotatableElement = Pick<SceneElement, 'kind' | 'shape' | 'props'>;

/**
 * Геометрический поворот опоры в градусах.
 *
 * Поворачиваются только прямоугольные опоры: мост, съезд, настил. У декора
 * `rotation` — это поворот спрайта, у остальных элементов геометрия осевая.
 * Угол нормализуется в 0–359, чтобы сравнения и кэши не зависели от знака.
 */
export function sceneRotation(element: RotatableElement): number {
  if (element.kind !== 'support' || element.shape.type !== 'rect') {
    return 0;
  }

  const rotation = element.props.rotation;
  if (typeof rotation !== 'number' || !Number.isFinite(rotation)) {
    return 0;
  }

  return ((Math.round(rotation) % 360) + 360) % 360;
}
