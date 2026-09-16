/** Режим улучшения удара: калечить или щадить на проверке увечья. */
export interface StrikeUpgradeMode {
  code: string;
  label: string;
  injury_check_advantage: number;
}
