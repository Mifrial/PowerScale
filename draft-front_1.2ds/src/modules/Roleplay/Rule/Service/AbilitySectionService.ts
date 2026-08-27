import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { NamedOption } from '@/modules/Roleplay/Rule/Dto/NamedOption';
import { ABILITY_SECTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SECTIONS';

export class AbilitySectionService {
  constructor(private readonly sections: readonly NamedOption[] = ABILITY_SECTIONS) {}

  label(code: string): string {
    return this.sections.find((section) => section.code === code)?.name ?? code;
  }

  isKnown(code: string): boolean {
    return this.sections.some((section) => section.code === code);
  }

  fromSpec(spec: Pick<AbilitySpecBase, 'section'> | null | undefined): string | null {
    const code = spec?.section?.trim();

    return code ? code : null;
  }

  fromKeywordCodes(keywordCodes: string[]): string | null {
    for (const code of keywordCodes) {
      if (this.isKnown(code)) return code;
    }

    return null;
  }
}
