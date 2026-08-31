/** Избранные способности одного персонажа: characterId → список ruleCode. */
export interface CharacterAbilityFavorites {
  characterId: number;
  ruleCodes: string[];
}
