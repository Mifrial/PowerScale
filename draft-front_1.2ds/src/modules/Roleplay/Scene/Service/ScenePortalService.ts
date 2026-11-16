import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';

/** Проём, ведущий на другое поле. */
export interface ScenePortal {
  opening: SceneElement;
  targetBoardId: number;
  center: ScenePoint;
}

/** Область родителя, которую покрывает дочернее поле. */
export interface ScenePortalFootprint {
  x: number;
  y: number;
  w: number;
  h: number;
}

/**
 * Переходы между полями через проёмы.
 *
 * Проём с `targetBoardId` — дверь, лестница или телепорт на другое поле.
 * Координаты полей разные, поэтому точка переносится через пятно вложения:
 * дочернее поле целиком лежит на области родителя, и масштаб выводится из
 * границ поля и пятна. Пара проёма ищется по обратной ссылке: на целевом поле
 * это проём, ведущий назад.
 */
export class ScenePortalService {
  /** Проём с целевым полем под точкой; ближайший, если их несколько. */
  portalAt(
    elements: readonly SceneElement[],
    boardId: number,
    point: ScenePoint,
    tolerance: number,
  ): ScenePortal | null {
    let best: ScenePortal | null = null;
    let bestDistance = Number.POSITIVE_INFINITY;
    for (const element of elements) {
      if (element.boardId !== boardId || element.kind !== 'opening' || element.targetBoardId === null) {
        continue;
      }
      if (!sceneGeometryService.contains(element.shape, point, tolerance, 0)) {
        continue;
      }

      const center = this.center(element);
      const distance = Math.hypot(center[0] - point[0], center[1] - point[1]);
      if (distance < bestDistance) {
        best = { opening: element, targetBoardId: element.targetBoardId, center };
        bestDistance = distance;
      }
    }

    return best;
  }

  /** Парный проём на целевом поле: ведёт назад и ближе всего к точке. */
  paired(
    elements: readonly SceneElement[],
    boardId: number,
    sourceBoardId: number,
    near: ScenePoint,
  ): SceneElement | null {
    let best: SceneElement | null = null;
    let bestDistance = Number.POSITIVE_INFINITY;
    for (const element of elements) {
      if (element.boardId !== boardId || element.kind !== 'opening' || element.targetBoardId !== sourceBoardId) {
        continue;
      }

      const center = this.center(element);
      const distance = Math.hypot(center[0] - near[0], center[1] - near[1]);
      if (distance < bestDistance) {
        best = element;
        bestDistance = distance;
      }
    }

    return best;
  }

  /** Центр проёма: середина его полосы. */
  center(element: SceneElement): ScenePoint {
    return sceneGeometryService.center(element.shape, 0);
  }

  /** Прямоугольник пятна вложения; у полигона — габарит. */
  footprint(shape: SceneShape): ScenePortalFootprint | null {
    if (shape.type === 'rect') {
      return { x: shape.x, y: shape.y, w: shape.w, h: shape.h };
    }

    if (shape.type !== 'polygon' && shape.type !== 'wall') {
      return null;
    }

    if (shape.points.length === 0) {
      return null;
    }

    let minX = Number.POSITIVE_INFINITY;
    let minY = Number.POSITIVE_INFINITY;
    let maxX = Number.NEGATIVE_INFINITY;
    let maxY = Number.NEGATIVE_INFINITY;
    for (const point of shape.points) {
      minX = Math.min(minX, point[0]);
      minY = Math.min(minY, point[1]);
      maxX = Math.max(maxX, point[0]);
      maxY = Math.max(maxY, point[1]);
    }

    return { x: minX, y: minY, w: maxX - minX, h: maxY - minY };
  }

  /** Точка родителя → координаты дочернего поля. */
  toChild(point: ScenePoint, footprint: ScenePortalFootprint, child: SceneBoard): ScenePoint {
    if (footprint.w <= 0 || footprint.h <= 0) {
      return point;
    }

    return [
      ((point[0] - footprint.x) * child.boundWidth) / footprint.w,
      ((point[1] - footprint.y) * child.boundHeight) / footprint.h,
    ];
  }

  /** Точка дочернего поля → координаты родителя. */
  toParent(point: ScenePoint, footprint: ScenePortalFootprint, child: SceneBoard): ScenePoint {
    if (child.boundWidth <= 0 || child.boundHeight <= 0) {
      return point;
    }

    return [
      footprint.x + (point[0] * footprint.w) / child.boundWidth,
      footprint.y + (point[1] * footprint.h) / child.boundHeight,
    ];
  }

  /** Вложение между двумя полями; `null` — поля не связаны. */
  enclosureOf(enclosures: readonly SceneEnclosure[], parentId: number, childId: number): SceneEnclosure | null {
    return (
      enclosures.find((enclosure) => enclosure.parentBoardId === parentId && enclosure.childBoardId === childId) ?? null
    );
  }
}
