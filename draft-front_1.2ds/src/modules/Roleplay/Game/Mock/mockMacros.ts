import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro';
import type { CreateMacroData } from '@/modules/Roleplay/Game/Dto/CreateMacroData';
import type { UpdateMacroData } from '@/modules/Roleplay/Game/Dto/UpdateMacroData';
import { mockGetCurrentUser } from '@/modules/Core/Auth/Mock/mockAuth';

const delay = (ms = 50, signal?: AbortSignal) =>
  new Promise<void>((resolve, reject) => {
    const timer = setTimeout(() => {
      if (signal?.aborted) {
        reject(new DOMException('Aborted', 'AbortError'));
      } else {
        resolve();
      }
    }, ms);

    signal?.addEventListener(
      'abort',
      () => {
        clearTimeout(timer);
        reject(new DOMException('Aborted', 'AbortError'));
      },
      { once: true },
    );
  });

let macroIdSeq = 100;

const macrosByUser: Record<number, UserMacro[]> = {
  1: [
    {
      id: 1,
      userId: 1,
      name: 'Атака мечом',
      textTemplate: 'Атакую мечом',
      rolls: [
        { rollFormula: '5d6', efficiency: 3, adv: 1, dieSize: 0, rollLabel: 'Удар 1', variableAdvantages: false },
      ],
      createdAt: '2026-07-25T10:00:00',
    },
    {
      id: 2,
      userId: 1,
      name: 'Проверка силы',
      textTemplate: 'Проверка силы',
      rolls: [
        { rollFormula: '4d6', efficiency: 3, adv: 0, dieSize: 1, rollLabel: 'Проверка силы', variableAdvantages: true },
      ],
      createdAt: '2026-07-25T10:05:00',
    },
    {
      id: 3,
      userId: 1,
      name: 'Полная атака',
      textTemplate: 'Атакую дважды',
      rolls: [
        { rollFormula: '5d6', efficiency: 3, adv: 1, dieSize: 0, rollLabel: 'Удар 1', variableAdvantages: false },
        { rollFormula: '5d6', efficiency: 3, adv: 0, dieSize: 0, rollLabel: 'Удар 2', variableAdvantages: false },
      ],
      createdAt: '2026-07-25T10:06:00',
    },
    { id: 4, userId: 1, name: 'Отдохнуть', textTemplate: 'Отдыхаю', rolls: [], createdAt: '2026-07-25T10:07:00' },
  ],
};

async function currentUserId(): Promise<number> {
  const me = await mockGetCurrentUser();

  return me?.id ?? 1;
}

export async function mockGetMyMacros(signal?: AbortSignal): Promise<UserMacro[]> {
  await delay(50, signal);
  const userId = await currentUserId();

  return [...(macrosByUser[userId] || [])];
}

export async function mockCreateMacro(data: CreateMacroData, signal?: AbortSignal): Promise<UserMacro> {
  await delay(50, signal);
  const userId = await currentUserId();
  if (!macrosByUser[userId]) macrosByUser[userId] = [];
  macroIdSeq++;
  const macro: UserMacro = { id: macroIdSeq, userId, ...data, createdAt: new Date().toISOString() };
  macrosByUser[userId].push(macro);

  return { ...macro };
}

export async function mockUpdateMacro(id: number, data: UpdateMacroData, signal?: AbortSignal): Promise<UserMacro> {
  await delay(50, signal);
  const userId = await currentUserId();
  const list = macrosByUser[userId] || [];
  const idx = list.findIndex((m) => m.id === id);
  if (idx === -1) throw new Error('Макрос не найден');
  list[idx] = { ...list[idx], ...data };

  return { ...list[idx] };
}

export async function mockDeleteMacro(id: number, signal?: AbortSignal): Promise<void> {
  await delay(50, signal);
  const userId = await currentUserId();
  const list = macrosByUser[userId] || [];
  const idx = list.findIndex((m) => m.id === id);
  if (idx !== -1) list.splice(idx, 1);
}
