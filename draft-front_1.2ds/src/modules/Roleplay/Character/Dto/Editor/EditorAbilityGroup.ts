import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';

/** Группа способностей в модели редактора: группирующее правило + его участники. */
export interface EditorAbilityGroup {
  ruleId: string;
  code: string;
  name: string;
  description: string;
  /** Сколько способностей из группы можно выбрать (1 = «1 из группы», -1 = без лимита). */
  selectLimit: number;
  members: EditorAbility[];
}
