import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Характеристика блока навигации редактора: запись модели + резолвнутое правило.
 * Для производных (формула в спеке) — список кодов и сами записи базовых характеристик.
 */
export interface EditorStatView {
  characteristic: EditorCharacteristic;
  rule: Rule | undefined;
  derived: boolean;
  bases: EditorCharacteristic[];
}
