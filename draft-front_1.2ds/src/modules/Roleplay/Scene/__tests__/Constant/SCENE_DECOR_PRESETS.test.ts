import { describe, expect, it } from 'vitest';
import {
  SCENE_DECOR_PRESETS,
  sceneDecorPreset,
  sceneDecorPresetProps,
} from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_DECOR_PRESETS';

describe('SCENE_DECOR_PRESETS', () => {
  it('узнаёт дерево по имени файла независимо от регистра и пути', () => {
    expect(sceneDecorPreset('tree.png')).toBe(SCENE_DECOR_PRESETS['tree.png']);
    expect(sceneDecorPreset('/uploads/TREE.PNG')).toBe(SCENE_DECOR_PRESETS['tree.png']);
  });

  it('возвращает null для незнакомого файла', () => {
    expect(sceneDecorPreset('unknown.png')).toBeNull();
    expect(sceneDecorPreset(null)).toBeNull();
    expect(sceneDecorPreset('')).toBeNull();
  });

  it('переносит в элемент все параметры пресета', () => {
    const preset = SCENE_DECOR_PRESETS['tree.png'];

    expect(sceneDecorPresetProps(preset)).toEqual({
      scale: preset.scale,
      baseRadius: preset.baseRadius,
      sightRadius: preset.sightRadius,
      sightBottom: preset.sightBottom,
    });
  });
});
