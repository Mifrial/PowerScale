/** Метка боя в проекции боя. */
export interface GamePhpBattleMark {
  subject: { type: string; id: number };
  kind: string;
  value: number;
  source_code: string;
  source_label?: string | null;
  source_rule_code?: string | null;
  scope: { defense_against: { type: string; id: number } };
  consumes: string;
  expires: { kind: string; of: { type: string; id: number } };
}
