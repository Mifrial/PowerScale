import { describe, expect, it } from 'vitest';
import { sceneZoomNeedsRebuild } from '@/modules/Roleplay/Scene/Service/SceneZoom';

describe('sceneZoomNeedsRebuild', () => {
  it('пересобирает при заметном изменении', () => {
    expect(sceneZoomNeedsRebuild(1, 1.5)).toBe(true);
    expect(sceneZoomNeedsRebuild(2, 1)).toBe(true);
  });

  it('не дёргается на дрожании колеса', () => {
    expect(sceneZoomNeedsRebuild(1, 1.005)).toBe(false);
    expect(sceneZoomNeedsRebuild(1, 1)).toBe(false);
  });

  it('пересобирает при сдвиге больше порога, а не на каждый шаг колеса', () => {
    // Порог — три процента: два процента отсекаются как дрожание, четыре
    // пересобирают слои. Иначе колесо дёргало бы сцену на каждом событии.
    expect(sceneZoomNeedsRebuild(1, 1.02)).toBe(false);
    expect(sceneZoomNeedsRebuild(1, 0.98)).toBe(false);
    expect(sceneZoomNeedsRebuild(1, 1.04)).toBe(true);
    expect(sceneZoomNeedsRebuild(1, 0.96)).toBe(true);
  });

  it('не пересобирает без валидного зума', () => {
    expect(sceneZoomNeedsRebuild(0, 1)).toBe(false);
    expect(sceneZoomNeedsRebuild(1, 0)).toBe(false);
  });
});
