import type { User } from '@/modules/Core/User/Dto/User';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { SheetAudience } from '@/modules/Roleplay/Character/Dto/SheetAudience';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import type { SheetRole } from '@/modules/Roleplay/Character/Interface/SheetRole';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';

/**
 * Оценка видимости листа (персонажа/НПС) по зонам `SheetVisibility`:
 * владелец и super_admin — всегда; инжектированная роль с `fullAccess` (ведущие) — всегда;
 * иначе — объединение секций правил, чья аудитория подходит зрителю в контексте.
 * В игровом контексте 'all' = участники; на standalone — `character.view`.
 */
export class SheetAccessService {
  constructor(
    private readonly getRoles: () => SheetRole[],
    private readonly hasCharacterView: (user: User) => boolean,
  ) {}

  canSeeSheet(user: User | null | undefined, visibility: SheetVisibility, ctx: SheetAccessContext): boolean {
    if (!user) return false;
    if (user.super_admin || (ctx.ownerId !== null && user.id === ctx.ownerId)) return true;
    const roles = this.resolvedRoles(ctx);
    if (roles.some((role) => role.fullAccess)) return true;

    return visibility.some((rule) => this.audienceMatches(user, rule.audience, ctx, roles));
  }

  /** Секции листа, видимые пользователю в контексте ([] — лист скрыт для него). */
  visibleSheetSections(
    user: User | null | undefined,
    visibility: SheetVisibility,
    ctx: SheetAccessContext,
  ): SheetSection[] {
    if (!this.canSeeSheet(user, visibility, ctx)) return [];
    if (!user) return [];
    if (user.super_admin || (ctx.ownerId !== null && user.id === ctx.ownerId)) return [...SHEET_VISIBLE_SECTIONS];
    const roles = this.resolvedRoles(ctx);
    if (roles.some((role) => role.fullAccess)) return [...SHEET_VISIBLE_SECTIONS];

    const granted = new Set<SheetSection>();
    for (const rule of visibility) {
      if (this.audienceMatches(user, rule.audience, ctx, roles)) {
        for (const section of rule.sections) granted.add(section);
      }
    }

    return [...granted];
  }

  private resolvedRoles(ctx: SheetAccessContext): SheetRole[] {
    return this.getRoles().filter((role) => role.resolve(ctx));
  }

  private audienceMatches(user: User, audience: SheetAudience, ctx: SheetAccessContext, roles: SheetRole[]): boolean {
    if (audience === 'all') {
      // В игре 'all' = участники (зритель уже прошёл доступ к игре); на standalone — character.view.
      return ctx.gameId !== null || this.hasCharacterView(user);
    }
    if (audience === 'gm') return roles.some((role) => role.name === 'gm');

    return audience.includes(user.id);
  }
}
