export class DateTime {
  private readonly date: Date

  constructor(iso: string) {
    this.date = new Date(iso)
  }

  formatRelative(): string {
    const diff = Date.now() - this.date.getTime()
    if (diff < 60000) return 'только что'
    if (diff < 3600000) return `${Math.floor(diff / 60000)} мин`
    if (diff < 86400000) return `${Math.floor(diff / 3600000)} ч`
    if (diff < 172800000) return 'вчера'
    return `${Math.floor(diff / 86400000)} д`
  }

  formatTime(): string {
    const diff = Date.now() - this.date.getTime()
    if (diff < 60000) return 'только что'
    if (diff < 3600000) return `${Math.floor(diff / 60000)} мин`
    const h = this.date.getHours().toString().padStart(2, '0')
    const m = this.date.getMinutes().toString().padStart(2, '0')
    return `${h}:${m}`
  }

  static formatRelative(iso: string): string {
    return new DateTime(iso).formatRelative()
  }

  static formatTime(iso: string): string {
    return new DateTime(iso).formatTime()
  }
}
