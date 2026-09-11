import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityInstance } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityInstance';

export type DevelopmentSpellRowKind = 'catalog' | 'instance';

/** Строка каталога развития: обычная способность или каталог/экземпляр заклинания. */
export interface DevelopmentAbilityRow {
  key: string;
  ability: EditorAbility;
  spellRowKind: DevelopmentSpellRowKind | null;
  instance: EditorAbilityInstance | null;
}
