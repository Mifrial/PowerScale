import { Texture } from 'pixi.js';
import type { Container } from 'pixi.js';
import { Sprite } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneTextureCache } from '@/modules/Roleplay/Scene/Service/SceneTextureCache';

/** Мягкая тень декора: 128×64, радиальный градиент. */
const SHADOW_WIDTH = 128;
const SHADOW_HEIGHT = 64;

/**
 * Декор: спрайт с якорем снизу по центру и мягкой тенью.
 *
 * Без ассета остаётся прежний маркер; битая текстура не роняет кадр.
 */
export class SceneDecorService {
  private shadow: Texture | null = null;

  constructor(private readonly textures: SceneTextureCache) {}

  /**
   * Рисует декор в слой.
   *
   * @returns true, если нарисован спрайт; false — нужен fallback-маркер.
   */
  paint(layer: Container, element: SceneElement, resolution: number): boolean {
    if (element.shape.type !== 'point' || element.asset === null) {
      return false;
    }

    const texture = this.textures.get(element.asset.publicId);
    if (texture === null) {
      return false;
    }

    const scale = Math.max(1, element.props.scale ?? 100) / 100;
    const size = Math.max(1, resolution) * scale;
    const ratio = texture.height / Math.max(1, texture.width);

    const shadow = new Sprite(this.shadowTexture());
    shadow.anchor.set(0.5, 0.5);
    shadow.position.set(element.shape.x, element.shape.y - size * 0.03);
    shadow.width = size * 0.78;
    shadow.height = size * 0.24;
    shadow.alpha = 0.32;
    layer.addChild(shadow);

    const sprite = new Sprite(texture);
    sprite.anchor.set(0.5, 1);
    sprite.position.set(element.shape.x, element.shape.y);
    sprite.width = size;
    sprite.height = size * ratio;
    sprite.rotation = ((element.props.rotation ?? 0) * Math.PI) / 180;
    layer.addChild(sprite);

    return true;
  }

  /** Высота якоря декора: нужна для Y-сортировки. */
  anchorY(element: SceneElement): number {
    return element.shape.type === 'point' ? element.shape.y : 0;
  }

  /** Освобождает процедурную тень. */
  destroy(): void {
    this.shadow?.destroy(true);
    this.shadow = null;
  }

  private shadowTexture(): Texture {
    if (this.shadow !== null) {
      return this.shadow;
    }

    const canvas = document.createElement('canvas');
    canvas.width = SHADOW_WIDTH;
    canvas.height = SHADOW_HEIGHT;
    const context = canvas.getContext('2d');
    if (context !== null) {
      context.translate(SHADOW_WIDTH / 2, SHADOW_HEIGHT / 2);
      context.scale(1, SHADOW_HEIGHT / SHADOW_WIDTH);
      const gradient = context.createRadialGradient(0, 0, 0, 0, 0, SHADOW_WIDTH / 2);
      gradient.addColorStop(0, 'rgba(15, 23, 42, 0.55)');
      gradient.addColorStop(0.55, 'rgba(15, 23, 42, 0.22)');
      gradient.addColorStop(1, 'rgba(15, 23, 42, 0)');
      context.fillStyle = gradient;
      context.fillRect(-SHADOW_WIDTH / 2, -SHADOW_WIDTH / 2, SHADOW_WIDTH, SHADOW_WIDTH);
    }

    this.shadow = Texture.from(canvas);

    return this.shadow;
  }
}
