import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';

/** Граница renderer'а: состояние снаружи, Pixi внутри. */
export interface ISceneRenderer {
  attach(container: HTMLElement): Promise<void>;
  detach(): void;
  resize(width: number, height: number, dpr: number): void;
  setViewport(viewport: SceneViewport): void;
  setScene(model: SceneRenderModel): Promise<void>;
  setSelection(ids: number[]): void;
  render(): void;
  destroy(): void;
}
