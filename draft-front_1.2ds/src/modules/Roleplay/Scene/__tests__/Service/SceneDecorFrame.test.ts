import { describe, expect, it } from 'vitest';
import { sceneDecorSpritePlacement } from '@/modules/Roleplay/Scene/Service/SceneDecorFrame';

describe('sceneDecorSpritePlacement', () => {
  it('ставит якорь на низ видимой массы и считает scale от её ширины', () => {
    // Валун 200×140: видимая масса 116×82 в точке (47, 47).
    const placement = sceneDecorSpritePlacement({ x: 47, y: 47, width: 116, height: 82 }, 200, 140, 64);

    expect(placement.visibleWidth).toBe(64);
    expect(placement.visibleHeight).toBeCloseTo((64 * 82) / 116);
    expect(placement.width).toBeCloseTo((64 * 200) / 116);
    expect(placement.height).toBeCloseTo(((64 * 200) / 116) * (140 / 200));
    expect(placement.anchorX).toBeCloseTo((47 + 58) / 200);
    expect(placement.anchorY).toBeCloseTo((47 + 82) / 140);
  });

  it('без рамки рисует весь файл, как раньше', () => {
    const placement = sceneDecorSpritePlacement(null, 200, 140, 64);

    expect(placement.width).toBe(64);
    expect(placement.height).toBeCloseTo((64 * 140) / 200);
    expect(placement.anchorX).toBe(0.5);
    expect(placement.anchorY).toBe(1);
    expect(placement.visibleWidth).toBe(64);
    expect(placement.visibleHeight).toBeCloseTo((64 * 140) / 200);
  });

  it('зажимает битую рамку в размеры файла', () => {
    const placement = sceneDecorSpritePlacement({ x: 190, y: 130, width: 500, height: 500 }, 200, 140, 64);

    expect(placement.anchorX).toBeCloseTo((190 + 10 / 2) / 200);
    expect(placement.anchorY).toBe(1);
    expect(placement.visibleHeight).toBeCloseTo((64 * 10) / 10);
  });
});
