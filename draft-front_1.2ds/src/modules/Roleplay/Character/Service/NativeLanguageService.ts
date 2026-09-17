import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { NATIVE_LANGUAGE_GIFTED_LEVEL } from '@/modules/Roleplay/Character/Constant/Language/NATIVE_LANGUAGE_GIFTED_LEVEL';
import { VLADENIE_YAZYKOM_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/VLADENIE_YAZYKOM_ABILITY_CODE';

type NativeSpeechSheet = Pick<CharacterBuild, 'abilities' | 'nativeLanguageCode' | 'nativeLanguageText'>;

/** Автоэкземпляр владения родным языком 2: смена родного переносит дар, не плодит второй. */
export class NativeLanguageService {
  constructor(private readonly languages: { isSpeakable(rule: Rule): boolean }) {}

  sanitize<T extends Pick<CharacterBuild, 'nativeLanguageCode' | 'nativeLanguageText'>>(sheet: T, rules: Rule[]): T {
    const code = sheet.nativeLanguageCode?.trim() || null;
    if (code) {
      const rule = rules.find((entry) => entry.type === 'language' && entry.code === code);
      if (rule && this.languages.isSpeakable(rule)) {
        return { ...sheet, nativeLanguageCode: code, nativeLanguageText: null };
      }

      return { ...sheet, nativeLanguageCode: null, nativeLanguageText: rule?.name ?? code };
    }
    const text = sheet.nativeLanguageText?.trim() || null;

    return { ...sheet, nativeLanguageCode: null, nativeLanguageText: text };
  }

  syncNativeSpeech<T extends NativeSpeechSheet>(sheet: T, rules: Rule[]): T {
    const sanitized = this.sanitize(sheet, rules);
    const native = this.nativeDomain(sanitized, rules);
    let abilities = sanitized.abilities.map((ability) => ({ ...ability }));
    const gifted = abilities.filter((ability) => ability.ruleCode === VLADENIE_YAZYKOM_ABILITY_CODE && ability.gifted);
    if (!native) {
      abilities = abilities.filter((ability) => {
        if (ability.ruleCode !== VLADENIE_YAZYKOM_ABILITY_CODE || !ability.gifted) return true;
        if (ability.level > NATIVE_LANGUAGE_GIFTED_LEVEL) {
          ability.gifted = false;

          return true;
        }

        return false;
      });

      return { ...sanitized, abilities };
    }

    const existingExact = abilities.find((ability) => this.matchesNative(ability, native));
    const donor = existingExact ?? gifted[0];
    if (!donor) {
      abilities.push({
        ruleCode: VLADENIE_YAZYKOM_ABILITY_CODE,
        level: NATIVE_LANGUAGE_GIFTED_LEVEL,
        domain: native.name,
        domainCode: native.code,
        gifted: true,
        zone: 'or',
      });

      return { ...sanitized, abilities };
    }

    donor.domain = native.name;
    donor.domainCode = native.code;
    donor.gifted = true;
    donor.zone = donor.zone ?? 'or';
    if (donor.level < NATIVE_LANGUAGE_GIFTED_LEVEL) donor.level = NATIVE_LANGUAGE_GIFTED_LEVEL;

    const donorKey = `${donor.domainCode ?? ''}|${donor.domain ?? ''}`;
    abilities = abilities.filter((ability) => {
      if (ability === donor) return true;
      if (ability.ruleCode !== VLADENIE_YAZYKOM_ABILITY_CODE) return true;
      const key = `${ability.domainCode ?? ''}|${ability.domain ?? ''}`;
      if (key !== donorKey) {
        if (ability.gifted && ability.level <= NATIVE_LANGUAGE_GIFTED_LEVEL) return false;
        if (ability.gifted) ability.gifted = false;

        return true;
      }

      return false;
    });

    return { ...sanitized, abilities };
  }

  private matchesNative(ability: CharacterAbility, native: { code: string | null; name: string }): boolean {
    if (ability.ruleCode !== VLADENIE_YAZYKOM_ABILITY_CODE) return false;
    if (native.code) return ability.domainCode === native.code;

    return !ability.domainCode && ability.domain === native.name;
  }

  nativeDomain(
    sheet: Pick<CharacterBuild, 'nativeLanguageCode' | 'nativeLanguageText'>,
    rules: Rule[],
  ): { code: string | null; name: string } | null {
    const code = sheet.nativeLanguageCode?.trim() || null;
    if (code) {
      const rule = rules.find((entry) => entry.type === 'language' && entry.code === code);

      return { code, name: rule?.name ?? code };
    }
    const text = sheet.nativeLanguageText?.trim() || null;
    if (text) return { code: null, name: text };

    return null;
  }

  giftedFloor(ability: CharacterAbility): number {
    if (!ability.gifted || ability.ruleCode !== VLADENIE_YAZYKOM_ABILITY_CODE) return ability.gifted ? 1 : 0;

    return NATIVE_LANGUAGE_GIFTED_LEVEL;
  }
}
