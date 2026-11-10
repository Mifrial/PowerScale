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
  /** Вершина, у которой видны ручки Безье; null — ручки скрыты. */
  setVertexFocus(target: SceneVertexTarget | null): void;
  /** Предварительная фигура во время протяжки; null — убрать превью. */
  setDraft(shape: SceneShape | null): void;
  render(): void;
  destroy(): void;
}
