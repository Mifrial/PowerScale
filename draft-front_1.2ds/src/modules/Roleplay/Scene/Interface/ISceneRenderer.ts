import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { SceneOverlayPaint } from '@/modules/Roleplay/Scene/Dto/SceneOverlayPaint';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneShape } from '@/modules/Roleplay/Scene/Dto/SceneShape';
import type { SceneVertexTarget } from '@/modules/Roleplay/Scene/Dto/SceneVertexTarget';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';

/** Граница renderer'а: состояние снаружи, Pixi внутри. */
export interface ISceneRenderer {
  attach(container: HTMLElement): Promise<void>;
  detach(): void;
  resize(width: number, height: number, dpr: number): void;
  setViewport(viewport: SceneViewport): void;
  setScene(model: SceneRenderModel): Promise<void>;
  setSelection(ids: number[]): void;
  /** Акторы на поле: позиции и facing приходят снаружи. */
  setActors(actors: SceneActor[]): void;
  /** Акторы соседнего поля: рисуются в контексте этажа. */
  setContextActors(actors: SceneActor[]): void;
  /** Режим жеста: на время перетаскивания трава не пересобирается. */
  setEditing(editing: boolean): void;
  /** Полосы оверлеев предпросмотра; null — выключить. */
  setOverlayPaint(paint: SceneOverlayPaint | null): void;
  /** Маршрут выбранного актора; null — линия скрыта. */
  /** Маршрут актора с участками по уровням; null — линия скрыта. */
  setRoute(route: SceneNavRoute | null): void;
  /** Вершина, у которой видны ручки Безье; null — ручки скрыты. */
  setVertexFocus(target: SceneVertexTarget | null): void;
  /** Предварительная фигура во время протяжки; null — убрать превью. */
  setDraft(shape: SceneShape | null): void;
  /** Анимация воды: пользовательская настройка, работает в preview/play. */
  setWaterAnimation(enabled: boolean): void;
  /** Крыши скрыты: тумблер обзора в редакторе и на карте. */
  setRoofsHidden(hidden: boolean): void;
  /** Точка под курсором: подсветка площадки перехода; null — убрать. */
  setHoverPoint(point: ScenePoint | null): void;
  /** Соотношение сторон текстуры ассета: высота / ширина; null — нет текстуры. */
  assetAspect(publicId: string | null): number | null;
  render(): void;
  destroy(): void;
}
