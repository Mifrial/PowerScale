import { Texture } from 'pixi.js';
import type { Container } from 'pixi.js';
import { Sprite } from 'pixi.js';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneTextureCache } from '@/modules/Roleplay/Scene/Service/SceneTextureCache';

/** Нарисованный декор: спрайт и его размеры нужны для погружения в воду. */
export interface SceneDecorPaint {
  sprite: Sprite;
  width: number;
  height: number;
}

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

  private gradient: Texture | null = null;

  constructor(private readonly textures: SceneTextureCache) {}

  /**
   * Рисует декор в слой.
   *
   * @returns спрайт и его размеры; null — нужен fallback-маркер.
   */
  paint(layer: Container, element: SceneElement, resolution: number): SceneDecorPaint | null {
    if (element.shape.type !== 'point' || element.asset === null) {
      return null;
    }

    const texture = this.textures.get(element.asset.publicId);
    if (texture === null) {
      return null;
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

    return { sprite, width: sprite.width, height: sprite.height };
  }

  /** Высота якоря декора: нужна для Y-сортировки. */
  anchorY(element: SceneElement): number {
    return element.shape.type === 'point' ? element.shape.y : 0;
  }

  /**
   * Мягкая маска погружения: белая полоса с прозрачным верхом.
   *
   * Градиент вместо жёсткой границы: вода замутняет низ объекта постепенно,
   * и линия уровня не режет спрайт пополам.
   */
  submersionMask(width: number, height: number): Sprite {
    const sprite = new Sprite(this.gradientTexture());
    sprite.position.set(0, 0);
    sprite.width = Math.max(1, width);
    sprite.height = Math.max(1, height);

    return sprite;
  }

  /** Освобождает процедурные ресурсы. */
  destroy(): void {
    this.shadow?.destroy(true);
    this.shadow = null;
    this.gradient?.destroy(true);
    this.gradient = null;
  }

  private gradientTexture(): Texture {
    if (this.gradient !== null) {
      return this.gradient;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 4;
    canvas.height = 64;
    const context = canvas.getContext('2d');
    if (context !== null) {
      const image = context.createImageData(4, 64);
      for (let row = 0; row < 64; row += 1) {
        // Сверху прозрачно, снизу плотно: погружение нарастает книзу.
        const alpha = Math.round(255 * Math.min(1, (row / 63) ** 0.7));
        for (let column = 0; column < 4; column += 1) {
          const index = (row * 4 + column) * 4;
          image.data[index] = 255;
          image.data[index + 1] = 255;
          image.data[index + 2] = 255;
          image.data[index + 3] = alpha;
        }
      }

      context.putImageData(image, 0, 0);
    }

    this.gradient = Texture.from(canvas);

    return this.gradient;
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
