import { describe, it, expect } from 'vitest';
import { formulaTypeItemsService } from '@/modules/Roleplay/Rule/Service/Instance/formulaTypeItemsService';

describe('formulaTypeItems', () => {
  it('по умолчанию: Число / От характеристики / Размерное число', () => {
    expect(formulaTypeItemsService.formulaTypeItems('fixed', undefined, false)).toEqual([
      { label: 'Число', value: 'fixed' },
      { label: 'От характеристики', value: 'characteristic' },
      { label: 'Размерное число', value: 'dimensional' },
    ]);
  });

  it('actionCharacteristic в профиле оружия показывается как «От характеристики»', () => {
    expect(
      formulaTypeItemsService.formulaTypeItems(
        'actionCharacteristic',
        ['fixed', 'actionCharacteristic', 'dimensional'],
        false,
      ),
    ).toEqual([
      { label: 'Число', value: 'fixed' },
      { label: 'От характеристики', value: 'actionCharacteristic' },
      { label: 'Размерное число', value: 'dimensional' },
    ]);
  });

  it('текущий actionCharacteristic подменяет characteristic в дефолтном списке (без дубля подписи)', () => {
    const items = formulaTypeItemsService.formulaTypeItems('actionCharacteristic', undefined, false);
    expect(items.filter((item) => item.label === 'От характеристики')).toHaveLength(1);
    expect(items.find((item) => item.label === 'От характеристики')?.value).toBe('actionCharacteristic');
  });
});
