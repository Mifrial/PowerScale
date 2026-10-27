import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Поля оружия, общие для всех строк широкого удара. */
export interface WideStrikeFields {
  itemInventoryId: number;
  itemRuleCode: string;
  profileType: string;
  profileIndex: number;
  instanceIndex?: number;
  ammunitionInventoryId?: number;
}

/**
 * Строки `context.strikes` широкого удара.
 *
 * Условия живут на строке удара, а `target` — только пара `{type, id}`:
 * серверный разбор отвергает лишние ключи в цели.
 */
export function wideStrikeRows(
  targets: (GamePhpParticipant & { conditions?: Record<string, unknown> })[],
  fields: WideStrikeFields,
) {
  return targets.map(({ conditions, ...target }) => ({
    itemInventoryId: fields.itemInventoryId,
    itemRuleCode: fields.itemRuleCode,
    profileType: fields.profileType,
    profileIndex: fields.profileIndex,
    instanceIndex: fields.instanceIndex ?? 0,
    ...(fields.ammunitionInventoryId !== undefined
      ? { ammunitionInventoryId: fields.ammunitionInventoryId }
      : {}),
    ...(conditions !== undefined ? { conditions } : {}),
    target,
  }));
}
