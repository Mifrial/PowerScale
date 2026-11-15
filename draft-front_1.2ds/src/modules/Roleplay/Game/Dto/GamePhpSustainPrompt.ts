import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';

/** Вопрос поддержания на входе хода. */
export interface GamePhpSustainPrompt extends ActiveSpell {
  defaultSustainPower: DimensionalNumberValue | null;
}
