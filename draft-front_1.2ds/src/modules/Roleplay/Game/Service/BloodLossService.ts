import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';
import { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { formatBloodLossTickMessage } from '@/modules/Roleplay/Game/Utils/bloodLossMessage';
import { formatBloodClottingMessage } from '@/modules/Roleplay/Game/Utils/bloodClottingMessage';
import { rollPoolDefaults } from '@/modules/Roleplay/Game/Utils/initiativeRoll';
import { SIMPLE_CHECK_ZERO_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Check/SIMPLE_CHECK_ZERO_DIFFICULTY';
import { setNumericState } from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import { endOfTurnDotsService } from '@/modules/Roleplay/Game/Service/Instance/endOfTurnDotsService';
import { applyBloodLossGain, bloodLossInjuryDifficulty } from '@/modules/Roleplay/Game/Utils/bloodLossMath';
import type { ApplyBloodLossArgs } from '@/modules/Roleplay/Game/Dto/ApplyBloodLossArgs';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

export class BloodLossService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  async applyBloodLossTick(args: ApplyBloodLossArgs): Promise<GameCombatOverlay | null> {
    if (args.delta <= 0) return null;
    const version = args.version;
    const bloodCode = attackDamageService.bloodLossRule(args.rules)?.code ?? '';
    const exhaustionCode = attackDamageService.exhaustionRule(args.rules)?.code ?? '';
    const oldBlood = injuryCheckService.overlayStateTotal(version, args.rules, bloodCode);
    const oldExh = injuryCheckService.overlayStateTotal(version, args.rules, exhaustionCode);
    const next = applyBloodLossGain(oldBlood, args.delta, oldExh);
    let overlay = args.overlay ?? null;
    if (bloodCode) {
      await setNumericState(
        this.resolveGameApi(),
        args.gameId,
        args.targetKey,
        version,
        args.rules,
        bloodCode,
        next.bloodLoss,
      );
    }
    if (args.chatId !== null) {
      const sent = await args.sendMessage(
        formatBloodLossTickMessage(args.targetName, args.delta, next.bloodLoss, args.targetKey),
        [],
        args.chatId,
        args.speaker,
      );
      if (!sent) throw new Error('Не удалось отправить сообщение о кровопотере');
    }
    if (exhaustionCode && next.exhaustion !== oldExh) {
      await setNumericState(
        this.resolveGameApi(),
        args.gameId,
        args.targetKey,
        version,
        args.rules,
        exhaustionCode,
        next.exhaustion,
      );
    }
    if (next.addedExhaustion > 0) {
      const checked = await exhaustionCheckService.applyExhaustionCheck({
        version,
        rng: args.rng,
        rules: args.rules,
        mechanics: args.mechanics,
        gameId: args.gameId,
        targetKey: args.targetKey,
        targetName: args.targetName,
        chatId: args.chatId,
        speaker: args.speaker,
        change: 'increase',
        sendMessage: args.sendMessage,
        askTokenSpend: args.askTokenSpend,
        overlay: args.overlay ?? overlay,
      });
      if (checked.overlay) {
        overlay = checked.overlay;
      }
    }
    const bloodDc = bloodLossInjuryDifficulty(next.reserved);
    const procedure = resolveInjuryProcedure(args.rules, args.mechanics);
    const normalDc = injuryRollService.injuryDifficulty(
      {
        leftoverDamage: 0,
        woundStrength: 0,
        endurance: Math.max(1, args.endurance),
        exhaustion: next.exhaustion,
        attackSr: 0,
      },
      procedure,
      0,
    );
    const useBlood = bloodDc != null;
    const difficulty = useBlood ? bloodDc : normalDc;
    if (difficulty > 0 && (useBlood || next.addedExhaustion > 0)) {
      const applied = await injuryCheckService.applyInjuryCheck({
        input: {
          leftoverDamage: 0,
          woundStrength: 0,
          difficulty,
          endurance: Math.max(1, args.endurance),
          exhaustion: next.exhaustion,
          attackSr: 0,
          actorKey: args.targetKey,
          label: useBlood ? 'Проверка на увечье (кровопотеря)' : undefined,
        },
        rng: args.rng,
        rules: args.rules,
        mechanics: args.mechanics,
        gameId: args.gameId,
        targetKey: args.targetKey,
        targetName: args.targetName,
        chatId: args.chatId,
        speaker: args.speaker,
        skipIfNoRoll: !useBlood,
        chatPrefix: useBlood ? 'Увечье от кровопотери.' : undefined,
        targetVersion: version,
        sendMessage: args.sendMessage,
      });
      if (applied.overlay) overlay = applied.overlay;
    }

    return overlay;
  }

  async applyTurnWoundBleed(args: Omit<ApplyBloodLossArgs, 'delta'>): Promise<GameCombatOverlay | null> {
    let version = args.version;
    let overlay: GameCombatOverlay | null = args.overlay ?? null;
    const checkCode = this.bloodClottingCheckCode(args.rules);
    if (checkCode) {
      const clotted = await this.applyBloodClotting(args, version, checkCode);
      if (clotted.overlay) overlay = clotted.overlay;
      version = clotted.version;
    }
    const delta = woundInstanceService.bleedTotal(version.states);
    const blood = await this.applyBloodLossTick({ ...args, version, overlay, delta });
    if (blood) {
      overlay = blood;
    }
    const dots = await endOfTurnDotsService.applyEndOfTurnDots({ ...args, version });

    return dots ?? overlay;
  }

  private bloodClottingCheckCode(rules: Rule[]): string | null {
    for (const rule of rules) {
      for (const row of rule.mechanics) {
        const payload = row.mechanicPayload;
        if (payload?.type === 'blood_clotting' && payload.check_code) return payload.check_code;
      }
    }

    return null;
  }

  private async applyBloodClotting(
    args: Omit<ApplyBloodLossArgs, 'delta'>,
    version: CharacterVersion,
    checkCode: string,
  ): Promise<{ version: CharacterVersion; overlay: GameCombatOverlay | null }> {
    const overlay: GameCombatOverlay | null = null;
    const defaults = rollPoolDefaults(args.rules);
    const checkName = args.rules.find((rule) => rule.code === checkCode)?.name ?? 'Свёртывание крови';
    for (let index = 0; index < version.states.length; index += 1) {
      const state = version.states[index];
      if (!state || !woundInstanceService.isWound(state)) continue;
      const migrated: CharacterStateValue = woundInstanceService.migrate(state);
      const rolled = checkRollService.rollNamedCheck(
        {
          diceCount: 1,
          dieFaces: defaults.dieFaces,
          efficiency: defaults.efficiency,
          advantages: [],
          dieSize: 0,
          poolSize: 0,
          efficiencySize: 0,
          label: checkName,
          actorKey: args.targetKey,
        },
        checkCode,
        SIMPLE_CHECK_ZERO_DIFFICULTY,
        args.rng ?? Math.random,
        args.rules,
        args.mechanics,
      );
      const rating = rolled.check?.passed ? (rolled.check.rating ?? 0) : 0;
      const next = woundInstanceService.applyClotting(migrated, rating);
      await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, next);
      if (args.chatId !== null) {
        const sent = await args.sendMessage(
          formatBloodClottingMessage(
            args.targetName,
            args.targetKey,
            woundInstanceService.strength(next),
            rating,
            rolled.check?.passed === true,
          ),
          [{ type: ROLL_ATTACHMENT_TYPE, payload: rolled }],
          args.chatId,
          args.speaker,
        );
        if (!sent) throw new Error('Не удалось отправить сообщение о свёртывании');
      }
    }

    return { version, overlay };
  }
}
