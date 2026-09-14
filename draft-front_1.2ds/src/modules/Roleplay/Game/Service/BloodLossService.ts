import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import { BLOOD_LOSS_STATE_CODE, EXHAUSTION_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { BLOOD_CLOTTING_RULE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/BLOOD_CLOTTING_RULE_CODE';
import { CHECK_BLOOD_CLOTTING_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';
import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';
import { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
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
    let version = args.version;
    const oldBlood = injuryCheckService.overlayStateTotal(version, args.rules, BLOOD_LOSS_STATE_CODE);
    const oldExh = injuryCheckService.overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE);
    const next = applyBloodLossGain(oldBlood, args.delta, oldExh);
    let overlay = await setNumericState(
      this.resolveGameApi(),
      args.gameId,
      args.targetKey,
      version,
      args.rules,
      BLOOD_LOSS_STATE_CODE,
      next.bloodLoss,
    );
    if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);
    if (args.chatId !== null) {
      const sent = await args.sendMessage(
        formatBloodLossTickMessage(args.targetName, args.delta, next.bloodLoss, args.targetKey),
        [],
        args.chatId,
        args.speaker,
      );
      if (!sent) throw new Error('Не удалось отправить сообщение о кровопотере');
    }
    if (next.exhaustion !== oldExh) {
      overlay =
        (await setNumericState(
          this.resolveGameApi(),
          args.gameId,
          args.targetKey,
          version,
          args.rules,
          EXHAUSTION_STATE_CODE,
          next.exhaustion,
        )) ?? overlay;
      if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);
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
        version = combatOverlayService.mergeCombatOverlay(version, checked.overlay);
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
    if (args.rules.some((rule) => rule.code === BLOOD_CLOTTING_RULE_CODE)) {
      const clotted = await this.applyBloodClotting(args, version);
      if (clotted.overlay) overlay = clotted.overlay;
      version = clotted.version;
    }
    const delta = woundInstanceService.bleedTotal(version.states);
    const blood = await this.applyBloodLossTick({ ...args, version, overlay, delta });
    if (blood) {
      overlay = blood;
      version = combatOverlayService.mergeCombatOverlay(version, blood);
    }
    const dots = await endOfTurnDotsService.applyEndOfTurnDots({ ...args, version });

    return dots ?? overlay;
  }

  private async applyBloodClotting(
    args: Omit<ApplyBloodLossArgs, 'delta'>,
    version: CharacterVersion,
  ): Promise<{ version: CharacterVersion; overlay: GameCombatOverlay | null }> {
    let overlay: GameCombatOverlay | null = null;
    const defaults = rollPoolDefaults(args.rules);
    const checkName = args.rules.find((rule) => rule.code === CHECK_BLOOD_CLOTTING_CODE)?.name ?? 'Свёртывание крови';
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
        CHECK_BLOOD_CLOTTING_CODE,
        SIMPLE_CHECK_ZERO_DIFFICULTY,
        args.rng ?? Math.random,
        args.rules,
        args.mechanics,
      );
      const rating = rolled.check?.passed ? (rolled.check.rating ?? 0) : 0;
      const next = woundInstanceService.applyClotting(migrated, rating);
      overlay = await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, next);
      version = combatOverlayService.mergeCombatOverlay(version, overlay);
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
