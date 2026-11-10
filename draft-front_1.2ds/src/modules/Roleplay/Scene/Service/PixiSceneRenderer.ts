import { Application, Container, Graphics, Sprite } from 'pixi.js';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneLayerName } from '@/modules/Roleplay/Scene/Enum/SceneLayerName';
import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import { SceneShapePainter } from '@/modules/Roleplay/Scene/Service/SceneShapePainter';
import { SceneTextureCache } from '@/modules/Roleplay/Scene/Service/SceneTextureCache';

/**
 * PixiJS-рендерер локаций: подложка, сетка, фигуры, декор и выделение.
 *
 * Доменное состояние остаётся снаружи; renderer получает готовую модель.
 */
export class PixiSceneRenderer implements ISceneRenderer {
  private app: Application | null = null;

  private host: HTMLElement | null = null;

  private world: Container | null = null;

  private layers: Partial<Record<SceneLayerName, Container>> = {};

  private model: SceneRenderModel | null = null;

  private viewport: SceneViewport | null = null;

  private readonly textures = new SceneTextureCache();

  private readonly painter = new SceneShapePainter();

  private sceneToken = 0;

  private dpr = 1;

  private contextLost = false;

  private readonly handleContextLost = (event: Event): void => {
    event.preventDefault();
    this.contextLost = true;
  };

  private readonly handleContextRestored = (): void => {
    this.contextLost = false;
    this.render();
  };

  async attach(host: HTMLElement): Promise<void> {
    if (this.app !== null) {
      return;
    }

    this.host = host;
    this.dpr = window.devicePixelRatio || 1;
    const app = new Application();
    await app.init({
      preference: 'webgl',
      width: Math.max(host.clientWidth, 1),
      height: Math.max(host.clientHeight, 1),
      resolution: this.dpr,
      autoDensity: true,
      antialias: true,
      backgroundAlpha: 0,
      autoStart: false,
    });
    app.ticker.stop();
    app.canvas.style.display = 'block';
    app.canvas.style.width = '100%';
    app.canvas.style.height = '100%';
    app.canvas.addEventListener('webglcontextlost', this.handleContextLost);
    app.canvas.addEventListener('webglcontextrestored', this.handleContextRestored);
    host.appendChild(app.canvas);
    this.app = app;
    this.createWorld();
  }

  detach(): void {
    const app = this.app;
    if (app === null) {
      return;
    }

    app.canvas.removeEventListener('webglcontextlost', this.handleContextLost);
    app.canvas.removeEventListener('webglcontextrestored', this.handleContextRestored);
    app.canvas.parentElement?.removeChild(app.canvas);
    this.host = null;
  }

  resize(width: number, height: number, dpr: number): void {
    if (this.app === null) {
      return;
    }

    this.dpr = dpr;
    this.app.renderer.resolution = dpr;
    this.app.renderer.resize(Math.max(width, 1), Math.max(height, 1));
    this.render();
  }

  setViewport(viewport: SceneViewport): void {
    this.viewport = viewport;
    this.applyViewport();
    this.render();
  }

  async setScene(model: SceneRenderModel): Promise<void> {
    this.model = model;
    const token = ++this.sceneToken;
    if (this.app === null || this.world === null) {
      return;
    }

    const board = model.boards.find((item) => item.id === model.activeBoardId);
    if (board === undefined) {
      this.clearAll();
      this.render();

      return;
    }

    await this.textures.load(this.textureIds(model, board));
    if (token !== this.sceneToken || this.app === null) {
      return;
    }

    this.rebuild(board);
    this.applyViewport();
    this.render();
  }

  setSelection(ids: number[]): void {
    if (this.model === null) {
      return;
    }

    this.model = { ...this.model, selection: ids };
    this.drawSelection();
    this.render();
  }

  render(): void {
    if (this.app === null || this.contextLost) {
      return;
    }

    this.app.render();
  }

  destroy(): void {
    ++this.sceneToken;
    this.textures.destroy();
    if (this.app !== null) {
      this.app.canvas.removeEventListener('webglcontextlost', this.handleContextLost);
      this.app.canvas.removeEventListener('webglcontextrestored', this.handleContextRestored);
      this.app.destroy(true, { children: true, texture: false, textureSource: false });
    }

    this.app = null;
    this.world = null;
    this.layers = {};
    this.model = null;
    this.viewport = null;
    this.host = null;
  }

  private createWorld(): void {
    if (this.app === null) {
      return;
    }

    this.world = new Container();
    this.world.label = 'scene-world';
    this.app.stage.addChild(this.world);
    this.layers = {};
    const order: SceneLayerName[] = [
      'backdrop',
      'grid',
      'terrain',
      'supports',
      'obstacles',
      'openings',
      'decor',
      'selection',
      'gizmos',
    ];
    for (const name of order) {
      const layer = new Container();
      layer.label = name;
      this.world.addChild(layer);
      this.layers[name] = layer;
    }
  }

  private applyViewport(): void {
    if (this.world === null || this.viewport === null) {
      return;
    }

    const zoom = this.viewport.zoom;
    this.world.scale.set(zoom);
    this.world.position.set(
      this.viewport.width / 2 - this.viewport.x * zoom,
      this.viewport.height / 2 - this.viewport.y * zoom,
    );
  }

  private rebuild(board: SceneBoard): void {
    this.clearAll();
    if (this.model === null) {
      return;
    }

    this.drawBackdrop(board);
    this.drawGrid(board);
    this.drawEnclosures(board);
    const elements = this.model.elements
      .filter((element) => element.boardId === board.id)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    for (const element of elements) {
      this.drawElement(element);
    }

    this.drawSelection();
  }

  private clearAll(): void {
    for (const name of Object.keys(this.layers) as SceneLayerName[]) {
      this.clearLayer(name);
    }
  }

  private clearLayer(name: SceneLayerName): void {
    const layer = this.layers[name];
    if (layer === undefined) {
      return;
    }

    const children = layer.removeChildren();
    for (const child of children) {
      child.destroy({ children: true, texture: false, textureSource: false });
    }
  }

  private textureIds(model: SceneRenderModel, board: SceneBoard): string[] {
    const publicIds = new Set<string>();
    if (board.baseFile !== null) {
      publicIds.add(board.baseFile.publicId);
    }

    for (const element of model.elements) {
      if (element.boardId === board.id && element.asset !== null) {
        publicIds.add(element.asset.publicId);
      }
    }

    return [...publicIds];
  }

  private drawBackdrop(board: SceneBoard): void {
    const layer = this.layers.backdrop;
    if (layer === undefined) {
      return;
    }

    const texture = this.textures.get(board.baseFile?.publicId ?? null);
    if (texture !== null) {
      const sprite = new Sprite(texture);
      sprite.width = board.boundWidth;
      sprite.height = board.boundHeight;
      sprite.position.set(0, 0);
      layer.addChild(sprite);

      return;
    }

    const graphics = new Graphics();
    graphics
      .rect(0, 0, board.boundWidth, board.boundHeight)
      .fill({ color: 0x1f2937, alpha: 0.08 })
      .stroke({ color: 0x475569, width: 2 / this.zoom(), alpha: 0.35 });
    layer.addChild(graphics);
  }

  private drawGrid(board: SceneBoard): void {
    if (board.gridStep <= 0) {
      return;
    }

    const layer = this.layers.grid;
    if (layer === undefined) {
      return;
    }

    const zoom = this.zoom();
    const minor = board.gridStep;
    const major = minor * 2;
    const minorVisible = minor * zoom >= 4;
    const majorVisible = major * zoom >= 8;
    if (!minorVisible && !majorVisible) {
      return;
    }

    if (minorVisible) {
      const graphics = new Graphics();
      for (let x = 0; x <= board.boundWidth; x += minor) {
        graphics.moveTo(x, 0).lineTo(x, board.boundHeight);
      }

      for (let y = 0; y <= board.boundHeight; y += minor) {
        graphics.moveTo(0, y).lineTo(board.boundWidth, y);
      }

      graphics.stroke({ color: 0x64748b, width: 1 / zoom, alpha: 0.12 });
      layer.addChild(graphics);
    }

    if (majorVisible) {
      const graphics = new Graphics();
      for (let x = 0; x <= board.boundWidth; x += major) {
        graphics.moveTo(x, 0).lineTo(x, board.boundHeight);
      }

      for (let y = 0; y <= board.boundHeight; y += major) {
        graphics.moveTo(0, y).lineTo(board.boundWidth, y);
      }

      graphics.stroke({ color: 0x94a3b8, width: 1.5 / zoom, alpha: 0.22 });
      layer.addChild(graphics);
    }
  }

  private drawEnclosures(board: SceneBoard): void {
    if (this.model === null) {
      return;
    }

    const layer = this.layers.supports;
    if (layer === undefined) {
      return;
    }

    for (const enclosure of this.model.enclosures) {
      if (enclosure.parentBoardId !== board.id) {
        continue;
      }

      const graphics = new Graphics();
      this.painter.paint(
        graphics,
        enclosure.footprint,
        undefined,
        { color: 0x0ea5e9, width: 2 / this.zoom(), alpha: 0.75 },
        this.zoom(),
      );
      layer.addChild(graphics);
    }
  }

  private drawElement(element: SceneElement): void {
    const layerName = this.layerFor(element);
    const layer = this.layers[layerName];
    if (layer === undefined) {
      return;
    }

    layer.addChild(this.painter.paintElement(element, this.zoom()));
  }

  private layerFor(element: SceneElement): SceneLayerName {
    if (element.kind === 'terrain') return 'terrain';
    if (element.kind === 'support') return 'supports';
    if (element.kind === 'obstacle') return 'obstacles';
    if (element.kind === 'opening') return 'openings';

    return 'decor';
  }

  private drawSelection(): void {
    this.clearLayer('selection');
    if (this.model === null) {
      return;
    }

    const layer = this.layers.selection;
    if (layer === undefined) {
      return;
    }

    const selected = this.model.elements.filter(
      (element) => element.boardId === this.model?.activeBoardId && this.model?.selection.includes(element.id),
    );
    for (const element of selected) {
      const graphics = new Graphics();
      this.painter.paint(
        graphics,
        element.shape,
        undefined,
        { color: 0xfacc15, width: 3 / this.zoom(), alpha: 1 },
        this.zoom(),
      );
      layer.addChild(graphics);
    }
  }

  private zoom(): number {
    return this.viewport?.zoom ?? 1;
  }
}
