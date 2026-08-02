class ServiceLocator {
  private map = new Map<string, unknown>()

  set<T>(key: string, value: T): void {
    this.map.set(key, value)
  }

  get<T>(key: string): T {
    const v = this.map.get(key)
    if (!v) {
      throw new Error(`Service "${key}" not registered`)
    }
    return v as T
  }

  reset(): void {
    this.map.clear()
  }
}

export const serviceLocator = new ServiceLocator()
