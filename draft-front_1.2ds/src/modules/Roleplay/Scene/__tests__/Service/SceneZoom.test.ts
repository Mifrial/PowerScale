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

  it('не пересобирает без валидного зума', () => {
    expect(sceneZoomNeedsRebuild(0, 1)).toBe(false);
    expect(sceneZoomNeedsRebuild(1, 0)).toBe(false);
  });
});
