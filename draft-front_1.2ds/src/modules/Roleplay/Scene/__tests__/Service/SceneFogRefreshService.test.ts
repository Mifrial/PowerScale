import { describe, expect, it } from 'vitest';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';
import { SceneFogRefreshService } from '@/modules/Roleplay/Scene/Service/SceneFogRefreshService';

describe('SceneFogRefreshService', () => {
  it('разрешает первый расчёт сразу', () => {
    const cadence = new SceneFogRefreshService();

    expect(cadence.isDue(1000)).toBe(true);
  });

  it('не считает туман чаще интервала', () => {
    const cadence = new SceneFogRefreshService();
    cadence.isDue(1000);

    expect(cadence.isDue(1000 + SCENE_OVERLAY.fogIntervalMs - 1)).toBe(false);
    expect(cadence.isDue(1000 + SCENE_OVERLAY.fogIntervalMs)).toBe(true);
  });

  it('после сброса считает туман на ближайшем тике', () => {
    const cadence = new SceneFogRefreshService();
    cadence.isDue(1000);
    cadence.isDue(1000 + SCENE_OVERLAY.fogIntervalMs);
    cadence.reset();

    expect(cadence.isDue(1000 + SCENE_OVERLAY.fogIntervalMs + 1)).toBe(true);
  });

  it('каденция плавная: на кадр движения не чаще 40 мс', () => {
    expect(SCENE_OVERLAY.fogIntervalMs).toBeLessThanOrEqual(50);
  });
});
