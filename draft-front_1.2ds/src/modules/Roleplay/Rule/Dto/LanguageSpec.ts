import type { LanguageRole } from '@/modules/Roleplay/Rule/Enum/LanguageRole';

/** Спека правила-языка: сток семьи или изучаемый язык, дерево через parent_code. */
export interface LanguageSpec {
  type: 'language';
  role: LanguageRole;
  parent_code: string | null;
  script_codes: string[];
}
