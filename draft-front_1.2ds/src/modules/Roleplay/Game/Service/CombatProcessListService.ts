import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CombatProcessRow } from '@/modules/Roleplay/Game/Dto/CombatProcessRow';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import { electrochargeService } from '@/modules/Roleplay/Game/Service/Instance/electrochargeService';
import { processSessionService } from '@/modules/Roleplay/Game/Service/Instance/processSessionService';
import { spellCastOptionsService } from '@/modules/Roleplay/Game/Service/Instance/spellCastOptionsService';
import { asProcessAbilitySpec, findRuleByRef } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Собирает видимые обязательства персонажа: процесс атаки и поддерживаемые заклинания. */
export class CombatProcessListService {
  listRows(input: {
    entityKey: CombatEntityKey;
    processSession: ProcessSession | null;
    committedAction: CommittedActionSession | null;
    activeSpells: ActiveSpell[];
    version: CharacterVersion | null;
    states: CharacterStateValue[];
    rules: Rule[];
  }): CombatProcessRow[] {
    const rows: CombatProcessRow[] = [];
    if (input.processSession) {
      rows.push(this.processRow(input.processSession, input.rules));
    }
    if (input.committedAction) {
      rows.push(this.committedRow(input.committedAction, input.rules));
    }
    for (const spell of input.activeSpells) {
      if (spell.casterKey !== input.entityKey) {
        continue;
      }
      rows.push(this.spellRow(spell, input.version, input.states, input.rules));
    }

    return rows;
  }

  private processRow(session: ProcessSession, rules: Rule[]): CombatProcessRow {
    const rule = findRuleByRef(rules, session.processRuleCode);
    const spec = rule ? asProcessAbilitySpec(rule) : null;
    const step = spec?.steps.find((entry) => entry.code === session.currentStepCode);
    const name = rule?.name ?? session.processRuleCode;
    const stepLabel = step?.name ?? session.currentStepCode;
    const canAbort = spec ? processSessionService.canInterruptNormally(spec, session.currentStepCode) : false;

    return {
      id: `process:${session.entityKey}`,
      kind: 'process',
      name,
      leftLabel: name,
      valueLabel: stepLabel,
      iconCode: 'mdi-timeline-clock-outline',
      details: [
        { label: 'Шаг', value: stepLabel },
        { label: 'Статус шага', value: session.currentStepStatus === 'pending' ? 'ожидает' : 'выполнен' },
        ...(session.comboCount ? [{ label: 'Комбо', value: String(session.comboCount) }] : []),
      ],
      canAbort,
      abortLabel: 'Оборвать',
      canChargeCast: false,
      chargeCastLabel: 'Сотворить',
    };
  }

  private committedRow(session: CommittedActionSession, rules: Rule[]): CombatProcessRow {
    const rule = findRuleByRef(rules, session.actionRuleCode);
    const name = rule?.name ?? session.actionRuleCode;

    return {
      id: `committed:${session.entityKey}`,
      kind: 'committed-action',
      name,
      leftLabel: name,
      valueLabel: `ещё ${session.remainingOd} ОД`,
      iconCode: 'mdi-progress-clock',
      details: [
        { label: 'Всего', value: `${session.totalOd} ОД` },
        { label: 'Осталось', value: `${session.remainingOd} ОД` },
      ],
      canAbort: true,
      abortLabel: 'Сорвать',
      canChargeCast: false,
      chargeCastLabel: 'Сотворить',
    };
  }

  private spellRow(
    spell: ActiveSpell,
    version: CharacterVersion | null,
    states: CharacterStateValue[],
    rules: Rule[],
  ): CombatProcessRow {
    const rule = findRuleByRef(rules, spell.spellCode);
    const name = rule?.name ?? spell.spellCode;
    const sources = spellCastOptionsService.listSources(version, rules);
    const sourceName = sources.find((source) => source.key === spell.sourceKey)?.name ?? spell.sourceKey;
    const pathName = spell.pathCode ? (findRuleByRef(rules, spell.pathCode)?.name ?? spell.pathCode) : '—';
    const power = DimensionalNumber.from(spell.sustainPower).toNumber().toString();
    const charge = electrochargeService.chargeSpec(spell.spellCode, rules);
    const bound = charge
      ? states.find((state) => state.stateRuleCode === charge.state_code && state.boundSustainId === spell.id)
      : undefined;
    const charges = bound?.value ?? 0;
    const canChargeCast = Boolean(charge && bound && electrochargeService.canOpenCast(bound, [spell], rules));

    return {
      id: spell.id,
      kind: 'sustained-spell',
      name,
      leftLabel: name,
      valueLabel: charge ? `${power} · ${charges}` : power,
      iconCode: 'mdi-creation',
      details: [
        { label: 'Мощь поддержания', value: power },
        { label: 'Источник', value: sourceName },
        { label: 'Путь', value: pathName },
        { label: 'Заклинание', value: name },
        ...(charge ? [{ label: 'Электрозаряд', value: String(charges) }] : []),
      ],
      canAbort: true,
      abortLabel: 'Оборвать',
      canChargeCast,
      chargeCastLabel: 'Сотворить',
    };
  }
}
