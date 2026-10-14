/**
 * Режим фронта на PHP Game API (VITE_API_MODE=real).
 */
export class GamePhpMode {
  isEnabled(): boolean {
    return import.meta.env.VITE_API_MODE === 'real';
  }
}
