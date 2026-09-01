/**
 * Календарное время для относительного и часового отображения.
 */
export class DateTime {
  private readonly date: Date;

  /**
   * Разбирает ISO-строку в значение времени.
   *
   * @param iso Момент времени в ISO-8601.
   */
  constructor(iso: string) {
    this.date = new Date(iso);
  }

  /**
   * Форматирует давность относительно текущего момента.
   */
  formatRelative(): string {
    const diff = Date.now() - this.date.getTime();
    const early = this.relativeHours(diff);
    if (early !== null) return early;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} ч`;
    if (diff < 172800000) return 'вчера';

    return `${Math.floor(diff / 86400000)} д`;
  }

  /**
   * Форматирует время суток или короткую давность.
   */
  formatTime(): string {
    const diff = Date.now() - this.date.getTime();
    const early = this.relativeHours(diff);
    if (early !== null) return early;
    const hours = this.date.getHours().toString().padStart(2, '0');
    const minutes = this.date.getMinutes().toString().padStart(2, '0');

    return `${hours}:${minutes}`;
  }

  /**
   * Возвращает подпись для интервала меньше часа.
   *
   * @param diff Разница в миллисекундах.
   */
  private relativeHours(diff: number): string | null {
    if (diff < 60000) return 'только что';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} мин`;

    return null;
  }

  /**
   * Форматирует давность по ISO-строке.
   *
   * @param iso Момент времени в ISO-8601.
   */
  static formatRelative(iso: string): string {
    return new DateTime(iso).formatRelative();
  }

  /**
   * Форматирует время суток по ISO-строке.
   *
   * @param iso Момент времени в ISO-8601.
   */
  static formatTime(iso: string): string {
    return new DateTime(iso).formatTime();
  }
}
