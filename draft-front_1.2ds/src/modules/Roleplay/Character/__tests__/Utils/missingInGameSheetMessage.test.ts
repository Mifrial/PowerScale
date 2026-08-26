import { describe, expect, it } from 'vitest';
import { missingInGameSheetMessage } from '@/modules/Roleplay/Character/Utils/missingInGameSheetMessage';
import type { IInGameSheetSource } from '@/modules/Roleplay/Character/Interface/IInGameSheetSource';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

const source: IInGameSheetSource = {
  getEffectiveSheet: async () => null,
};
const sheet = { name: 'A', rulesRevision: 1 } as CharacterVersion;

describe('missingInGameSheetMessage', () => {
  it('без зарегистрированного source — ошибка, не latest', () => {
    expect(missingInGameSheetMessage(null, sheet)).toBe('Редактор в игре недоступен: нет источника листа сессии');
  });

  it('source есть, листа нет — ошибка', () => {
    expect(missingInGameSheetMessage(source, null)).toBe('Не удалось загрузить версию листа в этой игре');
  });

  it('лист получен — без ошибки', () => {
    expect(missingInGameSheetMessage(source, sheet)).toBeNull();
  });
});
