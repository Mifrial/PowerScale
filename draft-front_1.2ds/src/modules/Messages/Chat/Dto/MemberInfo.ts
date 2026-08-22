export interface MemberInfo {
  userId: number;
  status: string;
  joinedAt: string;
  /** Роль участника в чате (значения задаёт домен; для игровых чатов 'gm'/'player'). */
  role?: string;
}
