import { Application, Container, Geometry, Graphics, Mesh, Sprite, Texture } from 'pixi.js';
import type { Shader } from 'pixi.js';
import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneGrassTuft } from '@/modules/Roleplay/Scene/Dto/SceneGrassTuft';
import type { SceneGrassInset } from '@/modules/Roleplay/Scene/Dto/SceneGrassInset';
import type {
  SceneOverlayBand,
  SceneOverlayMask,
  SceneOverlayPaint,
} from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { SceneNavRoute, SceneNavSegment } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
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
import { isSceneRoof, isWalkableRoof, sceneWaterDepth } from '@/modules/Roleplay/Scene/Service/SceneHeight';
import type { SceneRoofHatch } from '@/modules/Roleplay/Scene/Service/SceneRoofHatch';
import { sceneRoofHatches } from '@/modules/Roleplay/Scene/Service/SceneRoofHatch';
import { SceneTerrainBlendService } from '@/modules/Roleplay/Scene/Service/SceneTerrainBlendService';
import { SceneTerrainPainter } from '@/modules/Roleplay/Scene/Service/SceneTerrainPainter';
import { SceneFurnitureService } from '@/modules/Roleplay/Scene/Service/SceneFurnitureService';
import { sceneZoomNeedsRebuild } from '@/modules/Roleplay/Scene/Service/SceneZoom';
import { SceneTerrainPatternService } from '@/modules/Roleplay/Scene/Service/SceneTerrainPatternService';
import { SceneTerrainTextureService } from '@/modules/Roleplay/Scene/Service/SceneTerrainTextureService';
import { SceneStairSurfaceService } from '@/modules/Roleplay/Scene/Service/SceneStairSurface';
import { SceneTextureCache } from '@/modules/Roleplay/Scene/Service/SceneTextureCache';
import { sceneTerrainRank } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { sceneIslandShores } from '@/modules/Roleplay/Scene/Service/SceneIslandShoreService';
import type { SceneIslandShore } from '@/modules/Roleplay/Scene/Service/SceneIslandShoreService';
import { SCENE_WALL_MATERIAL } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_WALL_MATERIAL';
import { sceneWallService } from '@/modules/Roleplay/Scene/Service/Instance/sceneWallService';
import { SceneWaterService, isShaderWater } from '@/modules/Roleplay/Scene/Service/SceneWaterService';
import { SCENE_RENDERER_PREFERENCE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_RENDERER_PREFERENCE';
import { SCENE_FLOOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FLOOR';
import { SCENE_ROOF } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ROOF';
import { sceneGeometryService } from '@/modules/Roleplay/Scene/Service/Instance/sceneGeometryService';
import { sceneCurveService } from '@/modules/Roleplay/Scene/Service/Instance/sceneCurveService';
import { sceneRibbonService } from '@/modules/Roleplay/Scene/Service/Instance/sceneRibbonService';
import { sceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/Instance/sceneFloorPlanService';
import { sceneRotation } from '@/modules/Roleplay/Scene/Service/SceneRotation';
import type { SceneFloor, SceneFloorPlan } from '@/modules/Roleplay/Scene/Service/SceneFloorPlanService';

/** Вода и её анимация на видимом этаже. */
interface WaterEntry {
  elementId: number;
  node: SceneWaterNode;
}

/**
 * Материалы, которые могут стать порогом двери, и их приоритет.
 *
 * Порог повторяет пол рядом. Если полы разные с двух сторон, побеждает более
 * «пол»: доски и плитка важнее грунта улицы.
 */
const THRESHOLD_RANK: Record<string, number> = {
  wood: 0,
  tile: 0,
  stone: 1,
  ice: 2,
  dirt: 3,
  sand: 3,
  mud: 3,
};

/** Этаж, нарисованный в своей группе мира корня. */
interface SceneFieldRender {
  boardId: number;
  group: Container;
  layers: Partial<Record<SceneLayerName, Container>>;
  /** Масштаб этажа в мире корня. */
  scale: number;
}

/** Спрайт тумана поля: текстура и её канва переиспользуются между тиками. */
interface FogSpriteHandle {
  sprite: Sprite;
  texture: Texture;
  canvas: HTMLCanvasElement;
  context: CanvasRenderingContext2D | null;
  image: ImageData | null;
  columns: number;
  rows: number;
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

  /** Слои активного этажа: в них рисуются сетка, выделение и ручки. */
  private layers: Partial<Record<SceneLayerName, Container>> = {};

  /**
   * Видимые этажи как группы мира корня.
   *
   * Каждый этаж — своя группа слоёв, преобразованная вложением в координаты
   * корня. Смена этажа меняет состав групп, а не мир: камера остаётся на
   * месте.
   */
  private fields: SceneFieldRender[] = [];

  private activeField: SceneFieldRender | null = null;

  /** Масштаб текущего этажа в мире корня: от него зависят толщины линий. */
  private fieldScale = 1;

  /** Плита активного этажа полупрозрачна: сквозь неё виден этаж ниже. */
  private slabCutaway = true;

  /** План видимых этажей текущей сборки. */
  private floorPlan: SceneFloorPlan | null = null;

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

  /** Мебель: процедурный рисунок сверху. */
  private readonly furniture = new SceneFurnitureService();

  private readonly materials = new SceneTerrainTextureService();

  /** Подложки маршей: материал пола, выложенный досками по проступям. */
  private readonly stairSurfaces = new SceneStairSurfaceService(this.patterns, this.materials);

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

  /** Готовые оверлеи по полям; их считает сервис, рендерер только рисует. */
  private readonly overlayPaints = new Map<number, SceneOverlayPaint>();

  /**
   * Спрайты тумана по полям: живут между тиками, меняется только альфа.
   *
   * Туман считается на всех видимых этажах, поэтому у каждого поля своя
   * текстура в его координатах; общая одна не годится.
   */
  private readonly fogSprites = new Map<number, FogSpriteHandle>();

  /** Полосы стоимости, уже нарисованные на слое поля. */
  private readonly costPaints = new Map<number, readonly SceneOverlayBand[]>();

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
    this.flushPendingRebuild();
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
    this.flushPendingRebuild();
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

  /** Зум, под который собраны текущие слои. */
  private builtZoom = 1;

  /** Слои надо пересобрать под новый зум до следующего кадра. */
  private rebuildPending = false;

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
    // Толщины линий, фаски, тени и пороги считаются от зума при сборке.
    // Пересобираем не сразу, а одним кадром: колесо присылает пачку смен.
    if (previous !== null && Math.abs(previous.zoom - viewport.zoom) > 0.0001 && this.zoomNeedsRebuild(viewport.zoom)) {
      this.rebuildPending = true;
    }

    if (previous !== null && Math.abs(previous.zoom - viewport.zoom) > 0.0001) {
      for (const actor of this.actors) {
        const painted = this.actorNodes.get(actor.id);
        if (painted === undefined) {
          continue;
        }

        const field = this.fieldOf(actor.boardId ?? this.model?.activeBoardId ?? 0);
        this.actorService.update(painted, actor, viewport.zoom * (field?.scale ?? 1));
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

    const plan = sceneFloorPlanService.plan(model);
    if (plan.rootBoardId === null || plan.floors.length === 0) {
      this.boardBounds = null;
      this.clearAll();
      this.requestFrame();

      return;
    }

    await Promise.all([this.textures.load(this.textureIds(model, plan.floors)), this.materials.load()]);
    if (token !== this.sceneToken || this.app === null) {
      return;
    }

    this.rebuild();
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

    this.rebuild();
    this.applyViewport();
    this.requestFrame();
  }

  /** Акторы видимых этажей: позиции и facing приходят снаружи, состояние — у игры. */
  setActors(actors: SceneActor[]): void {
    this.actors = actors;
    this.paintActors();

    if (this.model !== null) {
      // След травы считается в координатах активного поля: чужие этажи в него
      // не подмешиваются, иначе полоса легла бы не туда.
      const activeBoardId = this.model.activeBoardId;
      this.grassTrail.record(
        actors.filter((actor) => (actor.boardId ?? activeBoardId) === activeBoardId),
        this.model.resolution,
      );
      this.applyTrail();
      this.syncTrailTicker();
    }

    this.requestFrame();
  }

  /**
   * Плита активного этажа полупрозрачна: сквозь неё виден этаж ниже.
   *
   * Тумблер обзора, а не свойство локации. Выключен — плита непрозрачна,
   * сквозь неё видны только настоящие проёмы-колодцы.
   */
  setSlabCutaway(enabled: boolean): void {
    if (this.slabCutaway === enabled) {
      return;
    }

    this.slabCutaway = enabled;
    this.rebuild();
    this.applyViewport();
    this.requestFrame();
  }

  /** Готовые оверлеи по полям; null — оверлеи выключены. */
  setOverlayPaint(paints: readonly SceneOverlayPaint[] | null): void {
    this.overlayPaints.clear();
    for (const paint of paints ?? []) {
      this.overlayPaints.set(paint.boardId, paint);
    }

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
    for (const field of this.fields) {
      for (const name of ['roofs', 'deckRoofs'] as const) {
        const layer = field.layers[name];
        if (layer !== undefined) {
          layer.visible = !hidden;
        }
      }
    }

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
        .fill({ color: 0xfacc15, alpha: 0.1 })
        .stroke({ color: 0xfacc15, width: Math.max(1, 2.5 / this.zoom()), alpha: 0.85 });
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

    this.flushPendingRebuild();
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
    this.releaseFogSprites();
    this.costPaints.clear();
    this.grassTrail.reset();
    this.waterNodes = [];
    this.textures.destroy();
    this.patterns.destroy();
    this.materials.destroy();
    this.stairSurfaces.destroy();
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
   * Нужно ли пересобрать слои под новый зум.
   *
   * Сравниваем с зумом, под который они уже собраны, а не с предыдущим
   * событием колеса: при плавном приближении старые толщины линий и тени
   * видны сразу, и ждать двукратного шага нельзя. Порог в один процент
   * отсекает дрожание, но не даёт картинке «поплыть».
   */
  private zoomNeedsRebuild(zoom: number): boolean {
    return this.model !== null && sceneZoomNeedsRebuild(this.builtZoom, zoom);
  }

  /** Пересобирает слои видимых этажей, если зум успел измениться. */
  private flushPendingRebuild(): void {
    if (!this.rebuildPending || this.model === null || this.world === null) {
      return;
    }

    this.rebuildPending = false;
    this.rebuild();
  }

  private createWorld(): void {
    if (this.app === null) {
      return;
    }

    this.world = new Container();
    this.world.label = 'scene-world';
    this.app.stage.addChild(this.world);
    this.layers = {};
    this.fields = [];
    this.activeField = null;
  }

  /**
   * Слои одного этажа в его группе.
   *
   * Порядок снизу вверх повторяет мир: подложка, сетка, террейн, вода,
   * острова, опоры, препятствия, проёмы, пол, декор, крыши и служебные слои.
   */
  private createFieldLayers(group: Container): Partial<Record<SceneLayerName, Container>> {
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
      'deckRoofs',
      'decor',
      'roofs',
      'overlay',
      'fog',
      'route',
      'selection',
      'gizmos',
    ];
    const layers: Partial<Record<SceneLayerName, Container>> = {};
    for (const name of order) {
      const layer = new Container();
      layer.label = name;
      if (name === 'roofs' || name === 'deckRoofs') {
        layer.visible = !this.roofsHidden;
      }
      group.addChild(layer);
      layers[name] = layer;
    }

    return layers;
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

  /**
   * Пересобирает видимые этажи в мире корня.
   *
   * Корень — общая земля; этажи строений идут по возрастанию отметки, активная
   * стопка — последней. Каждый этаж рисуется в своей группе теми же
   * помощниками, что и раньше: они работают в координатах поля, а группа несёт
   * преобразование вложения.
   */
  private rebuild(): void {
    this.clearAll();
    this.rebuildPending = false;
    this.waterNodes = [];
    const model = this.model;
    if (model === null || this.world === null) {
      return;
    }

    const plan = sceneFloorPlanService.plan(model);
    this.floorPlan = plan;
    this.activeField = null;
    for (const floor of plan.floors) {
      const field = this.createField(floor);
      this.fields.push(field);
      if (floor.active) {
        this.activeField = field;
      }
    }

    const activeBoard = model.boards.find((board) => board.id === model.activeBoardId);
    this.layers = this.activeField?.layers ?? {};
    this.fieldScale = this.activeField?.scale ?? 1;
    this.builtZoom = this.zoom();
    this.boardBounds =
      activeBoard === undefined ? null : { x: 0, y: 0, width: activeBoard.boundWidth, height: activeBoard.boundHeight };

    if (activeBoard !== undefined && this.isEdit()) {
      this.drawGrid(activeBoard);
      this.drawGridOverlay(activeBoard);
      this.drawEnclosures(activeBoard);
    }

    this.drawOverlay();
    this.drawRoute();
    this.paintActors();
    this.drawPortalHover();
    this.drawSelection();
    if (activeBoard !== undefined && this.isEdit()) {
      this.drawBoardFrame(activeBoard);
      this.drawDraft();
    }
  }

  /**
   * Группа этажа и его содержимое.
   *
   * @param floor Видимый этаж из плана.
   *
   * @returns Готовая группа в мире корня.
   */
  private createField(floor: SceneFloor): SceneFieldRender {
    const group = new Container();
    group.label = `scene-floor-${floor.boardId}`;
    this.world?.addChild(group);
    const layers = this.createFieldLayers(group);
    group.position.set(floor.transform.x, floor.transform.y);
    group.scale.set(floor.transform.scaleX, floor.transform.scaleY);

    const field: SceneFieldRender = {
      boardId: floor.boardId,
      group,
      layers,
      scale: Math.max(floor.transform.scaleX, floor.transform.scaleY),
    };
    this.drawField(field, floor);

    return field;
  }

  /**
   * Содержимое одного этажа в его собственных координатах.
   *
   * Помощники рисования читают `this.layers` и `this.boardBounds`, поэтому на
   * время этажа они подменяются; после — возвращаются к активному этажу.
   *
   * @param field Группа этажа.
   * @param floor Описание этажа.
   */
  private drawField(field: SceneFieldRender, floor: SceneFloor): void {
    const model = this.model;
    if (model === null) {
      return;
    }

    const board = floor.board;
    const savedLayers = this.layers;
    const savedBounds = this.boardBounds;
    const savedScale = this.fieldScale;
    this.layers = field.layers;
    this.boardBounds = { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
    this.fieldScale = field.scale;

    if (floor.root) {
      this.drawBackdrop(board);
    }

    const elements = model.elements
      .filter((element) => element.boardId === board.id)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.id - right.id);
    const terrainElements = elements
      .filter((element) => element.kind === 'terrain')
      .sort(
        (left, right) =>
          sceneTerrainRank(left) - sceneTerrainRank(right) || left.sortOrder - right.sortOrder || left.id - right.id,
      );
    const resolution = model.resolution;
    const zoom = this.zoom();
    const waterElements = terrainElements.filter((element) => element.props.kind === 'water');
    const islandElements = terrainElements.filter((element) => element.props.overWater === true);
    // Острова — дырки воды: контур суши уходит в поле глубины до того, как
    // вода начнёт печь мель, накат и мокрый песок. Сухой берег рисуется на
    // стороне острова теми же свойствами воды.
    const shores = sceneIslandShores(terrainElements, resolution);
    const beachByIsland = new Map<number, SceneIslandShore>();
    const insets = new Map<number, SceneGrassInset>();
    const holesByWater = new Map<number, ScenePoint[][]>();
    for (const shore of shores) {
      const holes = holesByWater.get(shore.water.id);
      if (holes === undefined) {
        holesByWater.set(shore.water.id, [shore.contour]);
      } else {
        holes.push(shore.contour);
      }

      const width = shore.water.props.shoreWidth ?? 0;
      if ((shore.water.props.shoreKind ?? 'none') === 'none' || width <= 0) {
        continue;
      }

      beachByIsland.set(shore.island.id, shore);
      insets.set(shore.island.id, { contour: shore.contour, distance: width });
    }

    this.water.clearIslands();
    for (const [waterId, holes] of holesByWater) {
      this.water.setIslands(waterId, holes);
    }

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
      const beach = beachByIsland.get(element.id);
      if (beach === undefined) {
        this.drawTerrain(element, this.layers.islands);
        continue;
      }

      // Свой берег у острова не рисуется: воду слушает вода, иначе полос было
      // бы две — сухая снаружи и мель внутри, а урез потерялся бы между ними.
      this.drawTerrain(element, this.layers.islands, { shore: false, spill: false });
      if (this.layers.islands !== undefined) {
        this.terrain.paintIslandShore(this.layers.islands, element, beach.water, resolution, zoom);
      }
    }

    const bridgeElements = elements.filter((element) => this.isGroundDeck(element));
    const walls = elements.filter((element) => element.kind === 'obstacle' && element.shape.type === 'wall');
    const openings = elements.filter((element) => element.kind === 'opening');
    const cut = this.cutRect(floor);
    const decorElements = elements
      .filter((element) => element.kind === 'decor')
      .sort(
        (left, right) =>
          this.decor.anchorY(left) - this.decor.anchorY(right) ||
          left.sortOrder - right.sortOrder ||
          left.id - right.id,
      );
    // Стены рисуются одной сетью до остальных препятствий: в углах соседние
    // ленты сходятся в общий узел, и угол делается митрой, а не двумя торцами.
    this.drawThresholds(elements, walls, openings);
    this.drawWalls(walls, openings);

    for (const element of elements) {
      if (
        element.kind === 'terrain' ||
        element.kind === 'decor' ||
        this.isGroundDeck(element) ||
        this.isFloorOpening(element) ||
        (element.kind === 'obstacle' && element.shape.type === 'wall')
      ) {
        continue;
      }

      // Дверь и окно — часть срезанной стены: отдельной полосой они торчали бы
      // из-под полупрозрачной плиты. За пятном реза стена остаётся, поэтому
      // проём ворот в заборе двора рисуется.
      if (
        cut !== null &&
        element.kind === 'opening' &&
        element.shape.type === 'wall' &&
        this.insideCut(cut, element.shape)
      ) {
        continue;
      }

      this.drawElement(element, walls);
    }

    this.drawProps(board, bridgeElements, decorElements, terrainElements, waterElements, resolution, insets);
    this.drawRoofHatchRims(board);
    this.applyContentClip(board, this.slabHoles(floor), cut);

    // Плита выбранного этажа полупрозрачна: сквозь неё виден этаж ниже. Стены,
    // декор и акторы остаются непрозрачными — прозрачность только у пола.
    if (floor.boardId === this.planSlabBoardId()) {
      const terrain = field.layers.terrain;
      if (terrain !== undefined) {
        terrain.alpha = SCENE_FLOOR.slabAlpha;
      }
    }

    this.layers = savedLayers;
    this.boardBounds = savedBounds;
    this.fieldScale = savedScale;
  }

  /**
   * Проёмы в плите этажа: настоящие вырезы, а не заливка поверх.
   *
   * Колодец — прямоугольный проём `stairs` без целевого поля. Площадка с
   * целью — верхняя ступень марша, а не плита: под ней тоже вырез, иначе
   * верхние ступени прятались бы под доской, а актор на них оказывался бы
   * в перекрытии. Настил площадки уже даёт сам марш на его отметке.
   *
   * @param floor Этаж.
   *
   * @returns Прямоугольники вырезов в координатах поля.
   */
  private slabHoles(floor: SceneFloor): SceneBounds[] {
    if (this.model === null || !this.slabCutaway) {
      return [];
    }

    // У корня плита не просвечивает целиком: видны только пятна подвалов.
    if (floor.root) {
      return this.basementHoles(floor.boardId);
    }

    if (floor.boardId !== this.planSlabBoardId()) {
      return [];
    }

    return sceneFloorPlanService.slabHoles(this.model, floor.boardId);
  }

  /**
   * Колодцы в проходимой крыше: марши, которые выходят на неё.
   *
   * Лестница на крышу не прорезает плиту отдельным проёмом: крыша — настил, а
   * не перекрытие с заливкой. Поэтому её настил режется по верхней части марша,
   * верх которого совпадает с крышей, — так виден люк и проход вниз, а
   * остальная лестница остаётся под крышей.
   *
   * @param boardId Поле этажа.
   *
   * @returns Проёмы в координатах поля.
   */
  private roofHoles(boardId: number): SceneRoofHatch[] {
    const model = this.model;
    if (model === null) {
      return [];
    }

    return sceneRoofHatches(
      model.elements.filter((element) => element.boardId === boardId),
      Math.max(1, model.resolution),
    );
  }

  /**
   * Рамка вокруг проёма в настиле крыши.
   *
   * Вырез маски не даёт края: ступени видны, но сам проём читается плохо.
   * Рамка рисуется толстой линией по контуру: наружная половина остаётся,
   * внутреннюю срезает маска.
   *
   * @param board Поле этажа.
   */
  private drawRoofHatchRims(board: SceneBoard): void {
    const layer = this.layers.deckRoofs;
    const holes = this.roofHoles(board.id);
    if (layer === undefined || holes.length === 0) {
      return;
    }

    const graphics = new Graphics();
    for (const hole of holes) {
      graphics.poly(hole.polygon.flat()).stroke({
        color: SCENE_ROOF.deckEdgeColor,
        width: Math.max(2, SCENE_ROOF.hatchRim * 2),
        alpha: 0.9,
      });
    }
    layer.addChild(graphics);
  }

  /**
   * Пятна подвалов в плите корня.
   *
   * Подвал — вложение корня с отрицательной отметкой. Сквозь его пятно видно
   * пол подвала; остальная земля остаётся непрозрачной.
   *
   * @param rootBoardId Корень.
   *
   * @returns Прямоугольники пятен в координатах корня.
   */
  private basementHoles(rootBoardId: number): SceneBounds[] {
    if (this.model === null) {
      return [];
    }

    const holes: SceneBounds[] = [];
    for (const enclosure of this.model.enclosures) {
      if (enclosure.parentBoardId !== rootBoardId || enclosure.elevation >= 0) {
        continue;
      }

      const shape = enclosure.footprint;
      if (shape.type === 'rect') {
        holes.push({ x: shape.x, y: shape.y, width: shape.w, height: shape.h });
      }
    }

    return holes;
  }

  /** Плита какого этажа сейчас в разрезе; null — нет. */
  private planSlabBoardId(): number | null {
    if (this.model === null || !this.slabCutaway) {
      return null;
    }

    return this.floorPlan?.slabBoardId ?? null;
  }

  /**
   * Пятно плиты фокуса в координатах этажа под разрезом.
   *
   * Стены и потолок нижнего этажа срезаны там, где над ними лежит пол
   * выбранного этажа: сквозь полупрозрачную плиту виден план. За пятном
   * стены остаются — там над ними неба нет только у соседних строений.
   *
   * @param floor Этаж.
   *
   * @returns Прямоугольник реза или null, если резать нечего.
   */
  private cutRect(floor: SceneFloor): SceneBounds | null {
    if (this.model === null || !this.slabCutaway || !floor.underCut) {
      return null;
    }

    const active = this.model.boards.find((board) => board.id === this.model?.activeBoardId);
    if (active === undefined) {
      return null;
    }

    // Режется только нарисованный пол, а не всё поле: поля обзора вокруг
    // плана — не плита, и стены двора под ними остаются.
    const slab = sceneFloorPlanService.slabBounds(this.model, active.id);
    if (slab === null) {
      return null;
    }

    const transform = sceneFloorPlanService.transform(this.model, active.id);
    const margin = Math.max(0, this.model.resolution * SCENE_FLOOR.cutMarginIpari);
    const left = transform.x + slab.x * transform.scaleX - margin;
    const top = transform.y + slab.y * transform.scaleY - margin;
    const right = transform.x + (slab.x + slab.width) * transform.scaleX + margin;
    const bottom = transform.y + (slab.y + slab.height) * transform.scaleY + margin;
    const start = sceneFloorPlanService.toLocal(floor.transform, [left, top]);
    const end = sceneFloorPlanService.toLocal(floor.transform, [right, bottom]);
    // Вырез обязан лежать внутри поля: иначе маска не режет вовсе. Пятно
    // вложения и так внутри родителя, но запас реза может выйти за край.
    const x = Math.max(0, Math.min(start[0], end[0]));
    const y = Math.max(0, Math.min(start[1], end[1]));
    const width = Math.min(floor.board.boundWidth, Math.max(start[0], end[0])) - x;
    const height = Math.min(floor.board.boundHeight, Math.max(start[1], end[1])) - y;
    if (width <= 0 || height <= 0) {
      return null;
    }

    return { x, y, width, height };
  }

  /**
   * Проём на срезанной стене: его габарит целиком внутри пятна реза.
   *
   * За пятном стена остаётся, и её дверь или окно тоже: иначе ворота в заборе
   * вокруг дома исчезли бы вместе с планом первого этажа.
   *
   * @param cut Пятно реза в координатах поля.
   * @param shape Фигура проёма.
   *
   * @returns true — проём лежит на срезанной части стены.
   */
  private insideCut(cut: SceneBounds, shape: SceneShape): boolean {
    const box = sceneGeometryService.bounds(shape);

    return (
      box.x >= cut.x &&
      box.y >= cut.y &&
      box.x + box.width <= cut.x + cut.width &&
      box.y + box.height <= cut.y + cut.height
    );
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
    insets: ReadonlyMap<number, SceneGrassInset>,
  ): void {
    const layer = this.layers.decor;
    const ground = this.layers.ground;
    if (layer === undefined || ground === undefined) {
      return;
    }

    // Острова и насыпи: на них декор стоит на суше, хотя контур воды его накрывает.
    const dryElements = terrainElements.filter((element) => element.props.overWater === true);

    const bounds = { x: 0, y: 0, width: board.boundWidth, height: board.boundHeight };
    // Мосты, съезды и лестницы — пол над водой и под оверлеями, декором и
    // акторами. Съезду нужна доля низкой части: под ней насыпь, а не просвет.
    const heightElements = [...terrainElements, ...bridgeElements];
    for (const element of bridgeElements) {
      const blockedShare =
        element.props.kind === 'ramp' ? sceneRampBlockedShare(element, heightElements, resolution) : 0;
      // Подложка марша — материал пола: доски, камень или плитка из тех же
      // текстур, что у террейна. Мосту и съезду материал не нужен.
      const surface =
        element.props.kind === 'stairs' && element.shape.type === 'rect'
          ? this.stairSurfaces.surface(element.props.material, element.shape, element.props.elevation ?? 0, resolution)
          : null;
      const painted = this.bridge.paint(element, resolution, this.zoom(), blockedShare, surface);
      if (painted !== null) {
        ground.addChild(painted);
      }
    }

    this.drawFloorOpenings(board.id, ground);

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
      : this.grass.band(this.grass.placeCarpet(grassElements, bounds, resolution, insets), resolution);
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
      ...(this.editing ? [] : this.grass.placeClumps(grassElements, bounds, resolution, insets)).map((tuft) => ({
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
    if (this.overlayPaints.size === 0 || this.isEdit()) {
      this.releaseFogSprites();
      for (const field of this.fields) {
        this.clearFieldLayer(field, 'overlay');
        this.clearFieldLayer(field, 'fog');
      }
      this.costPaints.clear();

      return;
    }

    const painted = new Set<number>();
    for (const field of this.fields) {
      const paint = this.overlayPaints.get(field.boardId);
      const costs = field.layers.overlay;
      const fog = field.layers.fog;
      if (paint === undefined || costs === undefined || fog === undefined) {
        if (costs !== undefined && this.costPaints.has(field.boardId)) {
          this.clearFieldLayer(field, 'overlay');
          this.costPaints.delete(field.boardId);
        }
        this.hideFog(field.boardId);

        continue;
      }

      painted.add(field.boardId);
      // Стоимость меняется только с данными поля, туман — каждый тик.
      if (this.costPaints.get(field.boardId) !== paint.costs) {
        this.clearFieldLayer(field, 'overlay');
        this.paintBands(costs, paint.costs);
        this.costPaints.set(field.boardId, paint.costs);
      }
      this.paintFog(field.boardId, fog, paint.fog);
    }

    for (const boardId of [...this.fogSprites.keys()]) {
      if (!painted.has(boardId)) {
        this.releaseFogSprite(boardId);
      }
    }
    for (const boardId of [...this.costPaints.keys()]) {
      if (!painted.has(boardId)) {
        this.costPaints.delete(boardId);
      }
    }
  }

  /** Прячет туман поля: маска пропала, спрайт остаётся для следующего тика. */
  private hideFog(boardId: number): void {
    const handle = this.fogSprites.get(boardId);
    if (handle !== undefined) {
      handle.sprite.visible = false;
    }
  }

  /** Чистит слой одного поля: у оверлеев их столько же, сколько этажей. */
  private clearFieldLayer(field: SceneFieldRender, name: SceneLayerName): void {
    const layer = field.layers[name];
    if (layer === undefined) {
      return;
    }

    layer.mask = null;
    const children = layer.removeChildren();
    for (const child of children) {
      child.destroy({ children: true, texture: false, textureSource: false });
    }
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
   * Туман одной текстурой: тексель мельче клетки и линейная фильтрация.
   *
   * Маска растягивается на сетку с `linear`, поэтому край сглаживается без
   * шейдера. Текстура и спрайт живут между тиками: обновляется только альфа
   * через `source.update()`, а не создаётся новая текстура и новый спрайт.
   */
  private paintFog(boardId: number, layer: Container, mask: SceneOverlayMask | null): void {
    if (mask === null) {
      this.hideFog(boardId);

      return;
    }

    let handle = this.fogSprites.get(boardId);
    if (handle === undefined || handle.columns !== mask.columns || handle.rows !== mask.rows) {
      if (handle !== undefined) {
        this.releaseFogSprite(boardId);
      }
      handle = this.createFogSprite(mask);
      this.fogSprites.set(boardId, handle);
      layer.addChild(handle.sprite);
    } else {
      this.writeFogTexture(handle, mask);
    }

    handle.sprite.visible = true;
    handle.sprite.position.set(mask.x, mask.y);
    handle.sprite.width = mask.columns * mask.cell;
    handle.sprite.height = mask.rows * mask.cell;
    if (handle.sprite.parent !== layer) {
      layer.addChild(handle.sprite);
    }
  }

  /** Спрайт и текстура тумана; контекст канвы переиспользуется между тиками. */
  private createFogSprite(mask: SceneOverlayMask): FogSpriteHandle {
    const canvas = document.createElement('canvas');
    canvas.width = mask.columns;
    canvas.height = mask.rows;
    const context = canvas.getContext('2d');
    if (context === null) {
      const texture = Texture.EMPTY;
      const handle: FogSpriteHandle = {
        sprite: new Sprite(texture),
        texture,
        canvas,
        context: null,
        image: null,
        columns: mask.columns,
        rows: mask.rows,
      };

      return handle;
    }

    const image = context.createImageData(mask.columns, mask.rows);
    const red = (mask.color >> 16) & 0xff;
    const green = (mask.color >> 8) & 0xff;
    const blue = mask.color & 0xff;
    for (let index = 0; index < image.data.length; index += 4) {
      image.data[index] = red;
      image.data[index + 1] = green;
      image.data[index + 2] = blue;
    }

    const texture = Texture.from(canvas);
    texture.source.scaleMode = 'linear';
    const handle: FogSpriteHandle = {
      sprite: new Sprite(texture),
      texture,
      canvas,
      context,
      image,
      columns: mask.columns,
      rows: mask.rows,
    };
    this.writeFogTexture(handle, mask);

    return handle;
  }

  /** Переносит альфу маски в канву спрайта и обновляет текстуру. */
  private writeFogTexture(handle: FogSpriteHandle, mask: SceneOverlayMask): void {
    if (handle.image === null || handle.context === null) {
      return;
    }

    const data = handle.image.data;
    const alpha = mask.alpha;
    for (let index = 0; index < alpha.length; index += 1) {
      data[index * 4 + 3] = alpha[index];
    }
    handle.context.putImageData(handle.image, 0, 0);
    handle.texture.source.update();
  }

  /** Освобождает спрайты тумана всех полей. */
  private releaseFogSprites(): void {
    for (const boardId of [...this.fogSprites.keys()]) {
      this.releaseFogSprite(boardId);
    }
  }

  /** Освобождает спрайт и текстуру тумана одного поля. */
  private releaseFogSprite(boardId: number): void {
    const handle = this.fogSprites.get(boardId);
    if (handle === undefined) {
      return;
    }

    this.fogSprites.delete(boardId);
    handle.sprite.removeFromParent();
    handle.sprite.destroy();
    if (handle.texture !== Texture.EMPTY) {
      handle.texture.destroy(true);
    }
  }

  /** Линия маршрута: над миром и туманом, под ручками редактирования. */
  private drawRoute(): void {
    // Линия маршрута живёт в координатах своего поля: у каждого этажа свой
    // участок, поэтому чистим слои всех видимых полей.
    for (const field of this.fields) {
      const layer = field.layers.route;
      if (layer === undefined) {
        continue;
      }

      layer.mask = null;
      for (const child of layer.removeChildren()) {
        child.destroy({ children: true, texture: false, textureSource: false });
      }
    }

    if (this.route === null || this.route.points.length < 2 || this.isEdit()) {
      return;
    }

    const grouped = new Map<number, SceneNavSegment[]>();
    for (const segment of this.route.segments) {
      if (segment.points.length < 2) {
        continue;
      }

      const list = grouped.get(segment.boardId) ?? [];
      list.push(segment);
      grouped.set(segment.boardId, list);
    }

    for (const [boardId, segments] of grouped) {
      const field = this.fieldOf(boardId);
      const layer = field?.layers.route;
      if (field === null || layer === undefined) {
        continue;
      }

      const zoom = Math.max(0.001, (this.viewport?.zoom ?? 1) * field.scale);
      const open = new Graphics();
      const covered = new Graphics();
      const dots = new Graphics();
      for (const segment of segments) {
        const target = segment.covered ? covered : open;
        const first = segment.points[0];
        target.moveTo(first[0], first[1]);
        for (const point of segment.points.slice(1)) {
          target.lineTo(point[0], point[1]);
        }

        const last = segment.points[segment.points.length - 1];
        dots.circle(first[0], first[1], 2.5 / zoom).fill({ color: 0xbfdbfe, alpha: 0.95 });
        dots.circle(last[0], last[1], 2.5 / zoom).fill({ color: 0xbfdbfe, alpha: 0.95 });
      }

      // Участок под настилом ведёт по земле: он приглушён и тоньше, чтобы не
      // читаться как дорога поверх моста.
      open.stroke({ color: 0x60a5fa, width: 3 / zoom, alpha: 0.85 });
      covered.stroke({ color: 0x1d4ed8, width: 2 / zoom, alpha: 0.4 });
      layer.addChild(open);
      layer.addChild(covered);
      layer.addChild(dots);
    }
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

  /**
   * Акторы видимых этажей поверх статики: вставка по `y` между детьми слоя.
   *
   * Актор принадлежит полю (`boardId`) и живёт в координатах этого поля:
   * группа этажа сама переводит его в мир корня. Акторы невидимых этажей не
   * рисуются.
   */
  private paintActors(): void {
    if (this.model === null) {
      return;
    }

    const resolution = this.model.resolution;
    const viewZoom = this.viewport?.zoom ?? 1;
    const ids = new Set(this.actors.map((actor) => actor.id));
    for (const [id, painted] of this.actorNodes) {
      if (!ids.has(id)) {
        painted.node.destroy({ children: true });
        this.actorNodes.delete(id);
        this.propY.delete(painted.node);
      }
    }

    for (const actor of this.actors) {
      const field = this.fieldOf(actor.boardId ?? this.model.activeBoardId);
      const layer = field?.layers.decor;
      if (field === null || layer === undefined) {
        continue;
      }

      const painted = this.actorNodes.get(actor.id);
      if (painted === undefined) {
        const created = this.actorService.paint(actor, resolution, viewZoom * field.scale);
        layer.addChild(created.node);
        this.actorNodes.set(actor.id, created);
        this.placeActor(created.node, this.actorSortY(actor), layer);
        continue;
      }

      if (painted.node.parent !== layer) {
        layer.addChild(painted.node);
      }

      this.actorService.update(painted, actor, viewZoom * field.scale);
      this.placeActor(painted.node, this.actorSortY(actor), layer);
    }
  }

  /** Этаж по id поля; null — поле не видно. */
  private fieldOf(boardId: number): SceneFieldRender | null {
    return this.fields.find((field) => field.boardId === boardId) ?? null;
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

  /**
   * Ставит актора на его `y` среди статичных детей слоя.
   *
   * Дети слоя отсортированы по `y` при сборке, поэтому позиция ищется двоичным
   * поиском. Линейный проход по лесу из десятков тысяч спрайтов на каждого
   * актора каждый кадр — это уже узкое место, даже без перерисовки.
   */
  private placeActor(node: Container, y: number, layer: Container): void {
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
    this.propY.clear();
    this.actorNodes.clear();
    this.draftGraphics = null;
    this.hoverGraphics = null;
    this.releaseFogSprites();
    this.costPaints.clear();
    for (const field of this.fields) {
      field.group.removeFromParent();
      field.group.destroy({ children: true, texture: false, textureSource: false });
    }

    this.fields = [];
    this.activeField = null;
    this.floorPlan = null;
    this.layers = {};
    this.fieldScale = 1;
    this.waterNodes = [];
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

  /**
   * Обрезает содержимое поля по его границам; gizmos остаются снаружи.
   *
   * Вырезы — настоящие дыры в плите: маска поля минус контуры проёмов. Сквозь
   * них виден этаж ниже, а не заливка поверх пола.
   *
   * @param board Поле.
   * @param holes Прямоугольники вырезов в координатах поля.
   */
  private applyContentClip(
    board: SceneBoard,
    holes: readonly SceneBounds[] = [],
    cut: SceneBounds | null = null,
  ): void {
    const names: SceneLayerName[] = [
      'backdrop',
      'terrain',
      'waterPlane',
      'islands',
      'supports',
      'obstacles',
      'openings',
      'ground',
      'deckRoofs',
      'decor',
      'roofs',
      'overlay',
      'fog',
      'route',
    ];
    const roofHoles = this.roofHoles(board.id);
    for (const name of names) {
      const layer = this.layers[name];
      if (layer === undefined) {
        continue;
      }

      const mask = new Graphics();
      mask.rect(0, 0, board.boundWidth, board.boundHeight).fill({ color: 0xffffff });
      // Подложка корня тоже режется: иначе тёмная заливка закрыла бы подвал,
      // видимый сквозь пятно в земле.
      if (name === 'terrain' || name === 'backdrop') {
        for (const hole of holes) {
          mask.rect(hole.x, hole.y, hole.width, hole.height).cut();
        }
      }

      // Марш, который выходит на проходимую крышу, прорезает её настил: видно,
      // куда спускаться, а не глухую заливку поверх ступеней.
      if (name === 'deckRoofs') {
        for (const hole of roofHoles) {
          mask.poly(hole.polygon.flat()).cut();
        }
      }

      // Стены и потолок этажа под плитой срезаны её пятном: сквозь
      // полупрозрачный пол виден план, а не стены первого этажа.
      if (cut !== null && (name === 'obstacles' || name === 'roofs' || name === 'deckRoofs')) {
        mask.rect(cut.x, cut.y, cut.width, cut.height).cut();
      }

      layer.addChild(mask);
      layer.mask = mask;
    }
  }

  /**
   * Текстуры всех видимых этажей.
   *
   * @param model Модель рендера.
   * @param floors Видимые этажи.
   *
   * @returns Id текстур.
   */
  private textureIds(model: SceneRenderModel, floors: readonly SceneFloor[]): string[] {
    const publicIds = new Set<string>();
    for (const floor of floors) {
      const board = floor.board;
      if (board.baseFile !== null) {
        publicIds.add(board.baseFile.publicId);
      }

      for (const element of model.elements) {
        if (element.boardId === board.id && element.asset !== null) {
          publicIds.add(element.asset.publicId);
        }
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
  private drawTerrain(
    element: SceneElement,
    layer: Container | undefined = this.layers.terrain,
    options: { shore?: boolean; spill?: boolean } = {},
  ): void {
    if (layer === undefined) {
      return;
    }

    const zoom = this.zoom();
    const resolution = this.model?.resolution ?? 64;
    const endPads = this.openEndPads(element, resolution);
    // У шейдерной воды мокрый берег и кромка считаются в шейдере тела:
    // гладкая полоса paintWaterBank дала бы вторую параллельную линию.
    const shaderWater = element.props.kind === 'water' && isShaderWater(element);
    if (options.shore ?? true) {
      this.terrain.paintShore(layer, element, resolution, zoom, endPads, !shaderWater);
    }
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

    this.terrain.paintSurface(layer, element, resolution, zoom, options.spill ?? true);
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

  /** Проём в полу: площадка перехода или лестничный колодец. */
  private isFloorOpening(element: SceneElement): boolean {
    return element.kind === 'opening' && element.shape.type === 'rect';
  }

  /** Проёмы в полу ложатся поверх настилов: площадка не должна тонуть в ступенях. */
  private drawFloorOpenings(boardId: number, layer: Container | undefined): void {
    if (layer === undefined || this.model === null) {
      return;
    }

    const zoom = this.zoom();
    for (const element of this.model.elements) {
      // Колодец в разрезанной плите не закрашивается: дыра настоящая, и
      // заливка «шахты» закрыла бы то, что видно сквозь неё.
      if (this.isSlabHole(element, boardId)) {
        continue;
      }

      // Площадка перехода в предпросмотре не рисуется: её подсвечивает
      // наведение, а постоянная рамка спорила со ступенями марша.
      if (element.targetBoardId !== null && !this.isEdit()) {
        continue;
      }

      if (element.boardId === boardId && this.isFloorOpening(element)) {
        layer.addChild(this.painter.paintElement(element, zoom));
      }
    }
  }

  /**
   * Колодец, который уже вырезан в плите.
   *
   * @param element Проём.
   * @param boardId Поле.
   *
   * @returns true — проём вырезан маской, заливка не нужна.
   */
  private isSlabHole(element: SceneElement, boardId: number): boolean {
    return (
      boardId === this.planSlabBoardId() &&
      element.boardId === boardId &&
      element.targetBoardId === null &&
      element.props.kind === 'stairs'
    );
  }

  private drawElement(element: SceneElement, walls: readonly SceneElement[]): void {
    const layerName = this.layerFor(element);
    const layer = this.layers[layerName];
    if (layer === undefined) {
      return;
    }

    const sized = this.openingInWall(element, walls);
    // Мебель рисуется процедурно по подтипу; остальные препятствия — как
    // раньше, фигурой с заливкой и обводкой.
    const furniture = element.kind === 'obstacle' ? this.furniture.paint(sized) : null;
    layer.addChild(furniture ?? this.painter.paintElement(sized, this.zoom()));
  }

  /**
   * Проём — в толщине своей стены.
   *
   * Проём, поставленный пресетом, несёт толщину по умолчанию, а стена могла
   * стать тоньше: окно рисовалось вдвое шире стены и читалось второй стеной.
   * Данные не меняем — только рисунок: толщина берётся у стены, на осевой
   * которой лежат оба конца проёма.
   *
   * @param element Элемент поля.
   * @param walls Стены поля.
   *
   * @returns Элемент с толщиной стены.
   */
  private openingInWall(element: SceneElement, walls: readonly SceneElement[]): SceneElement {
    if (element.kind !== 'opening' || element.shape.type !== 'wall' || element.shape.points.length < 2) {
      return element;
    }

    const points = element.shape.points;
    const start = points[0];
    const end = points[points.length - 1];
    for (const wall of walls) {
      if (wall.shape.type !== 'wall') {
        continue;
      }

      if (!sceneGeometryService.contains(wall.shape, start, 1) || !sceneGeometryService.contains(wall.shape, end, 1)) {
        continue;
      }

      if (Math.abs(wall.shape.width - element.shape.width) < 0.5) {
        return element;
      }

      return { ...element, shape: { ...element.shape, width: wall.shape.width } };
    }

    return element;
  }

  /**
   * Пороги: пол продолжается под дверью.
   *
   * Пол элемента террейна заканчивается на осевой стены, а проём занимает
   * полную её толщину: в двери оставалась полоса чужого грунта, и пол под
   * входом выглядел вырезанным. Порог — тот же материал, что у пола рядом,
   * поэтому проём читается продолжением пола, а не дырой.
   *
   * @param elements Элементы поля.
   * @param openings Проёмы поля.
   */
  private drawThresholds(
    elements: readonly SceneElement[],
    walls: readonly SceneElement[],
    openings: readonly SceneElement[],
  ): void {
    const layer = this.layers.terrain;
    if (layer === undefined) {
      return;
    }

    const floors = elements.filter(
      (element) => element.kind === 'terrain' && THRESHOLD_RANK[element.props.kind ?? ''] !== undefined,
    );
    if (floors.length === 0) {
      return;
    }

    const resolution = this.model?.resolution ?? 64;
    const zoom = this.zoom();
    for (const opening of openings) {
      if (opening.shape.type !== 'wall' || opening.shape.points.length < 2) {
        continue;
      }

      if (opening.props.kind !== 'door' && opening.props.kind !== 'gate') {
        continue;
      }

      const sized = this.openingInWall(opening, walls);
      if (sized.shape.type !== 'wall') {
        continue;
      }

      const floor = this.thresholdFloor(floors, sized);
      if (floor === null) {
        continue;
      }

      const points = sized.shape.points;
      const start = points[0];
      const end = points[points.length - 1];
      const dx = end[0] - start[0];
      const dy = end[1] - start[1];
      const length = Math.hypot(dx, dy);
      if (length < 1) {
        continue;
      }

      const half = Math.max(1, sized.shape.width) / 2;
      const normalX = (-dy / length) * half;
      const normalY = (dx / length) * half;
      const band: ScenePoint[] = [
        [start[0] + normalX, start[1] + normalY],
        [end[0] + normalX, end[1] + normalY],
        [end[0] - normalX, end[1] - normalY],
        [start[0] - normalX, start[1] - normalY],
      ];
      this.terrain.paintSurface(
        layer,
        {
          id: opening.id,
          boardId: opening.boardId,
          kind: 'terrain',
          shape: { type: 'polygon', points: band },
          props: { kind: floor.props.kind },
          sortOrder: opening.sortOrder,
          targetBoardId: null,
          asset: null,
        },
        resolution,
        zoom,
        false,
      );
    }
  }

  /** Пол у проёма: ближайший подходящий террейн с той или другой стороны. */
  private thresholdFloor(floors: readonly SceneElement[], opening: SceneElement): SceneElement | null {
    if (opening.shape.type !== 'wall' || opening.shape.points.length < 2) {
      return null;
    }

    const points = opening.shape.points;
    const start = points[0];
    const end = points[points.length - 1];
    const dx = end[0] - start[0];
    const dy = end[1] - start[1];
    const length = Math.hypot(dx, dy);
    if (length < 1) {
      return null;
    }

    const half = Math.max(1, opening.shape.width) / 2;
    const normalX = -dy / length;
    const normalY = dx / length;
    const middle: ScenePoint = [(start[0] + end[0]) / 2, (start[1] + end[1]) / 2];
    let best: { floor: SceneElement; rank: number } | null = null;
    for (const side of [1, -1]) {
      const probe: ScenePoint = [middle[0] + normalX * side * (half + 2), middle[1] + normalY * side * (half + 2)];
      for (const floor of floors) {
        if (!sceneGeometryService.contains(floor.shape, probe, 0, 0)) {
          continue;
        }

        const rank = THRESHOLD_RANK[floor.props.kind ?? ''] ?? 9;
        if (best === null || rank < best.rank) {
          best = { floor, rank };
        }
      }
    }

    return best === null ? null : best.floor;
  }

  /**
   * Стены одной сетью.
   *
   * Стены рисуются силуэтом: полосы соседних лент объединяются булевой
   * геометрией, поэтому нахлёст и Т-стык не видны, а в углу одна заливка.
   * Порядок — тень, заливка, обводка: тень уходит под стену, обводка ложится
   * поверх силуэта и обводит и внешний край, и край со стороны комнаты.
   *
   * @param walls Стены поля.
   * @param openings Проёмы поля: они режут стены на полосы.
   */
  private drawWalls(walls: readonly SceneElement[], openings: readonly SceneElement[]): void {
    const layer = this.layers.obstacles;
    if (layer === undefined || walls.length === 0) {
      return;
    }

    const layout = sceneWallService.layout(walls, openings);
    if (layout.bands.length === 0) {
      return;
    }

    const resolution = this.model?.resolution ?? 64;
    const zoom = this.zoom();
    const thickness = layout.outlines.reduce((max, outline) => Math.max(max, outline.thickness), 1);
    const shift = this.wallShadowDirection(thickness);
    for (const pass of SCENE_WALL_MATERIAL.shadow.layers) {
      const offset: ScenePoint = [shift[0] * pass.shift, shift[1] * pass.shift];
      layer.addChild(this.painter.paintWallShadow(sceneWallService.shadow(layout.area, offset), pass.alpha));
    }

    layer.addChild(this.painter.paintWallTop(layout.bands, resolution, zoom));
    layer.addChild(this.painter.paintWallOutline(layout.outlines, zoom));
  }

  /**
   * Сдвиг тени на толщину стены.
   *
   * Свет общий для сцены (`SCENE_WALL_MATERIAL.light`), тень падает в
   * противоположную ему сторону. Длина — в долях толщины: у толстой стены
   * тень должна быть дальше.
   */
  private wallShadowDirection(thickness: number): ScenePoint {
    const [lightX, lightY] = SCENE_WALL_MATERIAL.light;
    const length = Math.hypot(lightX, lightY) || 1;

    return [(-lightX / length) * thickness, (-lightY / length) * thickness];
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
    // Обычная крыша — накрытие над стенами и декором. Проходимая — настил:
    // она лежит ниже акторов, чтобы по ней было видно того, кто идёт.
    if (isWalkableRoof(element)) return 'deckRoofs';
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
    return (this.viewport?.zoom ?? 1) * this.fieldScale;
  }
}
