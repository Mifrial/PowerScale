/** Блок «Владение оружием» панели предмета: семья оружия, лестница и текущий уровень прокачки. */
export interface ItemMasteryView {
  /** Правило «Владение оружием» (domain_ref weapon-family). */
  masteryRuleCode: string;
  familyName: string;
  familyCode: string;
  /** Лестница стоимости уровней семьи (правило weapon_family). */
  ladder: number[];
  /** Текущий уровень владения семьёй (0 — не обучено). */
  level: number;
  maxLevel: number;
}
