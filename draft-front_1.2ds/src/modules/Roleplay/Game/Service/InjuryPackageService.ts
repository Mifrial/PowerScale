import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { InjuryDamageLayer } from '@/modules/Roleplay/Game/Dto/InjuryDamageLayer';
import type { InjuryRollInput } from '@/modules/Roleplay/Game/Dto/InjuryRollInput';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { COLLAPSE_INJURY_LABEL } from '@/modules/Roleplay/Game/Constant/Injury/COLLAPSE_INJURY_LABEL';

/** Раскладывает одновременный урон разных типов на проверки увечья. */
export class InjuryPackageService {
  planFromLayers(
    layers: InjuryDamageLayer[],
    endurance: number,
    overlayExhaustion: number,
    actorKey: CombatEntityKey,
  ): InjuryRollInput[] {
    const safeEndurance = Math.max(1, Math.floor(endurance));
    const present = layers.filter((layer) => layer.hpDamage > 0);
    if (present.length <= 1) {
      const layer = present[0] ?? layers[0];
      if (!layer) {
        return [];
      }
      const woundStrength = layers.reduce(
        (sum, item) => sum + Math.max(0, item.woundFromHit ?? 0) + Math.max(0, item.cuttingWound ?? 0),
        0,
      );
      if (layer.hpDamage <= 0 && woundStrength <= 0) {
        return [];
      }

      return [
        {
          leftoverDamage: Math.max(0, layer.hpDamage),
          woundStrength,
          endurance: safeEndurance,
          exhaustion: Math.max(0, overlayExhaustion),
          attackSr: Math.max(0, layer.remainingSr),
          damageTypeCode: layer.damageTypeCode,
          actorKey,
        },
      ];
    }
    const planned: InjuryRollInput[] = [];
    let remainder = 0;
    let woundStrength = 0;
    for (const layer of present) {
      const hp = Math.max(0, layer.hpDamage);
      const fromType = Math.floor(hp / safeEndurance);
      remainder += hp % safeEndurance;
      woundStrength += Math.max(0, layer.woundFromHit ?? 0) + Math.max(0, layer.cuttingWound ?? 0);
      if (fromType > 0) {
        planned.push({
          leftoverDamage: hp,
          woundStrength: 0,
          endurance: safeEndurance,
          exhaustion: 0,
          attackSr: Math.max(0, layer.remainingSr),
          damageTypeCode: layer.damageTypeCode,
          actorKey,
          label: 'Проверка на увечье',
        });
      }
    }
    const fromCollapse = Math.floor(remainder / safeEndurance);
    if (fromCollapse > 0) {
      planned.push({
        leftoverDamage: remainder,
        woundStrength: 0,
        endurance: safeEndurance,
        exhaustion: 0,
        attackSr: 0,
        damageTypeCode: null,
        actorKey,
        label: `Проверка на увечье · ${COLLAPSE_INJURY_LABEL}`,
        forceSource: 'collapse',
      });
    }
    if (woundStrength > 0 || overlayExhaustion >= 7) {
      planned.push({
        leftoverDamage: 0,
        woundStrength,
        endurance: safeEndurance,
        exhaustion: Math.max(0, overlayExhaustion),
        attackSr: 0,
        damageTypeCode: null,
        actorKey,
      });
    }

    return planned;
  }

  describePlan(planned: InjuryRollInput[], rules: Rule[]): string | null {
    const bits: string[] = [];
    for (const input of planned) {
      const endurance = Math.max(1, Math.floor(input.endurance));
      const fromDamage = Math.floor(Math.max(0, input.leftoverDamage) / endurance);
      const remainder = Math.max(0, input.leftoverDamage) % endurance;
      if (input.forceSource === 'collapse' && fromDamage > 0) {
        bits.push(
          `${fromDamage} от ${COLLAPSE_INJURY_LABEL} (⌊${input.leftoverDamage} / ${endurance}⌋ из нераспределённых остатков)`,
        );
        continue;
      }
      if (fromDamage > 0) {
        const typeName = input.damageTypeCode
          ? (rules.find((rule) => rule.code === input.damageTypeCode)?.name.toLowerCase() ?? 'урона')
          : 'повреждений';
        bits.push(`${fromDamage} от ${typeName} (⌊${input.leftoverDamage} / ${endurance}⌋, остаток ${remainder})`);
      }
    }
    if (bits.length === 0) {
      return null;
    }

    return `Будут проверки на увечье: ${bits.join('; ')}.`;
  }
}
