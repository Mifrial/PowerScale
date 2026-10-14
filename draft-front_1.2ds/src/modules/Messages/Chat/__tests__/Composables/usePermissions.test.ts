import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { usePermissions } from '@/modules/Messages/Chat/Composables/usePermissions';
import { registerAuthApi } from '@/modules/Core/Auth/init';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { mockAuthApi } from '@/modules/Core/Auth/Mock/mockAuthApi';
import { useAuthStore } from '@/modules/Core/Auth/Store/auth';
import { useUserStore } from '@/modules/Core/User/Store/users';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';

function listedChat(userId: number): Chat {
  return {
    id: 1,
    type: 'private',
    name: '',
    unreadCount: 0,
    lastReadMessageId: null,
    lastMessageAt: 0,
    members: [{ userId, status: 'member', joinedAt: 0 }],
  };
}

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
  registerAuthApi(mockAuthApi);
});

describe('usePermissions', () => {
  it('canWriteEmbedded: inbox-чат члена — писать можно', async () => {
    const auth = useAuthStore();
    await expect(auth.login('admin', 'test')).resolves.toBe(true);
    const { canWriteEmbedded } = usePermissions();
    const userId = useUserStore().currentUser?.id ?? 0;

    expect(canWriteEmbedded(listedChat(userId), false)).toBe(true);
  });

  it('canWriteEmbedded: donor-тред не в inbox, страница открыта — писать можно', async () => {
    const auth = useAuthStore();
    await expect(auth.login('admin', 'test')).resolves.toBe(true);
    const { canWriteEmbedded } = usePermissions();

    expect(canWriteEmbedded(null, true)).toBe(true);
  });

  it('canWriteEmbedded: donor-тред ещё не загружен — писать нельзя', async () => {
    const auth = useAuthStore();
    await expect(auth.login('admin', 'test')).resolves.toBe(true);
    const { canWriteEmbedded } = usePermissions();

    expect(canWriteEmbedded(null, false)).toBe(false);
  });

  it('canWriteEmbedded: гость не пишет во встроенный тред', () => {
    const { canWriteEmbedded } = usePermissions();

    expect(canWriteEmbedded(null, true)).toBe(false);
  });
});
