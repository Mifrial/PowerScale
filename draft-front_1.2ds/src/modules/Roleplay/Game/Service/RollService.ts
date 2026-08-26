import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec';
import type { RollForm } from '@/modules/Roleplay/Game/Dto/RollForm';
import type { ParsedCommand } from '@/modules/Messages/Chat/Dto/ParsedCommand';
import type { ParsedRollFormula } from '@/modules/Roleplay/Game/Dto/ParsedRollFormula';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { resolveAppliedMechanicNames } from '@/modules/Roleplay/Game/Utils/appliedRollMechanics';
import { ROLL_ADV_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ADV_MAX';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';
import { ROLL_DICE_COUNT_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MAX';
import { ROLL_DICE_COUNT_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DICE_COUNT_MIN';
import { ROLL_DIE_FACES_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DIE_FACES_MAX';
import { ROLL_DIE_FACES_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DIE_FACES_MIN';
import { ROLL_DIE_SIZE_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_DIE_SIZE_MAX';
import { ROLL_EFFICIENCY_MAX } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MAX';
import { ROLL_EFFICIENCY_MIN } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_EFFICIENCY_MIN';

export class RollService {
  private static readonly SUPERSCRIPTS: Record<number, string> = {
    2: '²',
    3: '³',
    4: '⁴',
    5: '⁵',
    6: '⁶',
    7: '⁷',
    8: '⁸',
    9: '⁹',
    10: '¹⁰',
  };

  formatRollSize(size: number): string {
    if (!size) return '';
    const arrow = size > 0 ? '↑' : '↓';
    const mag = Math.abs(size);

    return arrow + (mag >= 2 ? (RollService.SUPERSCRIPTS[mag] ?? String(mag)) : '');
  }

  /** Пул как 4к6 / 5↓к6 — размер мастерства на кубах, не смешанный с эффективностью. */
  formatPoolNotation(spec: Pick<DiceRollSpec, 'diceCount' | 'dieFaces' | 'dieSize' | 'poolSize'>): string {
    const size = spec.poolSize ?? 0;

    return `${spec.diceCount}${this.formatRollSize(size)}к${spec.dieFaces}`;
  }

  formatEfficiencyLabel(spec: Pick<DiceRollSpec, 'efficiency' | 'efficiencySize'>): string {
    return new DimensionalNumber({ base: spec.efficiency, size: spec.efficiencySize ?? 0 }).toString();
  }

  validateRollSpec(roll: RollForm): boolean {
    const diceCount = Number(roll.diceCount);
    const dieFaces = Number(roll.dieFaces);
    const efficiency = Number(roll.efficiency);
    const adv = Number(roll.adv);
    const dieSize = Number(roll.dieSize);
    if (!Number.isInteger(diceCount) || diceCount < ROLL_DICE_COUNT_MIN || diceCount > ROLL_DICE_COUNT_MAX)
      return false;
    if (!Number.isInteger(dieFaces) || dieFaces < ROLL_DIE_FACES_MIN || dieFaces > ROLL_DIE_FACES_MAX) return false;
    if (!Number.isInteger(efficiency) || efficiency < ROLL_EFFICIENCY_MIN || efficiency > ROLL_EFFICIENCY_MAX)
      return false;
    if (!Number.isInteger(adv) || Math.abs(adv) > ROLL_ADV_MAX) return false;
    if (!Number.isInteger(dieSize) || Math.abs(dieSize) > ROLL_DIE_SIZE_MAX) return false;

    return true;
  }

  formatRollSpecText(spec: MacroRollSpec): string {
    const adv = spec.adv || 0;
    const advPart = adv ? (adv > 0 ? ` +${adv}` : ` ${adv}`) : '';
    const size = this.formatRollSize(spec.dieSize || 0);
    const label = spec.rollLabel?.trim();

    return `${spec.rollFormula}${advPart}${size ? ` ${size}` : ''} · сл:${spec.efficiency}${label ? ` (${label})` : ''}${spec.variableAdvantages ? ' · преим. ?' : ''}`;
  }

  formatRollFormText(form: RollForm): string {
    return this.formatRollSpecText({
      rollFormula: `${form.diceCount}d${form.dieFaces}`,
      efficiency: Number(form.efficiency),
      adv: Number(form.adv),
      dieSize: Number(form.dieSize),
      rollLabel: form.rollLabel,
      variableAdvantages: form.variableAdvantages,
    });
  }

  parseRollFormula(formula: string): ParsedRollFormula | null {
    const match = /^\s*(\d{1,2})\s*[dDкК]\s*(\d{1,3})\s*$/.exec(formula);
    if (!match) return null;
    const diceCount = Number(match[1]);
    const dieFaces = Number(match[2]);
    if (diceCount < ROLL_DICE_COUNT_MIN || diceCount > ROLL_DICE_COUNT_MAX) return null;
    if (dieFaces < ROLL_DIE_FACES_MIN || dieFaces > ROLL_DIE_FACES_MAX) return null;

    return { diceCount, dieFaces };
  }

  parseRollCommand(text: string): ParsedCommand | null {
    const trimmed = text.trim();
    const headMatch = /^\/(?:roll|бросок)\b(.*)$/is.exec(trimmed);
    if (!headMatch) return null;

    const rest = headMatch[1];
    const formulaMatch = /^(\d{1,2}[dDкК]\d{1,3})(.*)$/s.exec(rest.trim());
    if (!formulaMatch) return null;

    const formula = this.parseRollFormula(formulaMatch[1]);
    if (!formula) return null;

    const parts = formulaMatch[2].split(/\s+/).filter(Boolean);
    let efficiency = 3;
    let adv = 0;
    let dieSize = 0;
    const labelParts: string[] = [];

    for (const part of parts) {
      if (/^e:\d+$/i.test(part)) {
        const value = Number(part.slice(2));
        efficiency = value >= ROLL_EFFICIENCY_MIN && value <= ROLL_EFFICIENCY_MAX ? value : efficiency;
      } else if (/^(?:adv|prem):[-+]?\d+$/i.test(part)) {
        const value = Number(part.split(':')[1]);
        adv = Math.max(-ROLL_ADV_MAX, Math.min(ROLL_ADV_MAX, value));
      } else if (/^(?:dis|pom):[-+]?\d+$/i.test(part)) {
        const value = Number(part.split(':')[1]);
        adv = Math.max(-ROLL_ADV_MAX, Math.min(ROLL_ADV_MAX, -value));
      } else if (/^(?:size|razm|dim):[-+]?\d+$/i.test(part)) {
        dieSize = Math.max(-ROLL_DIE_SIZE_MAX, Math.min(ROLL_DIE_SIZE_MAX, Number(part.split(':')[1])));
      } else if (/^label:/i.test(part)) {
        labelParts.push(part.slice(6));
      } else {
        labelParts.push(part);
      }
    }

    return {
      content: trimmed,
      attachments: [
        {
          type: ROLL_ATTACHMENT_TYPE,
          payload: {
            diceCount: formula.diceCount,
            dieFaces: formula.dieFaces,
            efficiency,
            advantages: aggregateSourceDeltasService.advantageEntries(adv),
            dieSize,
            label: labelParts.join(' ').trim() || undefined,
          },
        },
      ],
    };
  }

  /**
   * Результат броска базовым движком (без механик ревизии — фолбэк для мессенджера/макросов):
   * преимущества (лишние кубы + выбросить худшие/лучшие), «6 и 1» и базовый подсчёт.
   * В игровом чате/инициативе бросок идёт через RollEngine (механики ревизии).
   */
  computeRollResult(spec: DiceRollSpec, rng: DiceRng = Math.random): DiceRollResult {
    const diceCount = Math.max(1, spec.diceCount);
    const faces = Math.max(2, spec.dieFaces);
    const adv = aggregateSourceDeltasService.netSourceDelta(spec.advantages);
    const rollDie = () => Math.floor(rng() * faces) + 1;

    const rolls = Array.from({ length: diceCount }, rollDie);
    const adjusted = adv !== 0 ? [...rolls, ...Array.from({ length: Math.abs(adv) }, rollDie)] : [...rolls];

    let droppedRolls: number[] = [];
    if (adv > 0) {
      adjusted.sort((a, b) => b - a);
      droppedRolls = adjusted.splice(0, adv);
    } else if (adv < 0) {
      adjusted.sort((a, b) => a - b);
      droppedRolls = adjusted.splice(0, Math.abs(adv));
    }

    const successes = adjusted.map((v) => {
      if (v === 1) return 2;
      if (v <= spec.efficiency) return 1;
      if (v < faces) return 0;

      return -1;
    });
    const totalSuccesses: number = successes.reduce<number>((sum, s) => sum + s, 0);
    const appliedMechanics = resolveAppliedMechanicNames({
      spec,
      adjustedRolls: adjusted,
      successes,
      droppedRolls,
    });

    return {
      spec,
      rolls,
      successes,
      adjustedRolls: adjusted,
      droppedRolls,
      totalSuccesses,
      appliedMechanics: appliedMechanics.length > 0 ? appliedMechanics : undefined,
    };
  }

  isDiceRollResult(payload: DiceRollSpec | DiceRollResult): payload is DiceRollResult {
    return 'rolls' in payload;
  }
}
