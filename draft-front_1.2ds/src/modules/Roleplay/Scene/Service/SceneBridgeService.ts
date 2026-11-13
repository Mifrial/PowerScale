import { Graphics } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import { SCENE_BRIDGE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_BRIDGE';

/**
 * Мост: настил, доски и перила.
 *
 * Рисуется в мировых координатах прямоугольника элемента и живёт в слое декора
 * над водой, но под акторами: это пол, а не препятствие. Толщина линий
 * переводится из экранных пикселей в единицы сцены через зум.
 */
export class SceneBridgeService {
  /** Рисует мост; `null` — элемент не прямоугольный. */
  paint(element: SceneElement, resolution: number, zoom: number): Graphics | null {
    if (element.shape.type !== 'rect') {
      return null;
    }

    const { x, y, w, h } = element.shape;
    if (w <= 0 || h <= 0) {
      return null;
    }

    const base = Math.max(1, resolution);
    const short = Math.min(w, h);
    const horizontal = w >= h;
    const rail = Math.max(1, short * SCENE_BRIDGE.railWidth);
    const pad = Math.max(1, short * 0.04);
    const line = Math.max(1, 1.5 / Math.max(0.001, zoom));
    const planks = Math.max(1, Math.round((horizontal ? w : h) / (base * SCENE_BRIDGE.plankIpari)));

    const graphics = new Graphics();
    // Тёмная кромка под настилом: мост не сливается с водой.
    graphics.rect(x - pad, y - pad, w + pad * 2, h + pad * 2).fill({ color: SCENE_BRIDGE.edgeColor, alpha: 0.9 });
    graphics.rect(x, y, w, h).fill({ color: SCENE_BRIDGE.deckColor });

    // Доски вдоль короткой стороны: шаг по длинной.
    for (let index = 1; index < planks; index += 1) {
      const along = index / planks;
      if (horizontal) {
        const px = x + w * along;
        graphics.moveTo(px, y + rail).lineTo(px, y + h - rail);
      } else {
        const py = y + h * along;
        graphics.moveTo(x + rail, py).lineTo(x + w - rail, py);
      }
    }
    graphics.stroke({ color: SCENE_BRIDGE.plankColor, width: line, alpha: 0.65 });

    // Перила по длинным сторонам и столбики.
    if (horizontal) {
      graphics.rect(x, y, w, rail).fill({ color: SCENE_BRIDGE.railColor });
      graphics.rect(x, y + h - rail, w, rail).fill({ color: SCENE_BRIDGE.railColor });
    } else {
      graphics.rect(x, y, rail, h).fill({ color: SCENE_BRIDGE.railColor });
      graphics.rect(x + w - rail, y, rail, h).fill({ color: SCENE_BRIDGE.railColor });
    }

    const post = Math.max(2, short * SCENE_BRIDGE.postHeight);
    const posts = Math.max(2, Math.round(planks / 4));
    for (let index = 0; index <= posts; index += 1) {
      const along = index / posts;
      if (horizontal) {
        const px = x + w * along;
        graphics
          .rect(px - line, y - post * 0.35, line * 2, rail + post * 0.35)
          .rect(px - line, y + h - rail, line * 2, rail + post * 0.35);
      } else {
        const py = y + h * along;
        graphics
          .rect(x - post * 0.35, py - line, rail + post * 0.35, line * 2)
          .rect(x + w - rail, py - line, rail + post * 0.35, line * 2);
      }
    }
    graphics.fill({ color: SCENE_BRIDGE.railColor });

    return graphics;
  }
}
