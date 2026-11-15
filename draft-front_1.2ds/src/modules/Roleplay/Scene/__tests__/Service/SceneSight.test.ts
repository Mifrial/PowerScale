import { describe, expect, it } from 'vitest';
import {
  sceneBodyHeight,
  sceneSightEyeIpari,
  sceneSightTargetIpari,
} from '@/modules/Roleplay/Scene/Service/SceneSight';

describe('SceneSight', () => {
  it('берёт рост по умолчанию из роста тела и округляет до единиц', () => {
    expect(sceneBodyHeight(64)).toBe(77);
    expect(sceneBodyHeight(16)).toBe(19);
  });

  it('строит глаза как 90% роста, а цель — как 75%', () => {
    expect(sceneSightEyeIpari(77, 64)).toBe(69 / 64);
    expect(sceneSightTargetIpari(77, 64)).toBe(58 / 64);
  });

  it('у высокого персонажа глаза выше', () => {
    expect(sceneSightEyeIpari(96, 64)).toBe(86 / 64);
  });
});
