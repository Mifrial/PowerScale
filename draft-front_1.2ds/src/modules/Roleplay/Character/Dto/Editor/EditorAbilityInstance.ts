import type { EditorAbilityLevel } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityLevel';

/**
 * Экземпляр множественного навыка в модели редактора: домен (значение словаря или текст), уровень
 * и пер-экземплярные требования (оценены относительно домена — «Письменность того же языка»).
 */
export interface EditorAbilityInstance {
  /** Значение домена: имя из словаря (domainCode задан) или свой текст. */
  domain: string;
  /** Код правила словаря домена (вид/язык); null — свободное текстовое значение. */
  domainCode: string | null;
  /** Уровень этого экземпляра (1..maxLevel способности). */
  level: number;
  /** Требования по уровням экземпляра (has_ability-требования домен-скоупированы). */
  levels: EditorAbilityLevel[];
}
