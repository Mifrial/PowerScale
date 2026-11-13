import { Container, Graphics, Sprite, Texture } from 'pixi.js';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import { SCENE_ACTOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ACTOR';

/** Нарисованный актор: группа для Y-сортировки и части для обновления. */
export interface SceneActorPaint {
  node: Container;
  ring: Graphics;
  arrow: Graphics;
  sprite: Sprite;
  /** Радиус кольца в единицах сцены. */
  radius: number;
  /** Зум, под который нарисована толщина кольца. */
  zoom: number;
  selected: boolean;
  ghost: boolean;
}

/**
 * Актор: стендалка, тень и кольцо footprint со стрелкой facing.
 *
 * Всё рисуется в локальных координатах вокруг точки под ногами, поэтому
 * группа целиком вставляется в слой `decor` по `y` и сортируется вместе с
 * деревьями и травой. Стендалка процедурная: заглушка до ассетов персонажей.
 */
export class SceneActorService {
  private shadowTexture: Texture | null = null;

  private standeeTexture: Texture | null = null;

  /** Собирает группу актора. */
  paint(actor: SceneActor, resolution: number, zoom: number): SceneActorPaint {
    const base = Math.max(1, resolution);
    const height = base * SCENE_ACTOR.heightIpari;
    const radius = actor.radius > 0 ? actor.radius : base * SCENE_ACTOR.radiusIpari;
    const node = new Container();
    node.position.set(actor.x, actor.y);

    const shadow = new Sprite(this.shadow());
    shadow.anchor.set(0.5, 0.5);
    shadow.width = height * SCENE_ACTOR.shadowWidth;
    shadow.height = height * SCENE_ACTOR.shadowHeight;
    shadow.alpha = SCENE_ACTOR.shadowAlpha;
    node.addChild(shadow);

    const ring = new Graphics();
    node.addChild(ring);

    const arrow = new Graphics();
    const length = base * SCENE_ACTOR.arrowIpari;
    const half = (base * SCENE_ACTOR.arrowWidthIpari) / 2;
    const tip = -radius - length;
    arrow.poly([-half, tip + length, half, tip + length, 0, tip]);
    arrow.fill({ color: SCENE_ACTOR.arrowColor, alpha: SCENE_ACTOR.arrowAlpha });
    node.addChild(arrow);

    const standee = this.standee();
    const sprite = new Sprite(standee);
    sprite.anchor.set(0.5, 1);
    sprite.width = height * (standee.width / Math.max(1, standee.height));
    sprite.height = height;
    sprite.tint = actor.tint;
    node.addChild(sprite);

    const painted: SceneActorPaint = { node, ring, arrow, sprite, radius, zoom: 0, selected: false, ghost: false };
    this.update(painted, actor, zoom);

    return painted;
  }

  /** Обновляет позицию, поворот стрелки, кольцо под зум и отражение стендалки. */
  update(painted: SceneActorPaint, actor: SceneActor, zoom: number): void {
    painted.node.position.set(actor.x, actor.y);
    painted.arrow.rotation = (actor.facing * Math.PI) / 180;
    // Смотрит влево — отражаем стендалку; кольцо и стрелка показывают точный угол.
    const looksLeft = actor.facing > 90 && actor.facing < 270;
    painted.sprite.scale.x = Math.abs(painted.sprite.scale.x) * (looksLeft ? -1 : 1);
    const selected = actor.selected === true;
    const ghost = actor.ghost === true;
    if (Math.abs(painted.zoom - zoom) > 0.0001 || painted.selected !== selected || painted.ghost !== ghost) {
      painted.zoom = zoom;
      painted.selected = selected;
      painted.ghost = ghost;
      this.drawRing(painted, zoom, selected);
    }

    const underDeck = actor.underDeck === true;
    const alpha = ghost ? SCENE_ACTOR.ghostAlpha : underDeck ? SCENE_ACTOR.underDeckAlpha : 1;
    painted.sprite.alpha = alpha;
    painted.arrow.alpha = alpha;
  }

  /** Кольцо footprint: радиус в сцене, толщина — постоянная на экране. */
  private drawRing(painted: SceneActorPaint, zoom: number, selected: boolean): void {
    painted.ring.clear();
    painted.ring.circle(0, 0, painted.radius);
    painted.ring.stroke({
      color: selected ? SCENE_ACTOR.selectedRingColor : SCENE_ACTOR.ringColor,
      width: SCENE_ACTOR.ringPixels / Math.max(0.001, zoom),
      alpha: selected ? SCENE_ACTOR.selectedRingAlpha : SCENE_ACTOR.ringAlpha,
    });
  }

  /** Мягкая тень под ногами. */
  private shadow(): Texture {
    if (this.shadowTexture !== null) {
      return this.shadowTexture;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 128;
    canvas.height = 64;
    const context = canvas.getContext('2d');
    if (context !== null) {
      const gradient = context.createRadialGradient(64, 32, 0, 64, 32, 64);
      gradient.addColorStop(0, 'rgba(0, 0, 0, 0.9)');
      gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
      context.fillStyle = gradient;
      context.fillRect(0, 0, 128, 64);
    }

    this.shadowTexture = Texture.from(canvas);

    return this.shadowTexture;
  }

  /** Процедурная заглушка стендалки: плащ, голова и пояс. */
  private standee(): Texture {
    if (this.standeeTexture !== null) {
      return this.standeeTexture;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 128;
    canvas.height = 256;
    const context = canvas.getContext('2d');
    if (context !== null) {
      // Плащ.
      context.fillStyle = '#d7dde8';
      context.beginPath();
      context.moveTo(38, 252);
      context.quadraticCurveTo(20, 168, 44, 120);
      context.quadraticCurveTo(64, 96, 84, 120);
      context.quadraticCurveTo(108, 168, 90, 252);
      context.closePath();
      context.fill();

      // Голова.
      context.fillStyle = '#e8eef7';
      context.beginPath();
      context.arc(64, 80, 24, 0, Math.PI * 2);
      context.fill();

      // Пояс и складки: объём без второго цвета.
      context.strokeStyle = 'rgba(92, 104, 120, 0.55)';
      context.lineWidth = 4;
      context.beginPath();
      context.moveTo(38, 180);
      context.lineTo(90, 180);
      context.stroke();
      context.strokeStyle = 'rgba(92, 104, 120, 0.3)';
      context.lineWidth = 3;
      context.beginPath();
      context.moveTo(64, 126);
      context.lineTo(64, 248);
      context.stroke();
    }

    const texture = Texture.from(canvas);
    texture.source.style.addressMode = 'clamp-to-edge';
    texture.source.style.mipmapFilter = 'linear';
    texture.source.style.maxAnisotropy = 4;
    texture.source.style.update();
    texture.source.autoGenerateMipmaps = true;
    this.standeeTexture = texture;

    return texture;
  }
}
