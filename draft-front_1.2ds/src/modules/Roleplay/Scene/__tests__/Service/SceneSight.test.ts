import { describe, expect, it } from 'vitest';
import { sceneBodyHeight, sceneBodyIpari, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

describe('SceneSight', () => {
  it('берёт рост по умолчанию из роста тела и округляет до единиц', () => {
    expect(sceneBodyHeight(64)).toBe(77);
    expect(sceneBodyHeight(16)).toBe(19);
  });

  it('строит глаза как 90% роста, а тело цели — во весь рост', () => {
    expect(sceneSightEyeIpari(77, 64)).toBe(69 / 64);
    expect(sceneBodyIpari(77, 64)).toBe(77 / 64);
  });

  it('у высокого персонажа глаза выше', () => {
    expect(sceneSightEyeIpari(96, 64)).toBe(86 / 64);
  });
});
