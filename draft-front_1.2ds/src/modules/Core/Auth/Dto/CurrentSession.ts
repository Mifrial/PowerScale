import type { User } from '@/modules/Core/User/Dto/User';

export type CurrentSession = { kind: 'guest' } | { kind: 'user'; user: User };
