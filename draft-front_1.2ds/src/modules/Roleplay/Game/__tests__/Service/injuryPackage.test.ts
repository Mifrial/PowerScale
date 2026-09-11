import { describe, expect, it } from 'vitest';
import { injuryPackageService } from '@/modules/Roleplay/Game/Service/Instance/injuryPackageService';
import { COLLAPSE_INJURY_LABEL } from '@/modules/Roleplay/Game/Constant/Injury/COLLAPSE_INJURY_LABEL';

describe('InjuryPackageService', () => {
  it('дробящий 10 + электричество 8 при стойкости 3 → 3, 2 и упадок сил 1', () => {
    const planned = injuryPackageService.planFromLayers(
      [
        { hpDamage: 10, remainingSr: 2, damageTypeCode: 'blunt' },
        { hpDamage: 8, remainingSr: 2, damageTypeCode: 'electricity' },
      ],
      3,
      0,
      'character:2',
    );
    expect(planned).toHaveLength(3);
    expect(planned[0]?.leftoverDamage).toBe(10);
    expect(planned[0]?.damageTypeCode).toBe('blunt');
    expect(planned[1]?.leftoverDamage).toBe(8);
    expect(planned[1]?.damageTypeCode).toBe('electricity');
    expect(planned[2]?.leftoverDamage).toBe(3);
    expect(planned[2]?.forceSource).toBe('collapse');
    expect(planned[2]?.damageTypeCode).toBeNull();
    expect(planned[2]?.label).toContain(COLLAPSE_INJURY_LABEL);
    expect(injuryPackageService.describePlan(planned, [])).toBe(
      'Будут проверки на увечье: 3 от урона (⌊10 / 3⌋, остаток 1); 2 от урона (⌊8 / 3⌋, остаток 2); 1 от Упадок сил (⌊3 / 3⌋ из нераспределённых остатков).',
    );
  });

  it('один тип — одна классическая проверка, без упадка сил', () => {
    const planned = injuryPackageService.planFromLayers(
      [{ hpDamage: 10, remainingSr: 2, damageTypeCode: 'blunt' }],
      3,
      4,
      'character:2',
    );
    expect(planned).toHaveLength(1);
    expect(planned[0]?.exhaustion).toBe(4);
    expect(planned[0]?.forceSource).toBeUndefined();
  });
});
