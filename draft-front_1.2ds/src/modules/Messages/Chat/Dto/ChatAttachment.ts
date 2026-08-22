export interface ChatAttachment<TPayload = unknown> {
  type: string;
  payload: TPayload;
}
