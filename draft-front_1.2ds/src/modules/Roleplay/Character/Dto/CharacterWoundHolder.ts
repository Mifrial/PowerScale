/** Держатель зажима раны: PHP {kind,id} или ключ mock-пути. */
export interface CharacterWoundHolder {
  kind: 'character' | 'npc';
  id: number;
}
