import { describe, expect, it } from 'vitest';
import { ruleChipFromContext } from '@/modules/Roleplay/Rule/Utils/ruleChipFromContext';

describe('ruleChipFromContext', () => {
  it('без контекста — скрыт, каталог не нужен', () => {
    expect(ruleChipFromContext('movement', undefined)).toBeNull();
  });

  it('код есть в tokenLabels — имя из среза', () => {
    expect(
      ruleChipFromContext('movement', { tokenLabels: { movement: 'Движение' }, spaceId: 1, rulesRevision: 2 }),
    ).toEqual({
      id: 'movement',
      name: 'Движение',
    });
  });

  it('среза нет кода — скрыт даже при space/revision', () => {
    expect(
      ruleChipFromContext('absent', { tokenLabels: { movement: 'Движение' }, spaceId: 1, rulesRevision: 2 }),
    ).toBeNull();
  });
});
