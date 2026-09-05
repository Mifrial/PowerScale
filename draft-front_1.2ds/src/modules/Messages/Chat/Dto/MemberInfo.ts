export interface MemberInfo {
  userId: number;
  status: string;
  joinedAt: number;
  /** Роль участника в чате (значения задаёт домен; для игровых чатов 'gm'/'player'). */
  role?: string;
}
