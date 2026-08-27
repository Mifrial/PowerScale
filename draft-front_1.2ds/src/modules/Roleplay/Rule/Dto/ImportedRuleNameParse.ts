/** Разбор хвоста импортного имени: схема не часть названия. */
export interface ImportedRuleNameParse {
  name: string;
  domainRef: string | null;
  parameterCode: string | null;
  parameterMax: number | null;
  hadSchemaTail: boolean;
}
