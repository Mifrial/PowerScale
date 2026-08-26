import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/** Источник эффективной версии листа в игре (оверлей / active). Регистрирует Game. */
export interface IInGameSheetSource {
  getEffectiveSheet(gameId: number, characterId: number, signal?: AbortSignal): Promise<CharacterVersion | null>;
}
