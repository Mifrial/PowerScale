/**
 * Вход user.findPage / userGroup.findPage.
 */
export interface FindPageQuery {
  limit: number;
  offset: number;
  q?: string;
  active?: boolean;
}
