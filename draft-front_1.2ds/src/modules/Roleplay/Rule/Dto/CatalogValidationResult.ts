import type { CatalogProblemItem } from '@/modules/Roleplay/Rule/Dto/CatalogProblemItem';

/** Сводка валидации эффективного каталога (публикация и Save одного правила). */
export interface CatalogValidationResult {
  items: CatalogProblemItem[];
  spaceErrors: string[];
}
