import { Application, Container, Geometry, Graphics, Mesh, Sprite } from 'pixi.js';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneOverlayPaint } from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVertexTarget } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import type { SceneLayerName } from '@/modules/Roleplay/Scene/Enum/SceneLayerName';
import type { ISceneRenderer } from '@/modules/Roleplay/Scene/Interface/ISceneRenderer';
import type { SceneDecorPaint } from '@/modules/Roleplay/Scene/Service/SceneDecorService';
import type { SceneWaterNode } from '@/modules/Roleplay/Scene/Service/SceneWaterService';
import { SceneShapePainter } from '@/modules/Roleplay/Scene/Service/SceneShapePainter';
import { SceneDecorService } from '@/modules/Roleplay/Scene/Service/SceneDecorService';
import type { SceneActorPaint } from '@/modules/Roleplay/Scene/Service/SceneActorService';
import { SceneActorService } from '@/modules/Roleplay/Scene/Service/SceneActorService';
import { SceneBridgeService } from '@/modules/Roleplay/Scene/Service/SceneBridgeService';
import { SceneGrassMeshService } from '@/modules/Roleplay/Scene/Service/SceneGrassMeshService';
import { SceneGrassService } from '@/modules/Roleplay/Scene/Service/SceneGrassService';
import { SceneGrassShader } from '@/modules/Roleplay/Scene/Service/SceneGrassShader';
import type { SceneGrassShaderHandle } from '@/modules/Roleplay/Scene/Service/SceneGrassShader';
import { SceneGrassTextureService } from '@/modules/Roleplay/Scene/Service/SceneGrassTextureService';
import { SceneGrassTrailService } from '@/modules/Roleplay/Scene/Service/SceneGrassTrailService';
import { SceneTerrainBlendService } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendService';
import { SceneTerrainPainter } from '@/modules/Roleplay/Scene/Service/SceneTerrainPainter';
import { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneTextureCache } from '@/modules/Roleplay/Scene/Service/SceneTextureCache';
import { SceneWaterService, isShaderWater } from '@/modules/Roleplay/Scene/Service/SceneWaterService';
import { SCENE_RENDERER_PREFERENCE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_RENDERER_PREFERENCE';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';

/** Вода и её анимация на активном поле. */
interface WaterEntry {
  elementId: number;
  node: SceneWaterNode;
}

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

  /** Границы активного поля: по ним открываются концы рек, упёршиеся в край. */
  private boardBounds: SceneBounds | null = null;

  private viewport: SceneViewport | null = null;

  private draftShape: SceneShape | null = null;

  private draftGraphics: Graphics | null = null;

  private vertexFocus: SceneVertexTarget | null = null;

  private readonly textures = new SceneTextureCache();

  private readonly patterns = new SceneTerrainPatternService();

  private readonly materials = new SceneTerrainTextureService();

  private readonly painter = new SceneShapePainter();

  private readonly decor = new SceneDecorService(this.textures);

  private readonly blend = new SceneTerrainBlendService();

  private readonly terrain = new SceneTerrainPainter(this.patterns, this.materials, this.blend);

  private readonly grassTextures = new SceneGrassTextureService(this.materials, this.patterns);

  private readonly grass = new SceneGrassService(sceneGeometryService, this.grassTextures);

  private readonly grassShader = new SceneGrassShader();

  private readonly grassMesh = new SceneGrassMeshService();

  private readonly grassTrail = new SceneGrassTrailService();

  private carpet: SceneGrassShaderHandle | null = null;

  private readonly actorService = new SceneActorService();

  private readonly bridge = new SceneBridgeService();

  private actors: SceneActor[] = [];

  private readonly actorNodes = new Map<number, SceneActorPaint>();

  private readonly propY = new Map<Container, number>();

  /** Готовые полосы оверлеев; их считает сервис, рендерер только рисует. */
  private overlayPaint: SceneOverlayPaint | null = null;

  /** Полилиния маршрута выбранного актора. */
  private routePoints: ScenePoint[] | null = null;

  /** Идёт жест редактирования: траву не перестраиваем, иначе река тормозит. */
  private editing = false;

  private readonly water = new SceneWaterService(this.materials);

  private waterNodes: WaterEntry[] = [];

  private waterAnimationEnabled = true;

  private waterElapsed = 0;

  private waterTickerActive = false;

  private readonly handleWaterTick = (ticker: { deltaMS: number }): void => {
    // Долгий кадр не должен разом прокручивать воду: иначе после подвисания
    // картинка дёргается. Ограничиваем шаг времени, вода просто чуть
    // отстаёт от реального времени.
    this.waterElapsed += Math.min(ticker.deltaMS, 50) / 1000;
    for (const entry of this.waterNodes) {
      entry.node.update(this.waterElapsed);
    }
  };

  private readonly handleVisibilityChange = (): void => {
    this.syncWaterAnimation();
    this.syncTrailTicker();
  };

  private trailTickerActive = false;

  private readonly handleTrailTick = (ticker: { deltaMS: number }): void => {
    // След гаснет по времени: стоящий актор не оставляет круга, а прошедший —
    // полосу, которая распрямляется за trailRelaxMs.
    const alive = this.grassTrail.advance(Math.min(ticker.deltaMS, 50));
    this.applyTrail();
    if (!alive) {
      this.syncTrailTicker();
    }
  };

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
    const app = await this.createApplication(host);
    app.ticker.stop();
    app.canvas.style.display = 'block';
    app.canvas.style.width = '100%';
    app.canvas.style.height = '100%';
    app.canvas.addEventListener('webglcontextlost', this.handleContextLost);
    app.canvas.addEventListener('webglcontextrestored', this.handleContextRestored);
    host.appendChild(app.canvas);
    this.app = app;
    this.createWorld();
    document.addEventListener('visibilitychange', this.handleVisibilityChange);
  }

  /**
   * Создаёт Pixi `Application` на выбранном бэкенде.
   *
   * Если предпочтён WebGPU, а браузер его не дал, пробуем WebGL: редактор
   * должен открываться, а не падать с ошибкой контекста.
   */
  private async createApplication(host: HTMLElement): Promise<Application> {
    const preferences: ('webgl' | 'webgpu')[] =
      SCENE_RENDERER_PREFERENCE === 'webgpu' ? ['webgpu', 'webgl'] : ['webgl'];
    let failure: unknown = null;
    for (const preference of preferences) {
      const app = new Application();
      try {
        await app.init({
          preference,
          width: Math.max(host.clientWidth, 1),
          height: Math.max(host.clientHeight, 1),
          resolution: this.dpr,
          autoDensity: true,
          antialias: true,
          backgroundAlpha: 0,
          autoStart: false,
        });

        return app;
      } catch (error) {
        failure = error;
      }
    }

    throw failure ?? new Error('GPU context is unavailable');
  }

  detach(): void {
    const app = this.app;
    if (app === null) {
      return;
    }

    app.canvas.removeEventListener('webglcontextlost', this.handleContextLost);
    app.canvas.removeEventListener('webglcontextrestored', this.handleContextRestored);
    document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    this.stopTickers();
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
    const previous = this.viewport;
    this.viewport = viewport;
    this.applyViewport();
    if (this.shouldRetessellate(previous, viewport)) {
      this.retessellate();
    }

    if (previous !== null && Math.abs(previous.zoom - viewport.zoom) > 0.0001) {
      for (const actor of this.actors) {
        const painted = this.actorNodes.get(actor.id);
        if (painted !== undefined) {
          this.actorService.update(painted, actor, viewport.zoom);
        }
      }
    }

    this.syncWaterAnimation();
    this.render();
  }

  async setScene(model: SceneRenderModel): Promise<void> {
    this.model = model;
    this.grassTrail.reset();
    const token = ++this.sceneToken;
    if (this.app === null || this.world === null) {
      return;
    }

    // Во время жеста тянем только контур: пересборка воды, террейна и травы
    // на каждое движение указателя превращает перетаскивание реки в слайд-шоу.
    // Полная сцена собирается после отпускания.
    if (this.editing) {
      this.drawSelection();
      this.render();

      return;
    }

    const board = model.boards.find((item) => item.id === model.activeBoardId);
    if (board === undefined) {
      this.boardBounds = null;
      this.clearAll();
      this.render();

      return;
    }

    await Promise.all([this.textures.load(this.textureIds(model, board)), this.materials.load()]);
    if (token !== this.sceneToken || this.app === null) {
      return;
    }

    this.rebuild(board);
    this.applyViewport();
    this.render();
    this.syncWaterAnimation();
  }

  /**
   * Режим жеста редактирования.
   *
   * Пока тянут террейн, раскладка травы не пересобирается: сцена обновляется
   * на каждом движении указателя, и полный луг на 40 000 квадов не должен
   * попадать в этот цикл. После отпускания трава возвращается.
   */
  setEditing(editing: boolean): void {
    if (this.editing === editing) {
      return;
    }

    this.editing = editing;
    if (editing || this.model === null) {
      return;
    }

    const board = this.model.boards.find((item) => item.id === this.model?.activeBoardId);
    if (board !== undefined) {
      this.rebuild(board);
      this.applyViewport();
      this.render();
    }
  }

  /** Акторы на поле: позиции и facing приходят снаружи, состояние — у игры. */
  setActors(actors: SceneActor[]): void {
    this.actors = actors;
    if (this.layers.decor !== undefined) {
      this.paintActors();
    }

    if (this.model !== null) {
      this.grassTrail.record(actors, this.model.resolution);
      this.applyTrail();
      this.syncTrailTicker();
    }

    this.render();
  }

  /** Готовые полосы тактических оверлеев; null — оверлеи выключены. */
  setOverlayPaint(paint: SceneOverlayPaint | null): void {
    this.overlayPaint = paint;
    this.drawOverlay();
    this.render();
  }

  /** Маршрут выбранного актора; null — линия скрыта. */
  setRoute(points: ScenePoint[] | null): void {
    this.routePoints = points;
    this.drawRoute();
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

  setDraft(shape: SceneShape | null): void {
    this.draftShape = shape;
    this.drawDraft();
    this.render();
  }

  setVertexFocus(target: SceneVertexTarget | null): void {
    this.vertexFocus = target;
    this.drawSelection();
    this.render();
  }

  /** Анимация воды — пользовательская настройка, а не свойство локации. */
  setWaterAnimation(enabled: boolean): void {
    if (this.waterAnimationEnabled === enabled) {
      return;
    }

    this.waterAnimationEnabled = enabled;
    this.syncWaterAnimation();
  }

  render(): void {
    if (this.app === null || this.contextLost) {
      return;
    }

    this.app.render();
  }

  /** Тикер воды: только preview/play, включённая настройка и видимая вкладка. */
  private syncWaterAnimation(): void {
    if (this.app === null) {
      return;
    }

    const hidden = typeof document !== 'undefined' && document.hidden;
    const shouldRun =
      this.waterAnimationEnabled && this.model?.mode !== 'edit' && this.waterNodes.length > 0 && !hidden;
    if (shouldRun && !this.waterTickerActive) {
      this.app.ticker.add(this.handleWaterTick, this);
      this.waterTickerActive = true;
    } else if (!shouldRun && this.waterTickerActive) {
      this.app.ticker.remove(this.handleWaterTick, this);
      this.waterTickerActive = false;
    }

    this.syncTicker(hidden);
  }

  /** Тикер следа: пока есть точки, они гаснут и ковёр перерисовывается. */
  private syncTrailTicker(): void {
    if (this.app === null) {
      return;
    }

    const hidden = typeof document !== 'undefined' && document.hidden;
    const shouldRun = !hidden && this.grassTrail.hasPoints();
    if (shouldRun && !this.trailTickerActive) {
      this.app.ticker.add(this.handleTrailTick, this);
      this.trailTickerActive = true;
    } else if (!shouldRun && this.trailTickerActive) {
      this.app.ticker.remove(this.handleTrailTick, this);
      this.trailTickerActive = false;
    }

    this.syncTicker(hidden);
  }

  /** Общий тикер кадра: вода и затухающий след травы делят один цикл. */
  private syncTicker(hidden: boolean): void {
    if (this.app === null) {
      return;
    }

    if (!hidden && (this.waterTickerActive || this.trailTickerActive)) {
      this.app.ticker.start();

      return;
    }

    if (this.app.ticker.started) {
      this.app.ticker.stop();
      this.render();
    }
  }

  /** Снимает обработчики тикера перед detach/destroy. */
  private stopTickers(): void {
    if (this.app === null) {
      return;
    }

    if (this.waterTickerActive) {
      this.app.ticker.remove(this.handleWaterTick, this);
      this.waterTickerActive = false;
    }

    if (this.trailTickerActive) {
      this.app.ticker.remove(this.handleTrailTick, this);
      this.trailTickerActive = false;
    }

    this.app.ticker.stop();
  }

  destroy(): void {
    ++this.sceneToken;
    document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    this.stopTickers();
    this.grassTrail.reset();
    this.waterNodes = [];
    this.textures.destroy();
    this.patterns.destroy();
    this.materials.destroy();
    this.grassTextures.destroy();
    this.decor.destroy();
    this.water.destroy();
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
    this.draftShape = null;
    this.draftGraphics = null;
    this.vertexFocus = null;
    this.host = null;
  }

  /**
   * Нужно ли пересобрать геометрию после смены зума.
   *
   * Тесселяция кривых зависит от масштаба: при сильном приближении старые
   * сэмплы становятся видны как углы. Пересобираем только на заметных шагах,
   * чтобы не платить за каждый тик колеса.
   */
  private shouldRetessellate(previous: SceneViewport | null, next: SceneViewport): boolean {
    if (previous === null || previous.zoom <= 0 || this.model === null) {
      return false;
    }

    const ratio = next.zoom / previous.zoom;

    return ratio > 2 || ratio < 0.5;
  }

  /** Пересобирает слои активного поля под текущий зум. */
  private retessellate(): void {
    const board = this.model?.boards.find((item) => item.id === this.model?.activeBoardId);
    if (board === undefined || this.world === null) {
      return;
    }

    this.rebuild(board);
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
      'waterPlane',
      'ground',
      'overlay',
      'route',
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
    this.boardBounds = { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
    this.waterNodes = [];
    if (this.model === null) {
      return;
    }

    this.drawBackdrop(board);
    if (this.isEdit()) {
      this.drawGrid(board);
    }

    this.drawEnclosures(board);
    const elements = this.model.elements
      .filter((element) => element.boardId === board.id)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    const terrainElements = elements
      .filter((element) => element.kind === 'terrain')
      .sort(
        (left, right) =>
          this.terrainRank(left) - this.terrainRank(right) || left.sortOrder - right.sortOrder || left.id - right.id,
      );
    const resolution = this.model?.resolution ?? 64;
    const zoom = this.zoom();
    const waterElements = terrainElements.filter((element) => element.props.kind === 'water');
    for (const element of terrainElements) {
      this.drawTerrain(element);
    }

    for (const element of waterElements) {
      this.drawWaterPlane(element, resolution, zoom);
      this.drawWaterFoam(element, resolution, zoom);
    }

    const bridgeElements = elements.filter((element) => this.isBridge(element));
    const decorElements = elements
      .filter((element) => element.kind === 'decor')
      .sort(
        (left, right) =>
          this.decor.anchorY(left) - this.decor.anchorY(right) ||
          left.sortOrder - right.sortOrder ||
          left.id - right.id,
      );
    for (const element of elements) {
      if (element.kind !== 'terrain' && element.kind !== 'decor' && !this.isBridge(element)) {
        this.drawElement(element);
      }
    }

    this.drawProps(board, bridgeElements, decorElements, terrainElements, waterElements, resolution);
    this.drawOverlay();
    this.drawRoute();

    if (this.isEdit()) {
      this.drawGridOverlay(board);
    }

    this.applyContentClip(board);
    if (this.isEdit()) {
      this.drawSelection();
      this.drawBoardFrame(board);
      this.drawDraft();
    }
  }

  /**
   * Декор и пучки травы одним списком по `y` якоря.
   *
   * Общая сортировка нужна, чтобы трава за деревом уходила под его спрайт, а
   * трава перед деревом перекрывала низ ствола.
   */
  private drawProps(
    board: SceneBoard,
    bridgeElements: SceneElement[],
    decorElements: SceneElement[],
    terrainElements: SceneElement[],
    waterElements: SceneElement[],
    resolution: number,
  ): void {
    const layer = this.layers.decor;
    const ground = this.layers.ground;
    if (layer === undefined || ground === undefined) {
      return;
    }

    this.propY.clear();
    this.actorNodes.clear();
    const bounds = { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
    // Мосты и ковёр — пол над водой и под оверлеями, декором и акторами.
    for (const element of bridgeElements) {
      const painted = this.bridge.paint(element, resolution, this.zoom());
      if (painted !== null) {
        ground.addChild(painted);
      }
    }

    if (!this.editing) {
      this.drawCarpet(ground, terrainElements, bounds, resolution);
    }

    const props = [
      ...decorElements.map((element) => {
        const anchorY = this.decor.anchorY(element);

        return {
          anchorY,
          rank: 1,
          order: element.sortOrder,
          id: element.id,
          draw: (): void => {
            const group = this.decorGroup(element, waterElements, resolution);
            if (group !== null) {
              layer.addChild(group);
              this.propY.set(group, anchorY);
            }
          },
        };
      }),
      ...(this.editing ? [] : this.grass.placeClumps(terrainElements, bounds, resolution)).map((tuft) => ({
        anchorY: tuft.y,
        rank: 0,
        order: 0,
        id: tuft.sequence,
        draw: (): void => {
          const sprite = this.grass.paint(layer, tuft);
          if (sprite !== null) {
            this.propY.set(sprite, tuft.y);
          }
        },
      })),
    ];
    props.sort(
      (left, right) =>
        left.anchorY - right.anchorY || left.rank - right.rank || left.order - right.order || left.id - right.id,
    );
    for (const prop of props) {
      prop.draw();
    }

    this.paintActors();
  }

  /** Оверлеи поверх пола и под декором: стоимость и скрытые клетки. */
  private drawOverlay(): void {
    const layer = this.layers.overlay;
    if (layer === undefined) {
      return;
    }

    this.clearLayer('overlay');
    if (this.overlayPaint === null || this.isEdit()) {
      return;
    }

    const graphics = new Graphics();
    for (const band of this.overlayPaint.bands) {
      graphics.rect(band.x, band.y, band.width, band.height).fill({ color: band.color, alpha: band.alpha });
    }
    if (graphics.context.instructions.length > 0) {
      layer.addChild(graphics);
    }
  }

  /** Линия маршрута: над полом и оверлеями, под декором и акторами. */
  private drawRoute(): void {
    const layer = this.layers.route;
    if (layer === undefined) {
      return;
    }

    this.clearLayer('route');
    if (this.routePoints === null || this.routePoints.length < 2 || this.isEdit()) {
      return;
    }

    const zoom = Math.max(0.001, this.zoom());
    const graphics = new Graphics();
    const first = this.routePoints[0];
    graphics.moveTo(first[0], first[1]);
    for (const point of this.routePoints.slice(1)) {
      graphics.lineTo(point[0], point[1]);
    }
    graphics.stroke({ color: 0x60a5fa, width: 3 / zoom, alpha: 0.85 });
    for (const point of this.routePoints) {
      graphics.circle(point[0], point[1], 2.5 / zoom).fill({ color: 0xbfdbfe, alpha: 0.95 });
    }
    layer.addChild(graphics);
  }

  /** Ковёр травы: один меш под декором и акторами. */
  private drawCarpet(layer: Container, terrainElements: SceneElement[], bounds: SceneBounds, resolution: number): void {
    this.carpet = null;
    const tufts = this.grass.placeCarpet(terrainElements, bounds, resolution);
    if (tufts.length === 0) {
      return;
    }

    const atlas = this.grassTextures.atlasTexture();
    if (atlas === null) {
      return;
    }

    const mesh = this.grassMesh.build(tufts, this.grassTextures.frameRects());
    if (mesh === null) {
      return;
    }

    const geometry = new Geometry({
      attributes: {
        aPosition: { buffer: mesh.positions, format: 'float32x2' },
        aUV: { buffer: mesh.uvs, format: 'float32x2' },
        aTint: { buffer: mesh.tints, format: 'float32x4' },
        aOrigin: { buffer: mesh.origins, format: 'float32x2' },
      },
      indexBuffer: mesh.indices,
    });
    const carpet = this.grassShader.create(atlas);
    this.carpet = carpet;
    this.applyTrail();
    layer.addChild(new Mesh({ geometry, shader: carpet.shader }));
  }

  /** Передаёт след движения в шейдер ковра; без меша и модели — no-op. */
  private applyTrail(): void {
    if (this.carpet === null || this.model === null) {
      return;
    }

    this.carpet.apply(this.grassTrail.pack(this.model.resolution));
  }

  /** Группа декора: тень, спрайт и погружение — один ребёнок слоя. */
  private decorGroup(element: SceneElement, waterElements: SceneElement[], resolution: number): Container | null {
    const group = new Container();
    const painted = this.decor.paint(group, element, resolution);
    if (painted === null) {
      group.addChild(this.painter.paintElement(element, this.zoom()));
    } else {
      this.paintSubmersion(group, element, painted, waterElements, resolution);
    }

    return group;
  }

  /** Акторы поверх статики: вставка по `y` между детьми слоя. */
  private paintActors(): void {
    const layer = this.layers.decor;
    if (layer === undefined || this.model === null) {
      return;
    }

    const resolution = this.model.resolution;
    const zoom = this.zoom();
    const ids = new Set(this.actors.map((actor) => actor.id));
    for (const [id, painted] of this.actorNodes) {
      if (!ids.has(id)) {
        painted.node.destroy({ children: true });
        this.actorNodes.delete(id);
        this.propY.delete(painted.node);
      }
    }

    for (const actor of this.actors) {
      const painted = this.actorNodes.get(actor.id);
      if (painted === undefined) {
        const created = this.actorService.paint(actor, resolution, zoom);
        layer.addChild(created.node);
        this.actorNodes.set(actor.id, created);
        this.placeActor(created.node, actor.y);
        continue;
      }

      this.actorService.update(painted, actor, zoom);
      this.placeActor(painted.node, actor.y);
    }
  }

  /** Ставит актора на его `y` среди статичных детей слоя. */
  private placeActor(node: Container, y: number): void {
    const layer = this.layers.decor;
    if (layer === undefined) {
      return;
    }

    const children = layer.children;
    let index = 0;
    for (const child of children) {
      if (child === node) {
        continue;
      }

      if ((this.propY.get(child) ?? 0) <= y) {
        index += 1;
      } else {
        break;
      }
    }

    const current = children.indexOf(node);
    if (current !== index) {
      layer.setChildIndex(node, index);
    }

    this.propY.set(node, y);
  }

  /** Вода выше остальных шаблонов террейна. */
  private terrainRank(element: SceneElement): number {
    return element.props.kind === 'water' ? 1 : 0;
  }

  private isEdit(): boolean {
    return this.model?.mode === 'edit';
  }

  /** Продление открытых концов реки, упёршейся в край поля. */
  private openEndPads(element: SceneElement, resolution: number): readonly [number, number] {
    return sceneGeometryService.openEndPads(element.shape, this.boardBounds, resolution);
  }

  private clearAll(): void {
    this.carpet = null;
    for (const name of Object.keys(this.layers) as SceneLayerName[]) {
      this.clearLayer(name);
    }
  }

  private clearLayer(name: SceneLayerName): void {
    const layer = this.layers[name];
    if (layer === undefined) {
      return;
    }

    layer.mask = null;
    const children = layer.removeChildren();
    for (const child of children) {
      child.destroy({ children: true, texture: false, textureSource: false });
    }

    if (name === 'gizmos') {
      this.draftGraphics = null;
    }

    if (name === 'terrain' || name === 'waterPlane') {
      this.waterNodes = [];
    }
  }

  /** Обрезает содержимое поля по его границам; gizmos остаются снаружи. */
  private applyContentClip(board: SceneBoard): void {
    const names: SceneLayerName[] = [
      'terrain',
      'supports',
      'obstacles',
      'openings',
      'waterPlane',
      'ground',
      'overlay',
      'route',
      'decor',
    ];
    for (const name of names) {
      const layer = this.layers[name];
      if (layer === undefined) {
        continue;
      }

      const mask = new Graphics();
      mask.rect(0, 0, board.boundWidth, board.boundHeight).fill({ color: 0xffffff });
      layer.addChild(mask);
      layer.mask = mask;
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
    const tile = Math.max(1, Math.floor(Math.min(board.boundWidth, board.boundHeight) / 12));
    for (let row = 0, y = 0; y < board.boundHeight; row += 1, y += tile) {
      for (let column = 0, x = 0; x < board.boundWidth; column += 1, x += tile) {
        if ((row + column) % 2 !== 0) {
          continue;
        }

        graphics.rect(x, y, Math.min(tile, board.boundWidth - x), Math.min(tile, board.boundHeight - y));
        graphics.fill({ color: 0x64748b, alpha: 0.07 });
      }
    }

    graphics
      .rect(0, 0, board.boundWidth, board.boundHeight)
      .fill({ color: 0x1f2937, alpha: 0.05 })
      .stroke({ color: 0x94a3b8, width: 2 / this.zoom(), alpha: 0.5 });
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

    this.paintGrid(layer, board, 1, 0x94a3b8);
  }

  /** Сетка поверх террейна: под непрозрачным материалом нижняя сетка не видна. */
  private drawGridOverlay(board: SceneBoard): void {
    if (board.gridStep <= 0) {
      return;
    }

    const layer = this.layers.terrain;
    if (layer === undefined) {
      return;
    }

    this.paintGrid(layer, board, 0.55, 0x0f172a);
  }

  private paintGrid(layer: Container, board: SceneBoard, alphaFactor: number, color: number): void {
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

      graphics.stroke({ color, width: 1 / zoom, alpha: 0.14 * alphaFactor });
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

      graphics.stroke({ color, width: 1.4 / zoom, alpha: 0.24 * alphaFactor });
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

  /** Террейн: кромка, поверхность или вода. */
  private drawTerrain(element: SceneElement): void {
    const layer = this.layers.terrain;
    if (layer === undefined) {
      return;
    }

    const zoom = this.zoom();
    const resolution = this.model?.resolution ?? 64;
    const endPads = this.openEndPads(element, resolution);
    // У шейдерной воды мокрый берег и кромка считаются в шейдере тела:
    // гладкая полоса paintWaterBank дала бы вторую параллельную линию.
    const shaderWater = element.props.kind === 'water' && isShaderWater(element);
    this.terrain.paintShore(layer, element, resolution, zoom, endPads, !shaderWater);

    if (element.props.kind === 'water') {
      if (!shaderWater) {
        this.terrain.paintWaterBank(layer, element, resolution, zoom, endPads);
      }
      // Тело воды на шейдере рисует дно само: иначе галька легла бы дважды.
      if (!shaderWater) {
        this.terrain.paintWaterBed(layer, element, resolution, zoom);
      }
      this.drawWaterBody(layer, element, resolution, zoom, endPads);

      return;
    }

    if (element.asset !== null) {
      const sprite = this.terrainSprite(element);
      if (sprite !== null) {
        layer.addChild(sprite);

        return;
      }
    }

    this.terrain.paintSurface(layer, element, resolution, zoom);
  }

  /** Толща воды: градиент глубины и каустика под актёрами. */
  private drawWaterBody(
    layer: Container,
    element: SceneElement,
    resolution: number,
    zoom: number,
    endPads: readonly [number, number],
  ): void {
    const node = this.water.paintBody(layer, element, resolution, zoom, endPads);
    if (node === null) {
      return;
    }

    this.waterNodes.push({ elementId: element.id, node });
    node.update(this.waterElapsed);
  }

  /** Плоскость воды: рябь и блики; под декором и актёрами. */
  private drawWaterPlane(element: SceneElement, resolution: number, zoom: number): void {
    const layer = this.layers.waterPlane;
    if (layer === undefined) {
      return;
    }

    const endPads = this.openEndPads(element, resolution);
    const node = this.water.paintPlane(layer, element, resolution, zoom, endPads);
    if (node === null) {
      return;
    }

    this.waterNodes.push({ elementId: element.id, node });
    node.update(this.waterElapsed);
  }

  /**
   * Урез воды: пена и линия уреза.
   *
   * Живёт в плоскости воды, под декором: урез лежит на земле, поэтому дерево на
   * берегу его перекрывает, а не просвечивает сквозь пену.
   */
  private drawWaterFoam(element: SceneElement, resolution: number, zoom: number): void {
    const layer = this.layers.waterPlane;
    if (layer === undefined) {
      return;
    }

    // У шейдерной воды пена считается в шейдере поверхности: старая полоса
    // нарисовала бы вторую, текстурную «мочалку» поверх.
    if (isShaderWater(element)) {
      return;
    }

    const endPads = this.openEndPads(element, resolution);
    const node = this.water.paintFoam(layer, element, resolution, zoom, endPads);
    if (node === null) {
      return;
    }

    this.waterNodes.push({ elementId: element.id, node });
    node.update(this.waterElapsed);
  }

  private isBridge(element: SceneElement): boolean {
    return element.kind === 'support' && element.props.kind === 'bridge';
  }

  private drawElement(element: SceneElement): void {
    const layerName = this.layerFor(element);
    const layer = this.layers[layerName];
    if (layer === undefined) {
      return;
    }

    layer.addChild(this.painter.paintElement(element, this.zoom()));
  }

  /** Декор-спрайт; без ассета — прежний маркер. */
  /**
   * Погружение объекта в воду по его нижней точке.
   *
   * Глубина берётся из поля воды; если точка вне водоёма — объект сухой, даже
   * когда его спрайт наложился на воду. Тонируется только низ силуэта, высота
   * зависит от глубины: у берега меньше, к середине больше.
   */
  private paintSubmersion(
    layer: Container,
    element: SceneElement,
    painted: SceneDecorPaint,
    waterElements: SceneElement[],
    resolution: number,
  ): void {
    if (element.shape.type !== 'point') {
      return;
    }

    const anchor = element.shape;
    let depth = 0;
    for (const waterElement of waterElements) {
      depth = Math.max(
        depth,
        this.water.depthAt(waterElement, resolution, [anchor.x, anchor.y], this.openEndPads(waterElement, resolution)),
      );
    }

    // Дерево не должно утонуть наполовину: даже в самой глубокой точке мокрый
    // низ ограничен долей высоты спрайта.
    const submerged = Math.min(painted.height * 0.3, depth * 0.6);
    if (submerged < Math.max(1, painted.height * 0.04)) {
      return;
    }

    const tint = new Sprite(painted.sprite.texture);
    tint.anchor.copyFrom(painted.sprite.anchor);
    tint.position.copyFrom(painted.sprite.position);
    tint.width = painted.sprite.width;
    tint.height = painted.sprite.height;
    tint.rotation = painted.sprite.rotation;
    tint.tint = 0x2f6480;
    tint.alpha = 0.72;

    const mask = this.decor.submersionMask(painted.width * 2, submerged);
    mask.position.set(anchor.x - painted.width, anchor.y - submerged);
    layer.addChild(mask);
    tint.mask = mask;
    layer.addChild(tint);
  }

  /** Спрайт террейна, обрезанный по фигуре. */
  private terrainSprite(element: SceneElement): Sprite | null {
    const texture = this.textures.get(element.asset?.publicId ?? null);
    if (texture === null) {
      return null;
    }

    const bounds = sceneGeometryService.bounds(element.shape);
    if (bounds.width <= 0 || bounds.height <= 0) {
      return null;
    }

    const sprite = new Sprite(texture);
    sprite.position.set(bounds.x, bounds.y);
    sprite.width = bounds.width;
    sprite.height = bounds.height;

    const mask = new Graphics();
    this.painter.paint(mask, element.shape, undefined, undefined, this.zoom());
    mask.fill({ color: 0xffffff });
    sprite.mask = mask;
    this.layers.terrain?.addChild(mask);

    return sprite;
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
    if (this.model === null || !this.isEdit()) {
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

      if (element.shape.type === 'polygon' || element.shape.type === 'path') {
        this.drawVertices(layer, element.shape, element.id);
      }
    }
  }

  /** Вершины выделенной фигуры и ручки активной вершины. */
  private drawVertices(layer: Container, shape: SceneShape, elementId: number): void {
    if (shape.type !== 'polygon' && shape.type !== 'path') {
      return;
    }

    const zoom = this.zoom();
    const size = 8 / zoom;
    const focus = this.vertexFocus;
    const vertices = new Graphics();
    for (let index = 0; index < shape.points.length; index += 1) {
      const [x, y] = shape.points[index];
      const isActive = focus !== null && focus.elementId === elementId && focus.index === index;
      vertices
        .rect(x - size / 2, y - size / 2, size, size)
        .fill({ color: isActive ? 0x38bdf8 : 0xfacc15, alpha: 1 })
        .stroke({ color: 0x1f2937, width: 1.5 / zoom, alpha: 1 });
    }

    layer.addChild(vertices);
    if (focus === null || focus.elementId !== elementId || focus.index >= shape.points.length) {
      return;
    }

    const handles = sceneCurveService.normalizeHandles(shape.points, shape.handles);
    const display = sceneCurveService.displayHandles(
      shape.points,
      handles,
      focus.index,
      16 / zoom,
      shape.type === 'polygon',
    );
    const vertex = shape.points[focus.index];
    const gizmo = new Graphics();
    const radius = 4 / zoom;
    for (const side of ['in', 'out'] as const) {
      const target = display[side];
      const isStub = side === 'in' ? display.inIsStub : display.outIsStub;
      gizmo
        .moveTo(vertex[0], vertex[1])
        .lineTo(target[0], target[1])
        .stroke({ color: 0xb39ddb, width: 1.5 / zoom, alpha: isStub ? 0.55 : 0.9 })
        .circle(target[0], target[1], radius)
        .fill({ color: isStub ? 0x0f172a : 0x8e24aa, alpha: 1 })
        .stroke({ color: 0x8e24aa, width: 1.5 / zoom, alpha: 1 });
    }

    if (shape.type === 'path') {
      const widthPoint = sceneRibbonService.widthHandlePoint(
        shape.points,
        handles,
        shape.widths,
        focus.index,
        10 / zoom,
      );
      const half = size;
      gizmo
        .moveTo(widthPoint[0], widthPoint[1] - half)
        .lineTo(widthPoint[0] + half, widthPoint[1])
        .lineTo(widthPoint[0], widthPoint[1] + half)
        .lineTo(widthPoint[0] - half, widthPoint[1])
        .closePath()
        .fill({ color: 0x22d3ee, alpha: 1 })
        .stroke({ color: 0x0f172a, width: 1.5 / zoom, alpha: 1 });
    }

    layer.addChild(gizmo);
  }

  /** Рамка поля поверх содержимого в режиме редактирования. */
  private drawBoardFrame(board: SceneBoard): void {
    const layer = this.layers.gizmos;
    if (layer === undefined || this.model?.mode !== 'edit') {
      return;
    }

    const zoom = this.zoom();
    const graphics = new Graphics();
    graphics.rect(0, 0, board.boundWidth, board.boundHeight).stroke({ color: 0xe2e8f0, width: 2 / zoom, alpha: 0.85 });

    const tick = 10 / zoom;
    const corners: [number, number, number, number][] = [
      [0, 0, 1, 1],
      [board.boundWidth, 0, -1, 1],
      [board.boundWidth, board.boundHeight, -1, -1],
      [0, board.boundHeight, 1, -1],
    ];
    for (const [x, y, dx, dy] of corners) {
      graphics
        .moveTo(x, y)
        .lineTo(x + tick * dx, y)
        .moveTo(x, y)
        .lineTo(x, y + tick * dy)
        .stroke({ color: 0x38bdf8, width: 3 / zoom, alpha: 1 });
    }

    layer.addChild(graphics);
  }

  /** Превью фигуры во время протяжки. */
  private drawDraft(): void {
    if (this.draftGraphics !== null) {
      this.draftGraphics.destroy();
      this.draftGraphics = null;
    }

    const layer = this.layers.gizmos;
    if (layer === undefined || this.model?.mode !== 'edit' || this.draftShape === null) {
      return;
    }

    const graphics = new Graphics();
    this.painter.paint(
      graphics,
      this.draftShape,
      { color: 0x38bdf8, alpha: 0.2 },
      { color: 0x7dd3fc, width: 2 / this.zoom(), alpha: 1 },
      this.zoom(),
    );
    this.draftGraphics = graphics;
    layer.addChild(graphics);
  }

  private zoom(): number {
    return this.viewport?.zoom ?? 1;
  }
}
