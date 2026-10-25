import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { resetRegisteredApis } from '@/modules/Core/Engine/init';
import { registerUserApi } from '@/modules/Core/User/init';
import { mockUserApi } from '@/modules/Core/User/Mock/mockUserApi';
import { users as mockUsers } from '@/modules/Core/User/Mock/mockUsers';
import { useUserStore } from '@/modules/Core/User/Store/users';
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi';
import type { User } from '@/modules/Core/User/Dto/User';

beforeEach(() => {
  setActivePinia(createPinia());
  resetRegisteredApis();
});

function deferred<T>() {
  let resolve: (value: T) => void = () => undefined;
  let reject: (reason?: unknown) => void = () => undefined;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}

function countingApi(getUsersByIds: IUserApi['getUsersByIds']): IUserApi {
  return {
    ...mockUserApi,
    getUsersByIds,
  };
}

describe('users store', () => {
  it('coalesces concurrent ensureUsers calls for the same ids', async () => {
    const gate = deferred<User[]>();
    const calls: number[][] = [];
    registerUserApi(
      countingApi((ids) => {
        calls.push([...ids]);

        return gate.promise;
      }),
    );
    const store = useUserStore();

    const first = store.ensureUsers([1, 2]);
    const second = store.ensureUsers([1, 2]);

    expect(calls).toEqual([[1, 2]]);

    gate.resolve([mockUsers[0], mockUsers[1]]);
    await Promise.all([first, second]);

    expect(store.users.map((user) => user.id)).toEqual([1, 2]);
  });

  it('requests only ids that are not already pending', async () => {
    const firstGate = deferred<User[]>();
    const secondGate = deferred<User[]>();
    const calls: number[][] = [];
    registerUserApi(
      countingApi((ids) => {
        calls.push([...ids]);

        return calls.length === 1 ? firstGate.promise : secondGate.promise;
      }),
    );
    const store = useUserStore();

    const first = store.ensureUsers([1, 2]);
    const second = store.ensureUsers([2, 3]);

    expect(calls).toEqual([[1, 2], [3]]);

    firstGate.resolve([mockUsers[0], mockUsers[1]]);
    secondGate.resolve([mockUsers[2]]);
    await Promise.all([first, second]);

    expect(store.users.map((user) => user.id)).toEqual([1, 2, 3]);
  });

  it('retries after a failed ensureUsers request', async () => {
    const gate = deferred<User[]>();
    let calls = 0;
    registerUserApi(
      countingApi(() => {
        calls += 1;
        if (calls === 1) return gate.promise;

        return Promise.resolve([mockUsers[0]]);
      }),
    );
    const store = useUserStore();

    const first = store.ensureUsers([1]);
    gate.reject(new Error('boom'));
    await first;

    expect(store.users).toHaveLength(0);

    await store.ensureUsers([1]);

    expect(calls).toBe(2);
    expect(store.users.map((user) => user.id)).toEqual([1]);
  });

  it('does not request users that are already loaded', async () => {
    let calls = 0;
    registerUserApi(
      countingApi(() => {
        calls += 1;

        return Promise.resolve([]);
      }),
    );
    const store = useUserStore();
    store.users.push(mockUsers[0]);

    await store.ensureUsers([mockUsers[0].id]);

    expect(calls).toBe(0);
  });
});
