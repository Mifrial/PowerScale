import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { IInGameSheetSource } from '@/modules/Roleplay/Character/Interface/IInGameSheetSource';

/** Сообщение F17, если in-game редактор не должен падать на latest. */
export function missingInGameSheetMessage(
  source: IInGameSheetSource | null | undefined,
  sheet: CharacterVersion | null | undefined,
): string | null {
  if (source == null) return 'Редактор в игре недоступен: нет источника листа сессии';
  if (sheet == null) return 'Не удалось загрузить версию листа в этой игре';

  return null;
}
