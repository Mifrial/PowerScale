import type { RollMechanicPayload } from '@/modules/Roleplay/Rule/Dto/RollMechanicPayload';
import type { RollScoreAdjustPayload } from '@/modules/Roleplay/Rule/Dto/RollScoreAdjustPayload';

/**
 * Контекст инстанции механики на правиле (rule.mechanic_payload).
 * Разные хендлеры несут свой payload; дискриминатор — поле type.
 */
export type MechanicPayload =
  | {
      type: 'purchase_surcharge';
      /** Фильтр способностей, к которым применяется доплата (по признаку и/или расе). */
      filter: { keyword_code?: string; race_code?: string };
      /** Сколько первых способностей бесплатно. */
      free_count: number;
      /** Размер доплаты за каждую последующую (в ОС). */
      surcharge: number;
    }
  | {
      type: 'roll';
      /** Дефолтные параметры броска игры (правило «Бросок» ревизии, ТР §8 «Чат игры»). */
      data: RollMechanicPayload;
    }
  | {
      type: 'roll_score_adjust';
      /** Дельты успехов по значению кубика (механики, влияющие на подсчёт броска). */
      data: RollScoreAdjustPayload;
    }
  | {
      type: 'injury_efficiency';
      /** Дельта < 0 → помехи на проверку увечья (рубящий: −1). */
      delta: number;
    }
  | {
      type: 'exhaustion_wound';
      /** Множитель силы раны от истощения (1 колющий, 2 рубящий). */
      multiplier: number;
    };
