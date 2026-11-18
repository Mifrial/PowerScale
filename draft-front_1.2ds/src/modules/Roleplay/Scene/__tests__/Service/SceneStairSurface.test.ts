import { describe, expect, it } from 'vitest';
import {
  sceneCleanWindow,
  sceneStairMaterial,
  sceneStairSteps,
} from '@/modules/Roleplay/Scene/Service/SceneStairSurface';

describe('sceneStairMaterial', () => {
  it('неизвестный материал — доски', () => {
    expect(sceneStairMaterial(undefined)).toBe('wood');
    expect(sceneStairMaterial('plaster')).toBe('wood');
    expect(sceneStairMaterial('stone')).toBe('stone');
    expect(sceneStairMaterial('tile')).toBe('tile');
  });
});

describe('sceneStairSteps', () => {
  it('считает ступени по подъёму', () => {
    expect(sceneStairSteps(0, 64)).toBe(1);
    expect(sceneStairSteps(64, 64)).toBe(4);
    expect(sceneStairSteps(128, 64)).toBe(8);
  });

  it('не разгоняет марш выше предела', () => {
    expect(sceneStairSteps(100000, 64)).toBe(24);
  });
});

/** Синтетический кадр: ровный тон и тёмная колонка-стык. */
function band(width: number, height: number, joint: number): Uint8ClampedArray {
  const data = new Uint8ClampedArray(width * height * 4);
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const index = (y * width + x) * 4;
      const tone = x === joint ? 90 : 150;
      data[index] = tone;
      data[index + 1] = tone;
      data[index + 2] = tone;
      data[index + 3] = 255;
    }
  }

  return data;
}

describe('sceneCleanWindow', () => {
  it('не включает в срез стык доски', () => {
    const start = sceneCleanWindow(band(64, 8, 32), 20, 64, 0, 8);

    expect(start + 2 > 32 || start + 18 < 32).toBe(true);
  });
});
