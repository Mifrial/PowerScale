import type { EditorAbilityLevel } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityLevel';
import type { CharacterKnowledgeSlot } from '@/modules/Roleplay/Character/Dto/CharacterKnowledgeSlot';

/**
 * Экземпляр множественного навыка в модели редактора: домен (значение словаря или текст), уровень
 * и пер-экземплярные требования (оценены относительно домена — «Письменность того же языка»).
 */
export interface EditorAbilityInstance {
  /** Значение домена: имя из словаря (domainCode задан) или свой текст. */
  domain: string;
  /** Код правила словаря домена (вид/язык); null — свободное текстовое значение. */
  domainCode: string | null;
  fieldCode?: string | null;
  slots?: Record<string, CharacterKnowledgeSlot>;
  /** Уровень этого экземпляра (1..maxLevel способности). */
  level: number;
  /** Требования по уровням экземпляра (has_ability-требования домен-скоупированы). */
  levels: EditorAbilityLevel[];
  /** Фактически списанная цена экземпляра с учётом скидки пути. */
  paidCost?: number;
  /** Экземпляр занимает слот гранта с max_instances — снять можно только вместе с грантом. */
  bound?: boolean;
}
