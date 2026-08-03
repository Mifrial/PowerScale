export class DateTime {
  private readonly date: Date;

  constructor(iso: string) {
    this.date = new Date(iso);
  }

  formatRelative(): string {
    const diff = Date.now() - this.date.getTime();
    const early = this.relativeHours(diff);
    if (early !== null) return early;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} ч`;
    if (diff < 172800000) return 'вчера';

    return `${Math.floor(diff / 86400000)} д`;
  }

  formatTime(): string {
    const diff = Date.now() - this.date.getTime();
    const early = this.relativeHours(diff);
    if (early !== null) return early;
    const hours = this.date.getHours().toString().padStart(2, '0');
    const minutes = this.date.getMinutes().toString().padStart(2, '0');

    return `${hours}:${minutes}`;
  }

  private relativeHours(diff: number): string | null {
    if (diff < 60000) return 'только что';
    if (diff < 3600000) return `${Math.floor(diff / 60000)} мин`;

    return null;
  }

  static formatRelative(iso: string): string {
    return new DateTime(iso).formatRelative();
  }

  static formatTime(iso: string): string {
    return new DateTime(iso).formatTime();
  }
}
