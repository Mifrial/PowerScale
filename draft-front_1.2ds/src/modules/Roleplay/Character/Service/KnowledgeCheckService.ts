import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterKnowledgeSlot } from '@/modules/Roleplay/Character/Dto/CharacterKnowledgeSlot';
import type { KnowledgeAnswerCompletenessArgs } from '@/modules/Roleplay/Character/Dto/Knowledge/KnowledgeAnswerCompletenessArgs';
import type { KnowledgePracticeSituation } from '@/modules/Roleplay/Character/Dto/Knowledge/KnowledgePracticeSituation';
import type { KnowledgeAnswerCompleteness } from '@/modules/Roleplay/Character/Enum/KnowledgeAnswerCompleteness';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { KNOWLEDGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_ABILITY_CODE';
import { KNOWLEDGE_PRACTICE_GATE_CODES } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_PRACTICE_GATE_CODES';
import { LAW_DEFENSE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/LAW_DEFENSE_ABILITY_CODE';
import { BODY_TYPE_KNOWLEDGE_KEYWORD_IDS } from '@/modules/Roleplay/Rule/Constant/Keyword/BODY_TYPE_KNOWLEDGE_KEYWORD_IDS';
import { knowledgeInstanceService } from '@/modules/Roleplay/Character/Service/Instance/knowledgeInstanceService';
import type { KnowledgeInstanceService } from '@/modules/Roleplay/Character/Service/KnowledgeInstanceService';
import { raceSpecService } from '@/modules/Roleplay/Rule/init';

/**
 * Эффективный уровень знания, нехватка, сложность', полнота ответа и ворота практики.
 */
export class KnowledgeCheckService {
  constructor(private readonly knowledge: KnowledgeInstanceService = knowledgeInstanceService) {}

  effectiveLevel(
    abilities: readonly CharacterAbility[],
    fieldCode: string,
    slots: Record<string, CharacterKnowledgeSlot>,
    rules: readonly Rule[],
  ): number {
    const asked = this.withSpeciesCodes(slots, rules);
    let best = 0;
    for (const ability of abilities) {
      if (ability.ruleCode !== KNOWLEDGE_ABILITY_CODE || ability.fieldCode !== fieldCode) continue;
      const known = this.withSpeciesCodes(ability.slots, rules);
      if (this.knowledge.sameSlots(known, asked)) {
        best = Math.max(best, ability.level);
        continue;
      }
      best = Math.max(best, this.closeSpeciesLevel(ability, asked, rules));
    }

    return Math.max(0, Math.min(3, best));
  }

  shortage(level: number, band: number): number {
    return Math.max(0, band - level);
  }

  raisedDifficulty(base: DimensionalNumberValue, shortage: number): DimensionalNumberValue {
    return { base: base.base, size: base.size + shortage };
  }

  answerCompleteness(args: KnowledgeAnswerCompletenessArgs): KnowledgeAnswerCompleteness {
    if (!args.success) return 'fail';
    if (args.fieldCode === 'diseases' && args.physiologyLevel < 1) return 'incomplete';
    if (args.shortage > 0) return 'incomplete';

    return 'full';
  }

  lawDefenseDelta(
    abilities: readonly CharacterAbility[],
    fieldCode: string,
    regionSlot: CharacterKnowledgeSlot | undefined,
  ): number {
    if (fieldCode !== 'laws' || !regionSlot) return 0;
    const known = abilities.some(
      (ability) =>
        ability.ruleCode === KNOWLEDGE_ABILITY_CODE &&
        ability.fieldCode === 'laws' &&
        this.knowledge.sameSlots(ability.slots, { region: regionSlot }),
    );
    if (!known) return 0;
    const defense = abilities.find((ability) => ability.ruleCode === LAW_DEFENSE_ABILITY_CODE);

    return defense && defense.level > 0 ? defense.level : 0;
  }

  practiceApplies(
    code: string,
    abilities: readonly CharacterAbility[],
    situation: KnowledgePracticeSituation,
    rules: readonly Rule[],
  ): boolean {
    if (!KNOWLEDGE_PRACTICE_GATE_CODES.includes(code)) return false;
    if (code === 'poisk-trav') return this.plantsLevel(abilities, situation.placeRegion, rules) >= 1;
    if (code === 'farmatsiya') {
      return (
        this.physiologyLevel(abilities, situation.targetSpecies, rules) >= 1 &&
        this.plantsLevel(abilities, situation.placeRegion, rules) >= 1
      );
    }

    return this.physiologyLevel(abilities, situation.targetSpecies, rules) >= 1;
  }

  private physiologyLevel(
    abilities: readonly CharacterAbility[],
    species: CharacterKnowledgeSlot | undefined,
    rules: readonly Rule[],
  ): number {
    if (!species) return 0;

    return this.effectiveLevel(abilities, 'physiology', { species }, rules);
  }

  private plantsLevel(
    abilities: readonly CharacterAbility[],
    region: CharacterKnowledgeSlot | undefined,
    rules: readonly Rule[],
  ): number {
    if (!region) return 0;

    return this.effectiveLevel(abilities, 'plants', { region }, rules);
  }

  private closeSpeciesLevel(
    ability: CharacterAbility,
    slots: Record<string, CharacterKnowledgeSlot>,
    rules: readonly Rule[],
  ): number {
    const field = this.knowledge.fieldOf(ability.fieldCode);
    if (!field?.slots.some((slot) => slot.key === 'species')) return 0;
    const known = this.withSpeciesCodes(ability.slots, rules)?.species;
    const asked = this.withSpeciesCodes(slots, rules)?.species;
    const knownCode = known?.code ?? null;
    const askedCode = asked?.code ?? null;
    if (!knownCode || !askedCode || knownCode === askedCode) return 0;
    const knownRule = rules.find((rule) => rule.type === 'species' && rule.code === knownCode);
    const askedRule = rules.find((rule) => rule.type === 'species' && rule.code === askedCode);
    if (!this.sharesBodyType(knownRule, askedRule)) return 0;

    return Math.max(0, ability.level - 1);
  }

  private sharesBodyType(left: Rule | undefined, right: Rule | undefined): boolean {
    if (!left || !right) return false;
    const bodyIds = new Set(BODY_TYPE_KNOWLEDGE_KEYWORD_IDS);
    const leftIds = (left.keywordIds ?? []).filter((id) => bodyIds.has(id));

    return leftIds.some((id) => (right.keywordIds ?? []).includes(id));
  }

  private withSpeciesCodes(
    slots: Record<string, CharacterKnowledgeSlot> | undefined,
    rules: readonly Rule[],
  ): Record<string, CharacterKnowledgeSlot> | undefined {
    if (!slots) return slots;
    const species = slots.species;
    if (!species) return slots;
    const code = raceSpecService.speciesCodeOf(species.code, rules);
    if (!code || code === species.code) return slots;

    return { ...slots, species: { ...species, code } };
  }
}
