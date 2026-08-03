export interface NotificationButton {
  label: string;
  actionType: 'event' | 'url' | 'action';
  action: string;
  payload?: Record<string, unknown>;
}
