export type ContactStatus = 'PENDING' | 'SENDING' | 'SUCCESS' | 'FAILED_INVALID' | 'FAILED_TIMEOUT' | 'SKIPPED';

export interface ContactItem {
  id: string;
  phone: string;
  name: string;
  status: ContactStatus;
  sentAt?: string;
  errorMessage?: string;
}

export type QueueRunStatus = 'IDLE' | 'RUNNING' | 'PAUSED' | 'COMPLETED';

export interface SenderSettings {
  minDelaySec: number;
  maxDelaySec: number;
  minTypingSpeedMs: number;
  maxTypingSpeedMs: number;
}

export interface AttachmentItem {
  name: string;
  type: string;
  dataUrl: string;
  size?: number;
}

export interface QueueState {
  runStatus: QueueRunStatus;
  template: string;
  appendRandomCode?: boolean;
  attachments?: AttachmentItem[];
  contacts: ContactItem[];
  currentIndex: number;
  settings: SenderSettings;
  activeTabId?: number;
  isCooldown?: boolean;
  cooldownRemainingSec?: number;
  batchSize?: number;
  batchCooldownSec?: number;
}

export interface QuotaData {
  messages_sent: number;
  max_daily_messages: number;
  is_unlimited_messages: boolean;
  remaining_messages: number | string;
  files_attached: number;
  max_daily_attachments: number;
  is_unlimited_attachments: boolean;
  remaining_attachments: number | string;
}

export interface UserProfile {
  id: number;
  name: string;
  email: string;
  role: string;
  phone?: string | null;
  referral_code?: string;
}

export interface PlanData {
  name: string;
  slug: string;
  is_free: boolean;
  duration_days: number;
  features?: {
    max_daily_messages: number;
    max_daily_attachments: number;
    max_contacts: number;
    max_templates: number;
    can_import_csv: boolean;
    custom_delay: boolean;
    priority_support: boolean;
  };
  ends_at?: string | null;
}

export interface AuthSessionData {
  token: string | null;
  user: UserProfile;
  plan: PlanData;
  quota: QuotaData;
}

export type IPCMessage =
  | { type: 'START_QUEUE'; payload: { contacts: ContactItem[]; template: string; settings: SenderSettings; appendRandomCode?: boolean; attachments?: AttachmentItem[] } }
  | { type: 'PAUSE_QUEUE' }
  | { type: 'RESUME_QUEUE' }
  | { type: 'STOP_QUEUE' }
  | { type: 'GET_QUEUE_STATE' }
  | { type: 'NAVIGATE_AND_SEND'; payload: { contact: ContactItem; template: string; settings: SenderSettings; appendRandomCode?: boolean; attachments?: AttachmentItem[] } }
  | { type: 'CONTENT_READY_TO_TYPE'; payload: { phone: string; text: string; minSpeed: number; maxSpeed: number } }
  | { type: 'CONTENT_RESULT'; payload: { phone: string; status: ContactStatus; errorMessage?: string; hasAttachment?: boolean } }
  | { type: 'TOGGLE_SIDEBAR' }
  | { type: 'OPEN_SIDEBAR' }
  | { type: 'AUTH_CHANGED' }
  | { type: 'API_LOGIN'; payload: { email: string; password: string } }
  | { type: 'API_LOGOUT' }
  | { type: 'API_HEARTBEAT' }
  | { type: 'API_VALIDATE_BATCH'; payload: { count: number; filesCount?: number } }
  | { type: 'API_RECORD_SENT'; payload: { hasAttachment?: boolean } }
  | { type: 'API_GET_CONTACTS' }
  | { type: 'API_SAVE_CONTACTS'; payload: { contacts: Array<{ name?: string; phone: string }>; groupName: string } }
  | { type: 'API_GET_TEMPLATES' }
  | { type: 'OPEN_TAB'; payload: { url: string } }
  | { type: 'SYNC_WEB_AUTH'; payload: { authenticated: boolean; data?: AuthSessionData; origin?: string } }
  | { type: 'CHECK_WEB_SESSION'; payload?: { forceTakeover?: boolean } };
