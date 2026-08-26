import type { SheetAudience } from '@/modules/Roleplay/Character/Dto/SheetAudience';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';

/** Одно правило зоны видимости листа: аудитория + видимые секции. */
export interface SheetVisibilityRule {
  audience: SheetAudience;
  sections: SheetSection[];
}
