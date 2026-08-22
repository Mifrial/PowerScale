export type PeriodStep = 'turn' | 'minute' | 'hour' | 'day' | 'month' | 'year';

/**
 * Периодичность эффекта урона со временем — собственный период (значение + шаг).
 * Параметры яда живут в правиле (литералы), ссылок на поля экземпляра нет.
 */
export type StatePeriodicity = { kind: 'literal'; value: number; step: PeriodStep };
