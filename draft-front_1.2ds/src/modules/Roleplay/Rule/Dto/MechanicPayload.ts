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
    };

/**
 * Дефолты броска (структурные, без зависимости Rule → Game): заполняются из правила
 * «Бросок» ревизии, если поле броска не задано пользователем. Поля опциональны —
 * механику «Бросок» в полном виде реализует отдельный заход (Вариант Б).
 */
export interface RollMechanicPayload {
  diceCount?: number;
  dieFaces?: number;
  efficiency?: number;
  adv?: number;
  dieSize?: number;
  /** Коды механик, которые механика броска применяет всегда (если их правила есть в ревизии). */
  sub_mechanics?: string[];
}

/**
 * Дельты успехов механики «подсчёта броска»: «1» начисляет `oneDelta` доп. успехов,
 * грань куба списывает `faceDelta`. По умолчанию +1 / −1 (как правило «6 и 1»).
 */
export interface RollScoreAdjustPayload {
  oneDelta?: number;
  faceDelta?: number;
}
