import { beforeEach, describe, expect, it } from 'vitest';
import { sheetAccessService } from '@/modules/Roleplay/Character/Service/Instance/sheetAccessService';
import { registerSheetRole, resetSheetRoles } from '@/modules/Roleplay/Character/init';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { User } from '@/modules/Core/User/Dto/User';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';

function makeUser(id: number, overrides: Partial<User> = {}): User {
  return {
    id,
    name: 'Пользователь',
    login: `user${id}`,
    email: `user${id}@test.com`,
    groups: ['Игрок'],
    registered: '01.01.2026',
    active: true,
    ...overrides,
  };
}

function makeCtx(user: User, gameId: number | null = 1): SheetAccessContext {
  return { user, ownerId: 1, characterId: 10, gameId };
}

const brief: SheetVisibility = [{ audience: 'all', sections: ['shortDescription'] }];

describe('canSeeSheet / visibleSheetSections', () => {
  beforeEach(() => {
    resetSheetRoles();
  });

  it('владелец и super_admin видят всё', () => {
    const owner = makeCtx(makeUser(1));
    expect(sheetAccessService.canSeeSheet(makeUser(1), [], owner)).toBe(true);
    expect(sheetAccessService.visibleSheetSections(makeUser(1), [], owner)).toEqual(SHEET_VISIBLE_SECTIONS);

    const admin = makeUser(9, { super_admin: true });
    expect(sheetAccessService.visibleSheetSections(admin, [], makeCtx(admin))).toEqual(SHEET_VISIBLE_SECTIONS);
  });

  it('без правил лист скрыт от постороннего', () => {
    const ctx = makeCtx(makeUser(5));
    expect(sheetAccessService.canSeeSheet(makeUser(5), [], ctx)).toBe(false);
    expect(sheetAccessService.visibleSheetSections(makeUser(5), [], ctx)).toEqual([]);
  });

  it('в игровом контексте аудитория all = участники (секции из правила)', () => {
    const ctx = makeCtx(makeUser(5));
    expect(sheetAccessService.visibleSheetSections(makeUser(5), brief, ctx)).toEqual(['shortDescription']);
  });

  it('правило для списка пользователей даёт секции только им', () => {
    const visibility: SheetVisibility = [{ audience: [3, 5], sections: ['inventory'] }];
    expect(sheetAccessService.visibleSheetSections(makeUser(3), visibility, makeCtx(makeUser(3)))).toEqual([
      'inventory',
    ]);
    expect(sheetAccessService.canSeeSheet(makeUser(7), visibility, makeCtx(makeUser(7)))).toBe(false);
  });

  it('роль с fullAccess (ведущие) даёт полный доступ независимо от правил', () => {
    registerSheetRole({ name: 'gm', fullAccess: true, resolve: (ctx) => ctx.user.id === 5 });
    const ctx = makeCtx(makeUser(5));
    expect(sheetAccessService.visibleSheetSections(makeUser(5), [], ctx)).toEqual(SHEET_VISIBLE_SECTIONS);
    // другой игрок без правил — не видит
    expect(sheetAccessService.canSeeSheet(makeUser(7), [], makeCtx(makeUser(7)))).toBe(false);
  });

  it('зона аудитории gm требует роли', () => {
    registerSheetRole({ name: 'gm', fullAccess: false, resolve: (ctx) => ctx.user.id === 5 });
    const visibility: SheetVisibility = [{ audience: 'gm', sections: ['fullDescription'] }];
    expect(sheetAccessService.visibleSheetSections(makeUser(5), visibility, makeCtx(makeUser(5)))).toEqual([
      'fullDescription',
    ]);
    expect(sheetAccessService.visibleSheetSections(makeUser(7), visibility, makeCtx(makeUser(7)))).toEqual([]);
  });

  it('на standalone аудитория all требует character.view', () => {
    const ctx = makeCtx(makeUser(5), null);
    expect(sheetAccessService.visibleSheetSections(makeUser(5), brief, ctx)).toEqual([]);
    const withView = makeUser(5, { permissions: ['character.view'] });
    expect(sheetAccessService.visibleSheetSections(withView, brief, makeCtx(withView, null))).toEqual([
      'shortDescription',
    ]);
  });
});
