/** Избранные способности одного персонажа: characterId → список ruleId. */
export interface CharacterAbilityFavorites {
  characterId: number;
  ruleIds: string[];
}
