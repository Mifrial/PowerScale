/** Один бросок в макросе (ТР §3 user_macro_rolls). */
export interface MacroRollSpec {
  rollFormula: string
  efficiency: number
  /** Преимущества (>0) / помехи (<0), как в DiceRollForm. */
  adv: number
  /** Размерность успехов — суффиксом в выводе («4↑ успехов»). */
  dieSize: number
  /** Подпись броска — метка карточки (мульти-бросочные сообщения: «1 удар», «уклонение»). */
  rollLabel?: string
  /** При отправке спрашивать число преимуществ в диалоге (применяется к этому броску). */
  variableAdvantages: boolean
}
