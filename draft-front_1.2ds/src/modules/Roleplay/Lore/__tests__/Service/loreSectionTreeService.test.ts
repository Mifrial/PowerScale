import { describe, expect, it } from 'vitest';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import { LoreSectionTreeService } from '@/modules/Roleplay/Lore/Service/LoreSectionTreeService';

function section(code: string, parentCode: string | null = null, sortOrder = 0): LoreSectionInput {
  return { code, name: code, description: '', parentCode, sortOrder, icon: '' };
}

const service = new LoreSectionTreeService();

describe('LoreSectionTreeService', () => {
  it('добавляет раздел и нумерует соседей по десять', () => {
    const result = service.add([section('history', null, 10)], section('people'));

    expect(result.map((item) => [item.code, item.sortOrder])).toEqual([
      ['history', 10],
      ['people', 20],
    ]);
  });

  it('вкладывает раздел внутрь целевого при placement child', () => {
    const result = service.drop(
      [section('history', null, 10), section('people', null, 20)],
      'people',
      'history',
      'child',
    );

    expect(result.find((item) => item.code === 'people')?.parentCode).toBe('history');
  });

  it('переносит раздел после целевого', () => {
    const result = service.drop(
      [section('history', null, 10), section('people', null, 20), section('places', null, 30)],
      'places',
      'history',
      'after',
    );

    expect(result.map((item) => item.code)).toEqual(['history', 'places', 'people']);
    expect(result.find((item) => item.code === 'places')?.parentCode).toBeNull();
  });

  it('не даёт вложить раздел в собственного потомка', () => {
    const sections = [section('history', null, 10), section('wars', 'history', 10)];
    const result = service.drop(sections, 'history', 'wars', 'child');

    expect(result.find((item) => item.code === 'history')?.parentCode).toBeNull();
  });

  it('не удаляет раздел с дочерними', () => {
    const sections = [section('history', null, 10), section('wars', 'history', 10)];
    const result = service.remove(sections, 'history');

    expect(result.map((item) => item.code)).toEqual(['history', 'wars']);
  });

  it('удаляет лист и перенумеровывает соседей', () => {
    const sections = [section('history', null, 10), section('people', null, 20)];
    const result = service.remove(sections, 'history');

    expect(result.map((item) => [item.code, item.sortOrder])).toEqual([['people', 10]]);
  });

  it('переносит раздел в другого родителя через update и нумерует обоих', () => {
    const sections = [section('history', null, 10), section('people', null, 20), section('wars', 'history', 10)];
    const result = service.update(sections, 'people', { parentCode: 'history' });

    expect(result.find((item) => item.code === 'people')?.parentCode).toBe('history');
    expect(result.find((item) => item.code === 'people')?.sortOrder).toBe(20);
    expect(result.find((item) => item.code === 'wars')?.sortOrder).toBe(10);
  });

  it('поиск потомков возвращает сам узел и вложенные', () => {
    const sections = [
      section('history', null, 10),
      section('wars', 'history', 10),
      section('battles', 'wars', 10),
      section('people', null, 20),
    ];

    expect(service.descendantCodes(sections, 'history').sort()).toEqual(['battles', 'history', 'wars']);
  });
});
