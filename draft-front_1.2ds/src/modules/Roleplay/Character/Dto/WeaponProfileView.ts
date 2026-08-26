/** Профиль атаки оружия/щита в display-форме (панель предмета редактора). */
export interface WeaponProfileView {
  /** «Удар» / «Бросок» / «Выстрел». */
  profileTypeLabel: string;
  /** «6↓ рубящего» (значение от текущих характеристик + короткая форма типа урона). */
  damageLabel: string;
  /**
   * «Сила − 4» — человекочитаемая формула урона; null для фикс. значения («число N» не показываем —
   * значение и так в damageLabel).
   */
  damageFormula: string | null;
  /** «3↑ пробития». */
  penetrationLabel: string;
  penetrationFormula: string | null;
  accuracyLabel: string;
  /** «1↓» либо «дистанция/дальнобойность» («2/4»), как в овевью. */
  distanceLabel: string;
  /** «Дальнобойность» профиля (шаг падения силы броска/выстрела); null — отсутствует. */
  falloffLabel: string | null;
}
