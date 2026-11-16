import { Application, Container, Geometry, Graphics, Mesh, Sprite, Texture } from 'pixi.js';
import type { Shader } from 'pixi.js';
import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneGrassTuft } from '@/modules/Roleplay/Scene/Dto/SceneGrassTuft';
import type {
  SceneOverlayBand,
  SceneOverlayMask,
  SceneOverlayPaint,
} from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVertexPart, SceneVertexTarget } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
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
import { sceneRampBlockedShare } from '@/modules/Roleplay/Scene/Service/SceneDeck';
import {
  sceneDecorFootprint,
  sceneDecorSilhouette,
  sceneFootprintShape,
  sceneSilhouetteShape,
  type SceneWorldFootprint,
} from '@/modules/Roleplay/Scene/Service/SceneDecorFootprint';
import { SceneGrassMeshService } from '@/modules/Roleplay/Scene/Service/SceneGrassMeshService';
import { SceneGrassService } from '@/modules/Roleplay/Scene/Service/SceneGrassService';
import { SceneGrassShader } from '@/modules/Roleplay/Scene/Service/SceneGrassShader';
import type { SceneGrassShaderHandle } from '@/modules/Roleplay/Scene/Service/SceneGrassShader';
import { SceneGrassTextureService } from '@/modules/Roleplay/Scene/Service/SceneGrassTextureService';
import { SceneGrassTrailService } from '@/modules/Roleplay/Scene/Service/SceneGrassTrailService';
import { isSceneRoof, sceneWaterDepth } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import { SceneTerrainBlendService } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendService';
import { SceneTerrainPainter } from '@/modules/Roleplay/Scene/Service/SceneTerrainPainter';
import { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneTextureCache } from '@/modules/Roleplay/Scene/Service/SceneTextureCache';
import { sceneTerrainRank } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { sceneWallService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallService';
import { SceneWaterService, isShaderWater } from '@/modules/Roleplay/Scene/Service/SceneWaterService';
import { SCENE_RENDERER_PREFERENCE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_RENDERER_PREFERENCE';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';

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

  /**
   * Мир родительского поля вокруг поля-этажа.
   *
   * Этаж — отдельное поле, но окно смотрит в поляну родителя. Поэтому вокруг
   * дочернего поля рисуется контекст: террейн, стены и декор родителя, сжатые
   * вложением в координаты этажа. Слой лежит ниже всех слоёв активного поля.
   */
  private context: Container | null = null;

  private contextLayers: Partial<Record<SceneLayerName, Container>> = {};

  private model: SceneRenderModel | null = null;

  /** Границы активного поля: по ним открываются концы рек, упёршиеся в край. */
  private boardBounds: SceneBounds | null = null;

  private viewport: SceneViewport | null = null;

  private draftShape: SceneShape | null = null;

  private draftGraphics: Graphics | null = null;

  /** Точка под курсором в предпросмотре: подсветка площадки перехода. */
  private hoverPoint: ScenePoint | null = null;

  /** Нарисованная подсветка площадки; своя, чтобы не чистить чужие gizmos. */
  private hoverGraphics: Graphics | null = null;

  private vertexFocus: SceneVertexTarget | null = null;

  private readonly textures = new SceneTextureCache();

  /** Видимые рамки ассетов: по ним hit-test считает аспект видимой массы. */
  private readonly contentBoxes = new Map<string, ImageContentBox>();

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

  /** Акторы родительского поля: видны вокруг этажа, но не кликаются. */
  private contextActors: SceneActor[] = [];

  /** Нарисованные контекстные акторы: снимаются при пересборке контекста. */
  private contextActorNodes: SceneActorPaint[] = [];

  private readonly propY = new Map<Container, number>();

  /** Готовые полосы оверлеев; их считает сервис, рендерер только рисует. */
  private overlayPaint: SceneOverlayPaint | null = null;

  /** Маска тумана и её текстура: пересобираются только при новой видимости. */
  private fogMask: SceneOverlayMask | null = null;

  private fogTexture: Texture | null = null;

  /** Маршрут выбранного актора с участками по уровням. */
  private route: SceneNavRoute | null = null;

  /** Идёт жест редактирования: траву не перестраиваем, иначе река тормозит. */
  private editing = false;

  private readonly water = new SceneWaterService(this.materials);

  private waterNodes: WaterEntry[] = [];

  private waterAnimationEnabled = true;

  /** Крыши скрыты: тумблер обзора, а не свойство локации. */
  private roofsHidden = false;

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

  /** Запрошенный через rAF кадр, пока тикер не запущен. */
  private frameHandle: number | null = null;

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
    this.requestFrame();
  }

  async setScene(model: SceneRenderModel): Promise<void> {
    this.model = model;
    this.contentBoxes.clear();
    for (const element of model.elements) {
      if (element.asset === null) {
        continue;
      }

      const box = element.asset.contentBox;
      if (box !== null && box !== undefined) {
        this.contentBoxes.set(element.asset.publicId, box);
      }
    }

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
      this.requestFrame();

      return;
    }

    const board = model.boards.find((item) => item.id === model.activeBoardId);
    if (board === undefined) {
      this.boardBounds = null;
      this.clearAll();
      this.requestFrame();

      return;
    }

    await Promise.all([this.textures.load(this.textureIds(model, board)), this.materials.load()]);
    if (token !== this.sceneToken || this.app === null) {
      return;
    }

    this.rebuild(board);
    this.applyViewport();
    this.requestFrame();
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
      this.requestFrame();
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

    this.requestFrame();
  }

  /** Акторы соседнего поля: контекст этажа, не кликаются. */
  setContextActors(actors: SceneActor[]): void {
    this.contextActors = actors;
    this.paintContextActors();
    this.requestFrame();
  }

  /** Готовые полосы тактических оверлеев; null — оверлеи выключены. */
  setOverlayPaint(paint: SceneOverlayPaint | null): void {
    this.overlayPaint = paint;
    this.drawOverlay();
    this.requestFrame();
  }

  /** Маршрут выбранного актора; null — линия скрыта. */
  setRoute(route: SceneNavRoute | null): void {
    this.route = route;
    this.drawRoute();
    this.requestFrame();
  }

  setSelection(ids: number[]): void {
    if (this.model === null) {
      return;
    }

    this.model = { ...this.model, selection: ids };
    this.drawSelection();
    this.requestFrame();
  }

  setDraft(shape: SceneShape | null): void {
    this.draftShape = shape;
    this.drawDraft();
    this.requestFrame();
  }

  setVertexFocus(target: SceneVertexTarget | null): void {
    this.vertexFocus = target;
    this.drawSelection();
    this.requestFrame();
  }

  /**
   * Соотношение сторон текстуры ассета: высота / ширина.
   *
   * Нужно hit-test: спрайт рисуется выше якоря, и клик по кроне должен
   * выделять дерево, а не искать точку под стволом.
   */
  assetAspect(publicId: string | null): number | null {
    if (publicId !== null) {
      const box = this.contentBoxes.get(publicId);
      if (box !== undefined) {
        return box.height / Math.max(1, box.width);
      }
    }

    const texture = this.textures.get(publicId);

    return texture === null ? null : texture.height / Math.max(1, texture.width);
  }

  /** Анимация воды — пользовательская настройка, а не свойство локации. */
  setWaterAnimation(enabled: boolean): void {
    if (this.waterAnimationEnabled === enabled) {
      return;
    }

    this.waterAnimationEnabled = enabled;
    this.syncWaterAnimation();
  }

  /**
   * Крыши скрыты: в редакторе это доступ к комнатам и полу, на карте — взгляд
   * внутрь. Слой целиком, без пересборки: элементы остаются на месте.
   */
  setRoofsHidden(hidden: boolean): void {
    this.roofsHidden = hidden;
    const layer = this.layers.roofs;
    if (layer === undefined) {
      return;
    }

    layer.visible = !hidden;
    this.requestFrame();
  }

  /** Подсветка площадки перехода под курсором; null — убрать. */
  setHoverPoint(point: ScenePoint | null): void {
    this.hoverPoint = point;
    this.drawPortalHover();
    this.requestFrame();
  }

  /**
   * Подсветка площадки перехода.
   *
   * Площадка — проём с целевым полем. Наведение мыши делает её заметной:
   * рамка и заливка в цвет выделения. Это подсказка, а не выбор: клик по
   * площадке по-прежнему отправляет актора к ней.
   */
  private drawPortalHover(): void {
    if (this.hoverGraphics !== null) {
      this.hoverGraphics.removeFromParent();
      this.hoverGraphics.destroy();
      this.hoverGraphics = null;
    }

    if (this.model === null || this.hoverPoint === null) {
      return;
    }

    const layer = this.layers.gizmos;
    if (layer === undefined) {
      return;
    }

    const point = this.hoverPoint;
    const portal = this.model.elements.find(
      (element) =>
        element.boardId === this.model?.activeBoardId &&
        element.kind === 'opening' &&
        element.targetBoardId !== null &&
        sceneGeometryService.contains(element.shape, point, 0, 0),
    );
    if (portal === undefined) {
      return;
    }

    const graphics = new Graphics();
    if (portal.shape.type === 'rect') {
      graphics
        .rect(portal.shape.x, portal.shape.y, portal.shape.w, portal.shape.h)
        .fill({ color: 0xfacc15, alpha: 0.18 })
        .stroke({ color: 0xfacc15, width: Math.max(1, 4 / this.zoom()), alpha: 0.95 });
    } else {
      this.painter.paint(
        graphics,
        portal.shape,
        undefined,
        { color: 0xfacc15, width: Math.max(1, 6 / this.zoom()), alpha: 0.95 },
        this.zoom(),
      );
    }

    layer.addChild(graphics);
    this.hoverGraphics = graphics;
  }

  /** Немедленный кадр. Сеттеры просят кадр через `requestFrame`, а не рисуют сами. */
  render(): void {
    if (this.app === null || this.contextLost) {
      return;
    }

    this.app.render();
  }

  /**
   * Просит кадр вместо немедленного рендера.
   *
   * Сеттеры приходят пачкой — акторы, маршрут, оверлей, — а тикер воды и следа
   * всё равно рисует сцену раз в кадр. Поэтому сеттер только помечает, что
   * кадр нужен: с запущенным тикером его отрисует тикер, без тикера — один
   * `requestAnimationFrame`. Иначе сцена уходила в GPU дважды за кадр, и
   * движение дёргалось.
   */
  private requestFrame(): void {
    if (this.app === null || this.contextLost) {
      return;
    }

    // Тикер сам вызовет `app.render` в конце кадра; второй рендер не нужен.
    if (this.app.ticker.started || this.frameHandle !== null) {
      return;
    }

    this.frameHandle = requestAnimationFrame(() => {
      this.frameHandle = null;
      this.render();
    });
  }

  /** Снимает запрошенный кадр: его заменит тикер или немедленный рендер. */
  private cancelFrame(): void {
    if (this.frameHandle === null) {
      return;
    }

    cancelAnimationFrame(this.frameHandle);
    this.frameHandle = null;
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
      // Тикер отрисует кадр сам; запрошенный rAF больше не нужен.
      this.cancelFrame();
      this.app.ticker.start();

      return;
    }

    if (this.app.ticker.started) {
      this.app.ticker.stop();
      this.cancelFrame();
      this.render();
    }
  }

  /** Снимает обработчики тикера перед detach/destroy. */
  private stopTickers(): void {
    this.cancelFrame();
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
    this.releaseFogTexture();
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
    // Контекст родителя — под слоями активного поля. Порядок внутри него
    // повторяет мир: террейн, вода, острова, опоры, препятствия, проёмы, пол,
    // декор.
    this.context = new Container();
    this.context.label = 'scene-context';
    this.context.visible = false;
    this.world.addChild(this.context);
    this.contextLayers = {};
    const contextOrder: SceneLayerName[] = [
      'terrain',
      'waterPlane',
      'islands',
      'supports',
      'obstacles',
      'openings',
      'ground',
      'decor',
    ];
    for (const name of contextOrder) {
      const layer = new Container();
      layer.label = `context-${name}`;
      this.context.addChild(layer);
      this.contextLayers[name] = layer;
    }

    const order: SceneLayerName[] = [
      'backdrop',
      'grid',
      'terrain',
      'waterPlane',
      'islands',
      'supports',
      'obstacles',
      'openings',
      'ground',
      'decor',
      'roofs',
      'overlay',
      'fog',
      'route',
      'selection',
      'gizmos',
    ];
    for (const name of order) {
      const layer = new Container();
      layer.label = name;
      if (name === 'roofs') {
        layer.visible = !this.roofsHidden;
      }
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

    const context = this.contextOf(board);
    if (context === null) {
      if (this.context !== null) {
        this.context.visible = false;
      }
      this.drawBackdrop(board);
    } else {
      this.drawContext(board, context);
    }
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
          sceneTerrainRank(left) - sceneTerrainRank(right) || left.sortOrder - right.sortOrder || left.id - right.id,
      );
    const resolution = this.model?.resolution ?? 64;
    const zoom = this.zoom();
    const waterElements = terrainElements.filter((element) => element.props.kind === 'water');
    const islandElements = terrainElements.filter((element) => element.props.overWater === true);
    for (const element of terrainElements) {
      if (element.props.overWater === true) {
        continue;
      }
      this.drawTerrain(element);
    }

    for (const element of waterElements) {
      this.drawWaterPlane(element, resolution, zoom);
      this.drawWaterFoam(element, resolution, zoom);
    }

    // Остров лежит поверх воды и её плёнки, но под стенами, настилами и декором.
    for (const element of islandElements) {
      this.drawTerrain(element, this.layers.islands);
    }

    const bridgeElements = elements.filter((element) => this.isGroundDeck(element));
    const openings = elements.filter((element) => element.kind === 'opening');
    const decorElements = elements
      .filter((element) => element.kind === 'decor')
      .sort(
        (left, right) =>
          this.decor.anchorY(left) - this.decor.anchorY(right) ||
          left.sortOrder - right.sortOrder ||
          left.id - right.id,
      );
    for (const element of elements) {
      if (element.kind !== 'terrain' && element.kind !== 'decor' && !this.isGroundDeck(element)) {
        this.drawElement(element, openings);
      }
    }

    this.drawWallJoints(elements, board.id);

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

    // Острова и насыпи: на них декор стоит на суше, хотя контур воды его накрывает.
    const dryElements = terrainElements.filter((element) => element.props.overWater === true);

    this.propY.clear();
    this.actorNodes.clear();
    this.carpet = null;
    const bounds = { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
    // Мосты, съезды и лестницы — пол над водой и под оверлеями, декором и
    // акторами. Съезду нужна доля низкой части: под ней насыпь, а не просвет.
    const heightElements = [...terrainElements, ...bridgeElements];
    for (const element of bridgeElements) {
      const blockedShare =
        element.props.kind === 'ramp' ? sceneRampBlockedShare(element, heightElements, resolution) : 0;
      const painted = this.bridge.paint(element, resolution, this.zoom(), blockedShare);
      if (painted !== null) {
        ground.addChild(painted);
      }
    }

    // Трава сеется только по террейну, но настил обязан её перекрывать:
    // иначе пучки растут прямо на досках. Трава идёт первой, чтобы более
    // поздние мосты и съезды попали в блокеры раскладки.
    const grassElements = [
      ...terrainElements.filter((element) => element.props.kind === 'grass'),
      ...terrainElements.filter((element) => element.props.kind !== 'grass'),
      ...bridgeElements,
    ];
    const carpet = this.editing
      ? []
      : this.grass.band(this.grass.placeCarpet(grassElements, bounds, resolution), resolution);
    const props = [
      ...carpet.map((band, index) => ({
        anchorY: band.y,
        rank: -1,
        order: 0,
        id: index,
        draw: (): void => {
          const mesh = this.carpetMesh(band.tufts);
          if (mesh !== null) {
            layer.addChild(mesh);
            this.propY.set(mesh, band.y);
          }
        },
      })),
      ...decorElements.map((element) => {
        const anchorY = this.decor.anchorY(element);

        return {
          anchorY,
          rank: 1,
          order: element.sortOrder,
          id: element.id,
          draw: (): void => {
            const group = this.decorGroup(element, waterElements, dryElements, resolution);
            if (group !== null) {
              layer.addChild(group);
              this.propY.set(group, anchorY);
            }
          },
        };
      }),
      ...(this.editing ? [] : this.grass.placeClumps(grassElements, bounds, resolution)).map((tuft) => ({
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

  /**
   * Оверлеи: стоимость и туман — над миром, туман выше стоимости.
   *
   * Стоимость — авторская подсказка, а не часть мира: траву и кроны она может
   * подкрасить, зато непроходимые клетки и скорости читаются поверх декора.
   * Туман — маска мира: дерево и трава за пределами обзора должны темнеть, а
   * не светиться поверх неё, поэтому он лежит выше стоимости.
   */
  private drawOverlay(): void {
    const costs = this.layers.overlay;
    const fog = this.layers.fog;
    if (costs === undefined || fog === undefined) {
      return;
    }

    this.clearLayer('overlay');
    this.clearLayer('fog');
    if (this.overlayPaint === null || this.isEdit()) {
      this.releaseFogTexture();

      return;
    }

    this.paintBands(costs, this.overlayPaint.costs);
    this.paintFog(fog, this.overlayPaint.fog);
  }

  /** Полосы заливки одним `Graphics` на слой: один draw call вместо сотен. */
  private paintBands(layer: Container, bands: readonly SceneOverlayBand[]): void {
    if (bands.length === 0) {
      return;
    }

    const graphics = new Graphics();
    for (const band of bands) {
      graphics.rect(band.x, band.y, band.width, band.height).fill({ color: band.color, alpha: band.alpha });
    }
    layer.addChild(graphics);
  }

  /**
   * Туман одной текстурой: один тексель на клетку и линейная фильтрация.
   *
   * Полосы из прямоугольников давали явные ступеньки на косых тенях. Маска
   * растягивается на сетку с `linear`, поэтому край сглаживается без шейдера
   * и без пересчёта видимости на каждый кадр. Текстура пересобирается только
   * при новой маске: панорама и зум переиспользуют её.
   */
  private paintFog(layer: Container, mask: SceneOverlayMask | null): void {
    if (mask === null) {
      this.releaseFogTexture();

      return;
    }

    if (this.fogTexture === null || this.fogMask !== mask) {
      this.releaseFogTexture();
      this.fogTexture = this.createFogTexture(mask);
      this.fogMask = mask;
    }

    const sprite = new Sprite(this.fogTexture);
    sprite.position.set(mask.x, mask.y);
    sprite.width = mask.columns * mask.cell;
    sprite.height = mask.rows * mask.cell;
    layer.addChild(sprite);
  }

  /** Маска в текстуру: цвет тумана и альфа по клеткам. */
  private createFogTexture(mask: SceneOverlayMask): Texture {
    const canvas = document.createElement('canvas');
    canvas.width = mask.columns;
    canvas.height = mask.rows;
    const context = canvas.getContext('2d');
    if (context === null) {
      return Texture.EMPTY;
    }

    const image = context.createImageData(mask.columns, mask.rows);
    const red = (mask.color >> 16) & 0xff;
    const green = (mask.color >> 8) & 0xff;
    const blue = mask.color & 0xff;
    for (let index = 0; index < mask.alpha.length; index += 1) {
      const offset = index * 4;
      image.data[offset] = red;
      image.data[offset + 1] = green;
      image.data[offset + 2] = blue;
      image.data[offset + 3] = mask.alpha[index];
    }
    context.putImageData(image, 0, 0);

    const texture = Texture.from(canvas);
    texture.source.scaleMode = 'linear';

    return texture;
  }

  /** Освобождает текстуру тумана: спрайты слоя уже сняты. */
  private releaseFogTexture(): void {
    this.fogMask = null;
    if (this.fogTexture === null) {
      return;
    }

    this.fogTexture.destroy(true);
    this.fogTexture = null;
  }

  /** Линия маршрута: над миром и туманом, под ручками редактирования. */
  private drawRoute(): void {
    const layer = this.layers.route;
    if (layer === undefined) {
      return;
    }

    this.clearLayer('route');
    if (this.route === null || this.route.points.length < 2 || this.isEdit()) {
      return;
    }

    const zoom = Math.max(0.001, this.zoom());
    const open = new Graphics();
    const covered = new Graphics();
    for (const segment of this.route.segments) {
      if (segment.points.length < 2) {
        continue;
      }

      const target = segment.covered ? covered : open;
      const first = segment.points[0];
      target.moveTo(first[0], first[1]);
      for (const point of segment.points.slice(1)) {
        target.lineTo(point[0], point[1]);
      }
    }

    // Участок под настилом ведёт по земле: он приглушён и тоньше, чтобы не
    // читаться как дорога поверх моста.
    open.stroke({ color: 0x60a5fa, width: 3 / zoom, alpha: 0.85 });
    covered.stroke({ color: 0x1d4ed8, width: 2 / zoom, alpha: 0.4 });
    layer.addChild(open);
    layer.addChild(covered);

    // Точки стыков: вход на настил и спуск с него.
    const dots = new Graphics();
    for (const segment of this.route.segments) {
      const first = segment.points[0];
      const last = segment.points[segment.points.length - 1];
      dots.circle(first[0], first[1], 2.5 / zoom).fill({ color: 0xbfdbfe, alpha: 0.95 });
      dots.circle(last[0], last[1], 2.5 / zoom).fill({ color: 0xbfdbfe, alpha: 0.95 });
    }
    layer.addChild(dots);
  }

  /**
   * Меш одной полосы ковра.
   *
   * Шейдер общий: юниформы следа одинаковы для всех полос, а геометрия у
   * каждой своя. Так ковёр остаётся несколькими draw call, но сортируется
   * по `y` вместе с высокими пучками и акторами.
   */
  private carpetMesh(tufts: readonly SceneGrassTuft[]): Mesh<Geometry, Shader> | null {
    if (tufts.length === 0) {
      return null;
    }

    const atlas = this.grassTextures.atlasTexture();
    if (atlas === null) {
      return null;
    }

    const mesh = this.grassMesh.build(tufts, this.grassTextures.frameRects());
    if (mesh === null) {
      return null;
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
    if (this.carpet === null) {
      this.carpet = this.grassShader.create(atlas);
      this.applyTrail();
    }

    return new Mesh({ geometry, shader: this.carpet.shader });
  }

  /** Передаёт след движения в шейдер ковра; без меша и модели — no-op. */
  private applyTrail(): void {
    if (this.carpet === null || this.model === null) {
      return;
    }

    this.carpet.apply(this.grassTrail.pack(this.model.resolution));
  }

  /** Группа декора: тень, спрайт и погружение — один ребёнок слоя. */
  private decorGroup(
    element: SceneElement,
    waterElements: SceneElement[],
    dryElements: SceneElement[],
    resolution: number,
  ): Container | null {
    const group = new Container();
    const painted = this.decor.paint(group, element, resolution);
    if (painted === null) {
      group.addChild(this.painter.paintElement(element, this.zoom()));
    } else {
      this.paintSubmersion(group, element, painted, waterElements, dryElements, resolution);
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
        this.placeActor(created.node, this.actorSortY(actor));
        continue;
      }

      this.actorService.update(painted, actor, zoom);
      this.placeActor(painted.node, this.actorSortY(actor));
    }
  }

  /**
   * Ключ Y-сортировки актора.
   *
   * Низ footprint, а не точка под ногами: трава, попавшая под круг актора,
   * должна уйти за него, а не расти из середины силуэта.
   */
  private actorSortY(actor: SceneActor): number {
    return actor.y + actor.radius;
  }

  /** Акторы родителя в контексте этажа: приглушённые, без клика. */
  private paintContextActors(): void {
    for (const painted of this.contextActorNodes) {
      painted.node.removeFromParent();
      painted.node.destroy({ children: true });
    }
    this.contextActorNodes = [];

    if (this.model === null || this.context === null || !this.context.visible) {
      return;
    }

    const layer = this.contextLayers.decor;
    if (layer === undefined) {
      return;
    }

    const resolution = this.model.resolution;
    const zoom = this.zoom();
    for (const actor of this.contextActors) {
      const painted = this.actorService.paint(actor, resolution, zoom);
      layer.addChild(painted.node);
      this.contextActorNodes.push(painted);
    }
  }

  /**
   * Ставит актора на его `y` среди статичных детей слоя.
   *
   * Дети слоя отсортированы по `y` при сборке, поэтому позиция ищется двоичным
   * поиском. Линейный проход по лесу из десятков тысяч спрайтов на каждого
   * актора каждый кадр — это уже узкое место, даже без перерисовки.
   */
  private placeActor(node: Container, y: number): void {
    const layer = this.layers.decor;
    if (layer === undefined) {
      return;
    }

    const children = layer.children;
    let low = 0;
    let high = children.length;
    while (low < high) {
      const middle = (low + high) >> 1;
      if ((this.propY.get(children[middle]) ?? 0) <= y) {
        low = middle + 1;
      } else {
        high = middle;
      }
    }

    const current = children.indexOf(node);
    // Узел ещё стоит в списке со старым `y`: после его снятия позиция сдвигается.
    const index = current >= 0 && current < low ? low - 1 : low;
    if (current !== index) {
      layer.setChildIndex(node, index);
    }

    this.propY.set(node, y);
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
    this.contextActorNodes = [];
    for (const name of Object.keys(this.layers) as SceneLayerName[]) {
      this.clearLayer(name);
    }

    // Контекст родителя живёт отдельным набором слоёв: чистим его так же,
    // иначе после смены поля остались бы тени прошлого родителя.
    const saved = this.layers;
    this.layers = this.contextLayers;
    for (const name of Object.keys(this.contextLayers) as SceneLayerName[]) {
      this.clearLayer(name);
    }
    this.layers = saved;
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
      this.hoverGraphics = null;
    }

    if (name === 'terrain' || name === 'waterPlane') {
      this.waterNodes = [];
    }
  }

  /** Обрезает содержимое поля по его границам; gizmos остаются снаружи. */
  private applyContentClip(board: SceneBoard): void {
    const names: SceneLayerName[] = [
      'terrain',
      'waterPlane',
      'islands',
      'supports',
      'obstacles',
      'openings',
      'ground',
      'decor',
      'roofs',
      'overlay',
      'fog',
      'route',
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
  private drawTerrain(element: SceneElement, layer: Container | undefined = this.layers.terrain): void {
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
      // Откос рисуется под телом воды: видимой остаётся только полоса снаружи
      // уреза. Шейдерная вода тоже получает откос — он лежит на берегу, а не
      // на поверхности, и не спорит с мокрым краем шейдера.
      this.terrain.paintBankSlope(layer, element, resolution, zoom, sceneWaterDepth(element), endPads);
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
      const sprite = this.terrainSprite(element, layer);
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

  /** Настил, который рисуется в слое `ground`: мост, съезд, лестница. */
  private isGroundDeck(element: SceneElement): boolean {
    return (
      element.kind === 'support' &&
      (element.props.kind === 'bridge' || element.props.kind === 'ramp' || element.props.kind === 'stairs')
    );
  }

  /**
   * Родитель и область вложения для поля-этажа.
   *
   * `null` — поле не вложено ни в какое другое: обычное поле рисуется как
   * раньше, без контекста.
   */
  private contextOf(board: SceneBoard): { parent: SceneBoard; footprint: SceneBounds } | null {
    if (this.model === null) {
      return null;
    }

    const enclosure = this.model.enclosures.find((item) => item.childBoardId === board.id);
    if (enclosure === undefined) {
      return null;
    }

    const parent = this.model.boards.find((item) => item.id === enclosure.parentBoardId);
    if (parent === undefined) {
      return null;
    }

    const footprint = this.footprintRect(enclosure.footprint);
    if (footprint === null || footprint.width <= 0 || footprint.height <= 0) {
      return null;
    }

    return { parent, footprint };
  }

  /** Прямоугольник пятна вложения: у полигона берётся габарит. */
  private footprintRect(shape: SceneShape): SceneBounds | null {
    if (shape.type === 'rect') {
      return { x: shape.x, y: shape.y, width: shape.w, height: shape.h };
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

    return { x: minX, y: minY, width: maxX - minX, height: maxY - minY };
  }

  /**
   * Мир родителя вокруг поля-этажа.
   *
   * Рисуется в отдельные слои контекста, сжатые пятном вложения в координаты
   * этажа. Крыши и вода родителя пропускаются: в поляне вокруг дома их нет, а
   * накрытие скрыло бы план. Слои активного поля ложатся поверх, поэтому пол
   * этажа закрывает внутренность дома, а поляна остаётся по краям.
   */
  private drawContext(board: SceneBoard, context: { parent: SceneBoard; footprint: SceneBounds }): void {
    if (this.context === null || this.model === null) {
      return;
    }

    const scaleX = board.boundWidth / context.footprint.width;
    const scaleY = board.boundHeight / context.footprint.height;
    if (!(scaleX > 0) || !(scaleY > 0)) {
      this.context.visible = false;

      return;
    }

    this.context.visible = true;
    this.context.scale.set(scaleX, scaleY);
    this.context.position.set(-context.footprint.x * scaleX, -context.footprint.y * scaleY);

    const savedLayers = this.layers;
    const savedBounds = this.boardBounds;
    this.layers = this.contextLayers;
    this.boardBounds = { x: 0, y: 0, width: context.parent.boundWidth, height: context.parent.boundHeight };
    const resolution = this.model.resolution;
    const elements = this.model.elements.filter(
      (element) => element.boardId === context.parent.id && !isSceneRoof(element),
    );
    const terrain = elements.filter(
      (element) => element.kind === 'terrain' && element.props.kind !== 'water' && element.props.overWater !== true,
    );
    const islands = elements.filter((element) => element.kind === 'terrain' && element.props.overWater === true);
    for (const element of terrain) {
      this.drawTerrain(element, this.contextLayers.terrain);
    }
    for (const element of islands) {
      this.drawTerrain(element, this.contextLayers.islands);
    }

    const openings = elements.filter((element) => element.kind === 'opening');
    for (const element of elements) {
      if (element.kind === 'terrain' || element.kind === 'decor') {
        continue;
      }
      if (this.isGroundDeck(element)) {
        const painted = this.bridge.paint(element, resolution, this.zoom(), 0);
        if (painted !== null) {
          this.contextLayers.ground?.addChild(painted);
        }
        continue;
      }
      this.drawElement(element, openings);
    }
    this.drawWallJoints(elements, context.parent.id);

    const water = elements.filter((element) => element.kind === 'terrain' && element.props.kind === 'water');
    const decor = elements
      .filter((element) => element.kind === 'decor')
      .sort(
        (left, right) =>
          this.decor.anchorY(left) - this.decor.anchorY(right) ||
          left.sortOrder - right.sortOrder ||
          left.id - right.id,
      );
    for (const element of decor) {
      const group = this.decorGroup(element, water, islands, resolution);
      if (group !== null) {
        this.contextLayers.decor?.addChild(group);
      }
    }

    this.paintContextActors();

    this.applyContentClip(context.parent);
    this.layers = savedLayers;
    this.boardBounds = savedBounds;
  }

  private drawElement(element: SceneElement, openings: readonly SceneElement[]): void {
    const layerName = this.layerFor(element);
    const layer = this.layers[layerName];
    if (layer === undefined) {
      return;
    }

    // Стена режется проёмами: под дверью и окном её полосы нет.
    if (element.kind === 'obstacle' && element.shape.type === 'wall') {
      for (const segment of sceneWallService.segments(element, openings)) {
        layer.addChild(this.painter.paintWall(segment, this.zoom()));
      }

      return;
    }

    layer.addChild(this.painter.paintElement(element, this.zoom()));
  }

  /** Уголки стыков: закрывают щели между отдельными лентами стен. */
  private drawWallJoints(elements: readonly SceneElement[], boardId: number): void {
    const layer = this.layers.obstacles;
    if (layer === undefined) {
      return;
    }

    for (const joint of sceneWallService.joints(elements, boardId)) {
      layer.addChild(this.painter.paintWallJoint(joint.point, joint.width, this.zoom()));
    }
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
    dryElements: SceneElement[],
    resolution: number,
  ): void {
    if (element.shape.type !== 'point') {
      return;
    }

    const anchor = element.shape;
    const point: ScenePoint = [anchor.x, anchor.y];
    // Остров лежит поверх воды: дерево на нём стоит на суше, хотя формально
    // попадает внутрь контура озера.
    if (dryElements.some((dry) => sceneGeometryService.contains(dry.shape, point, 0, sceneRotation(dry)))) {
      return;
    }

    let depth = 0;
    for (const waterElement of waterElements) {
      depth = Math.max(
        depth,
        this.water.depthAt(waterElement, resolution, point, this.openEndPads(waterElement, resolution)),
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
  private terrainSprite(element: SceneElement, layer: Container): Sprite | null {
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
    layer.addChild(mask);

    return sprite;
  }

  private layerFor(element: SceneElement): SceneLayerName {
    if (element.kind === 'terrain') return 'terrain';
    // Крыша — накрытие: она лежит над стенами и декором, а не среди опор.
    if (isSceneRoof(element)) return 'roofs';
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
    // У группы рисуется общий габарит, а не ручки каждого элемента: иначе
    // выделенный дом превращается в частокол вершин.
    const group = selected.length > 1;
    for (const element of selected) {
      const graphics = new Graphics();
      this.painter.paint(
        graphics,
        element.shape,
        undefined,
        group
          ? { color: 0xfacc15, width: 1.5 / this.zoom(), alpha: 0.45 }
          : { color: 0xfacc15, width: 3 / this.zoom(), alpha: 1 },
        this.zoom(),
        sceneRotation(element),
      );
      layer.addChild(graphics);

      if (group) {
        continue;
      }

      if (element.kind === 'decor' && element.shape.type === 'point') {
        this.drawDecorContours(layer, element, this.model.resolution);
        const footprint = sceneFootprintShape(element, this.model.resolution);
        if (footprint !== null) {
          this.drawVertices(layer, footprint, element.id, 'footprint');
        }

        const silhouette =
          element.props.blocksVision === false || element.props.silhouette === undefined
            ? null
            : sceneSilhouetteShape(element, this.model.resolution);
        if (silhouette !== null) {
          this.drawVertices(layer, silhouette, element.id, 'silhouette');
        }
      }

      if (element.shape.type === 'polygon' || element.shape.type === 'path' || element.shape.type === 'wall') {
        this.drawVertices(layer, element.shape, element.id, 'shape');
      }

      if (element.shape.type === 'rect') {
        this.drawRectHandles(layer, element);
      }
    }

    if (group) {
      this.drawGroupBox(layer, selected);
    }
  }

  /** Пунктирный габарит группы: видно, что поедет целиком. */
  private drawGroupBox(layer: Container, elements: readonly SceneElement[]): void {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (const element of elements) {
      const box = sceneGeometryService.bounds(element.shape, sceneRotation(element));
      minX = Math.min(minX, box.x);
      minY = Math.min(minY, box.y);
      maxX = Math.max(maxX, box.x + box.width);
      maxY = Math.max(maxY, box.y + box.height);
    }

    if (!Number.isFinite(minX) || !Number.isFinite(minY)) {
      return;
    }

    const zoom = this.zoom();
    const dash = 6 / zoom;
    const gap = 4 / zoom;
    const graphics = new Graphics();
    const edges: [ScenePoint, ScenePoint][] = [
      [
        [minX, minY],
        [maxX, minY],
      ],
      [
        [maxX, minY],
        [maxX, maxY],
      ],
      [
        [maxX, maxY],
        [minX, maxY],
      ],
      [
        [minX, maxY],
        [minX, minY],
      ],
    ];
    for (const [from, to] of edges) {
      this.dashLine(graphics, from, to, dash, gap);
    }

    graphics.stroke({ color: 0xfacc15, width: 1.5 / zoom, alpha: 0.9 });
    layer.addChild(graphics);
  }

  /** Пунктир по отрезку: штрихи постоянной длины на экране. */
  private dashLine(graphics: Graphics, from: ScenePoint, to: ScenePoint, dash: number, gap: number): void {
    const length = Math.hypot(to[0] - from[0], to[1] - from[1]);
    if (length <= 0) {
      return;
    }

    const step = dash + gap;
    const count = Math.max(1, Math.floor(length / step));
    const unitX = (to[0] - from[0]) / length;
    const unitY = (to[1] - from[1]) / length;
    for (let index = 0; index < count; index += 1) {
      const start = index * step;
      const end = Math.min(length, start + dash);
      graphics.moveTo(from[0] + unitX * start, from[1] + unitY * start);
      graphics.lineTo(from[0] + unitX * end, from[1] + unitY * end);
    }
  }

  /** Восемь ручек размера и ручка поворота у выделенного прямоугольника. */
  private drawRectHandles(layer: Container, element: SceneElement): void {
    if (element.shape.type !== 'rect') {
      return;
    }

    const zoom = this.zoom();
    const size = 8 / zoom;
    const rotation = sceneRotation(element);
    const graphics = new Graphics();
    for (const handle of sceneGeometryService.rectHandles(element.shape, rotation)) {
      graphics
        .rect(handle.point[0] - size / 2, handle.point[1] - size / 2, size, size)
        .fill({ color: 0xfacc15, alpha: 1 })
        .stroke({ color: 0x1f2937, width: 1.5 / zoom, alpha: 1 });
    }

    if (element.kind === 'support') {
      const center: ScenePoint = [element.shape.x + element.shape.w / 2, element.shape.y + element.shape.h / 2];
      const top = this.rotateAround([center[0], element.shape.y], center, rotation);
      const grip = this.rotateAround([center[0], element.shape.y - 24 / zoom], center, rotation);
      graphics
        .moveTo(top[0], top[1])
        .lineTo(grip[0], grip[1])
        .stroke({ color: 0x8e24aa, width: 1.5 / zoom, alpha: 0.9 })
        .circle(grip[0], grip[1], 5 / zoom)
        .fill({ color: 0x8e24aa, alpha: 1 })
        .stroke({ color: 0x1f2937, width: 1.5 / zoom, alpha: 1 });
    }

    layer.addChild(graphics);
  }

  /** Поворот точки вокруг центра на угол в градусах. */
  private rotateAround(point: ScenePoint, center: ScenePoint, degrees: number): ScenePoint {
    const radians = (degrees * Math.PI) / 180;
    const cosine = Math.cos(radians);
    const sine = Math.sin(radians);
    const dx = point[0] - center[0];
    const dy = point[1] - center[1];

    return [center[0] + dx * cosine - dy * sine, center[1] + dx * sine + dy * cosine];
  }

  /**
   * Контур основания и силуэта выделенного декора.
   *
   * Мастер видит, что именно непроходимо и откуда начинается перекрытие
   * обзора: без этого основание — невидимая зона в числах карточки.
   */
  private drawDecorContours(layer: Container, element: SceneElement, resolution: number): void {
    const footprint = sceneDecorFootprint(element, resolution);
    if (footprint !== null) {
      const graphics = new Graphics();
      this.paintFootprint(graphics, footprint, 0xfacc15, 0.9);
      layer.addChild(graphics);
    }

    const silhouette = element.props.blocksVision === false ? null : sceneDecorSilhouette(element, resolution);
    if (silhouette !== null) {
      const graphics = new Graphics();
      this.paintFootprint(graphics, silhouette.area, 0x38bdf8, 0.55);
      layer.addChild(graphics);
    }
  }

  /** Обводит мировую форму основания. */
  private paintFootprint(graphics: Graphics, area: SceneWorldFootprint, color: number, alpha: number): void {
    const width = 2 / this.zoom();
    if (area.shape === 'circle') {
      graphics.circle(area.center[0], area.center[1], area.radius).stroke({ color, width, alpha });

      return;
    }

    graphics
      .poly(
        area.points.flatMap((point) => [point[0], point[1]]),
        true,
      )
      .stroke({ color, width, alpha });
  }

  /** Вершины выделенной фигуры и ручки активной вершины. */
  private drawVertices(layer: Container, shape: SceneShape, elementId: number, part: SceneVertexPart = 'shape'): void {
    if (shape.type !== 'polygon' && shape.type !== 'path' && shape.type !== 'wall') {
      return;
    }

    const zoom = this.zoom();
    const size = 8 / zoom;
    const focus = this.vertexFocus;
    const focused = focus !== null && focus.elementId === elementId && (focus.part ?? 'shape') === part;
    const idleColor = part === 'footprint' ? 0x22d3ee : part === 'silhouette' ? 0x7dd3fc : 0xfacc15;
    const vertices = new Graphics();
    for (let index = 0; index < shape.points.length; index += 1) {
      const [x, y] = shape.points[index];
      const isActive = focused && focus?.index === index;
      vertices
        .rect(x - size / 2, y - size / 2, size, size)
        .fill({ color: isActive ? 0x38bdf8 : idleColor, alpha: 1 })
        .stroke({ color: 0x1f2937, width: 1.5 / zoom, alpha: 1 });
    }

    layer.addChild(vertices);
    if (shape.type === 'wall' || !focused || focus === null || focus.index >= shape.points.length) {
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
