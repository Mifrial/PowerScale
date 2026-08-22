import type { ICharacterApi } from '@/modules/Roleplay/Character/Interface/ICharacterApi';
import * as mock from '@/modules/Roleplay/Character/Mock/mockCharacters';
import * as routed from '@/modules/Roleplay/Character/Mock/mockCharacterUpdate';

/**
 * Мок character API поверх «чистого» хранилища mockCharacters. Мутирующие операции проходят через
 * единый роутер (модель версий — Баг 1): во время активной сессии — в оверлей, иначе — в latest
 * с автоподачей членств на модерацию.
 */
export const mockCharacterApi: ICharacterApi = {
  getCharacters: mock.fetchCharacters,
  getCharacter: mock.fetchCharacter,
  createCharacter: mock.createCharacter,
  updateCharacter: routed.updateCharacter,
  updateVisibility: mock.updateCharacterVisibility,
  addCustomRule: routed.addCustomRule,
  updateCustomRule: routed.updateCustomRule,
  migrateCharacter: mock.migrateCharacter,
  applyMigration: async (characterId, version, signal) => {
    const detail = await mock.applyMigration(characterId, version, signal);
    routed.applyVersionChange(characterId);

    return detail;
  },
};
