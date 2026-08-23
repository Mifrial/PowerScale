import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface DiceRollSpec {
  diceCount: number;
  dieSize: number;
  dieFaces: number;
  efficiency: number;
  /** Размер пула (мастерство), для записи 5↓к6. Scoring: dieSize = poolSize + efficiencySize. */
  poolSize?: number;
  /** Размер эффективности грани, для записи 4↓. */
  efficiencySize?: number;
  /** Вклады помех/преимуществ по источнику; нет голое adv. */
  advantages: AdvantageModifier[];
  /** Сдвиги мастерства (`modifyWith`), не кубы помех. */
  masteryAdjustments?: AdvantageModifier[];
  label?: string;
  /** Кто бросал: клик по имени в чате открывает карточку. */
  actorKey?: CombatEntityKey;
}
