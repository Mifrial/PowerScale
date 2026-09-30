export interface GameNpcListQuery {
  gameId: number;
  query?: string;
  status?: 'active' | 'proposed';
  limit?: number;
  cursor?: string;
}
