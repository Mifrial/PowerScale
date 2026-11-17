import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_ROOF } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ROOF';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { isWalkableRoof, sceneRampSlope, sceneSurfaceElevation } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

/** Проём в проходимой крыше: марш, который на неё выходит. */
export interface SceneRoofHatch {
  /** Крыша, которую режет проём. */
  roofId: number;
  /** Марш, который выходит на крышу. */
  stairsId: number;
  /** Полигон проёма в координатах поля. */
  polygon: ScenePoint[];
}

/**
 * Проёмы в проходимых крышах.
 *
 * Крыша режется не по всему пятну марша, а по его верхней части: лестница
 * внутри дома уходит под крышу, и проём остаётся люком, а не щелью во всю
 * длину. Глубина люка — `SCENE_ROOF.hatchDepthIpari`, но не больше марша.
 *
 * @param elements Элементы поля.
 * @param resolution Разрешение сцены, единицы на ипари.
 *
 * @returns Проёмы с полигонами в координатах поля.
 */
export function sceneRoofHatches(elements: readonly SceneElement[], resolution: number): SceneRoofHatch[] {
  const base = Math.max(1, resolution);
  const step = base * SCENE_NAVIGATION.stepIpari;
  const depth = base * SCENE_ROOF.hatchDepthIpari;
  const roofs = elements.filter((element) => isWalkableRoof(element));
  if (roofs.length === 0) {
    return [];
  }

  const hatches: SceneRoofHatch[] = [];
  for (const stairs of elements) {
    if (stairs.kind !== 'support' || stairs.props.kind !== 'stairs' || stairs.shape.type !== 'rect') {
      continue;
    }

    const slope = sceneRampSlope(stairs, elements, resolution);
    if (slope === null) {
      continue;
    }

    const top = sceneSurfaceElevation(stairs);
    const roof = roofs.find(
      (candidate) =>
        candidate.boardId === stairs.boardId &&
        Math.abs(sceneSurfaceElevation(candidate) - top) <= step &&
        sceneGeometryService.contains(candidate.shape, slope.to, 0, sceneRotation(candidate)),
    );
    if (roof === undefined) {
      continue;
    }

    const polygon = hatchPolygon(stairs, slope.from, slope.to, depth);
    if (polygon !== null) {
      hatches.push({ roofId: roof.id, stairsId: stairs.id, polygon });
    }
  }

  return hatches;
}

/**
 * Верхняя полоса марша: прямоугольник у высокого торца.
 *
 * Полоса считается по проекции углов на ось марша, поэтому работает и у
 * повёрнутой лестницы: проём остаётся на высоком конце, а не на оси поля.
 */
function hatchPolygon(stairs: SceneElement, from: ScenePoint, to: ScenePoint, depth: number): ScenePoint[] | null {
  const length = Math.hypot(to[0] - from[0], to[1] - from[1]);
  if (length <= 0) {
    return null;
  }

  const axis: ScenePoint = [(to[0] - from[0]) / length, (to[1] - from[1]) / length];
  const project = (point: ScenePoint): number => point[0] * axis[0] + point[1] * axis[1];
  const corners = sceneGeometryService.points(stairs.shape, sceneRotation(stairs));
  const projections = corners.map(project);
  const high = Math.max(...projections);
  const low = Math.min(...projections);
  const cap = Math.max(low, high - depth);
  const back = high - cap;
  const top: ScenePoint[] = [];
  const inner: ScenePoint[] = [];
  for (let index = 0; index < corners.length; index += 1) {
    if (projections[index] < high - 0.01) {
      continue;
    }

    top.push(corners[index]);
    inner.push([corners[index][0] - axis[0] * back, corners[index][1] - axis[1] * back]);
  }
  if (top.length < 2 || back <= 0) {
    return null;
  }

  return [top[0], top[1], inner[1], inner[0]];
}
