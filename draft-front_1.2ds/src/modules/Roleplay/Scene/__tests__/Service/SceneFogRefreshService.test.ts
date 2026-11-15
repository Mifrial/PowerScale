import { describe, expect, it } from 'vitest';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import { SceneFogRefreshService } from '@/modules/Roleplay/Scene/Service/SceneFogRefreshService';

const resolution = 64;
const cell = resolution * SCENE_OVERLAY.fogCellIpari;

describe('SceneFogRefreshService', () => {
  it('считает туман при переходе в другую грубую клетку', () => {
    const cadence = new SceneFogRefreshService();

    expect(cadence.isDue([0, 0], resolution, 0)).toBe(true);
    expect(cadence.isDue([cell * 0.5, 0], resolution, 1000)).toBe(false);
    expect(cadence.isDue([cell * 2, 0], resolution, 2000)).toBe(true);
  });

  it('держит минимальный интервал между пересчётами', () => {
    const cadence = new SceneFogRefreshService();

    expect(cadence.isDue([0, 0], resolution, 1000)).toBe(true);
    expect(cadence.isDue([cell, 0], resolution, 1000 + SCENE_OVERLAY.fogIntervalMs - 1)).toBe(false);
    // Клетка уже другая, но интервал не вышел: расчёт нужно досчитать позже.
    expect(cadence.isCellChanged([cell, 0], resolution)).toBe(true);
    expect(cadence.isDue([cell, 0], resolution, 1000 + SCENE_OVERLAY.fogIntervalMs)).toBe(true);
    expect(cadence.isCellChanged([cell, 0], resolution)).toBe(false);
  });

  it('после сброса считает заново', () => {
    const cadence = new SceneFogRefreshService();

    expect(cadence.isDue([0, 0], resolution, 1000)).toBe(true);
    cadence.reset();
    expect(cadence.isDue([0, 0], resolution, 1001)).toBe(true);
  });

  it('без наблюдателя считает центр один раз', () => {
    const cadence = new SceneFogRefreshService();

    expect(cadence.isDue(null, resolution, 0)).toBe(true);
    expect(cadence.isDue(null, resolution, 5000)).toBe(false);
  });
});
