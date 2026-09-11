import { describe, expect, it } from 'vitest';
import { spellDamageService } from '@/modules/Roleplay/Rule/Service/Instance/spellDamageService';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';

const damage: SpellDamage = {
  damage_type_code: 'electricity',
  experience_keyword_code: 'electromancy',
  power_modify_steps: [
    { min_experience: 0, modify: 3 },
    { min_experience: 10, modify: 4 },
    { min_experience: 20, modify: 5 },
    { min_experience: 30, modify: 6 },
  ],
};

describe('SpellDamageService', () => {
  it('берёт наибольший порог опыта не выше текущего', () => {
    expect(spellDamageService.modifyForExperience(damage, 0)).toBe(3);
    expect(spellDamageService.modifyForExperience(damage, 9)).toBe(3);
    expect(spellDamageService.modifyForExperience(damage, 10)).toBe(4);
    expect(spellDamageService.modifyForExperience(damage, 20)).toBe(5);
    expect(spellDamageService.modifyForExperience(damage, 30)).toBe(6);
  });

  it('{4|0}.modify(+3) = {4|1}', () => {
    expect(spellDamageService.amountFromPower({ base: 4, size: 0 }, 3)).toEqual({ base: 4, size: 1 });
  });

  it('falloff снижает размер и обнуляет урон ниже порога', () => {
    const falloff = { free_ipari: 2, size_per_extra_ipari: 1, min: { base: 3, size: -1 } };
    expect(spellDamageService.applyFalloff({ base: 4, size: 0 }, 2, falloff)).toEqual({ base: 4, size: 0 });
    expect(spellDamageService.applyFalloff({ base: 4, size: 0 }, 3, falloff)).toEqual({ base: 4, size: -1 });
    expect(spellDamageService.applyFalloff({ base: 3, size: 0 }, 4, falloff)).toBeNull();
  });
});
