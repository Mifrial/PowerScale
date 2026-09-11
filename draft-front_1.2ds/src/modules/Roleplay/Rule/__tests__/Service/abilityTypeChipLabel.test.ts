import { describe, expect, it } from 'vitest';
import { abilityTypeChipLabelService } from '@/modules/Roleplay/Rule/Service/Instance/abilityTypeChipLabelService';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';

function keyword(id: number, code: string, name: string): Keyword {
  return { id, code, name, description: '', active: true };
}

const catalog: Keyword[] = [
  keyword(13, 'skill', 'Навык'),
  keyword(3, 'magic', 'Волшебство'),
  keyword(228, 'magic-path', 'Путь волшебства'),
  keyword(229, 'arcanist', 'Арканист'),
];

const pathRules = [{ type: 'magic_path', code: 'arcanist' }];

describe('AbilityTypeChipLabelService', () => {
  it('навык пути волшебства склеивает путь и признак конкретного пути', () => {
    expect(abilityTypeChipLabelService.label('skill', catalog, [13, 3, 228, 229], pathRules)).toBe(
      'Путь волшебства: Арканист',
    );
  });

  it('прочий навык волшебства заменяет тип именем ключевого слова', () => {
    expect(abilityTypeChipLabelService.label('skill', catalog, [13, 3], pathRules)).toBe('Волшебство');
  });

  it('обычный навык остаётся типом без уточнения', () => {
    expect(abilityTypeChipLabelService.label('skill', catalog, [13], pathRules)).toBe('Навык');
  });

  it('заклинание не уточняется ключевым словом волшебства', () => {
    expect(abilityTypeChipLabelService.label('spell', catalog, [3], pathRules)).toBe('Заклинание');
  });
});
