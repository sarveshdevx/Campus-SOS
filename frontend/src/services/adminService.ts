import { ApiClient } from './api';
import { IncidentAttachment } from '../types/incident';

export interface AdminMetrics {
  activeSosCount: number;
  openIncidentsCount: number;
  totalRespondersCount: number;
  resolvedTodayCount: number;
  unassignedCount: number;
  byCategory: Record<string, number>;
  byStatus: Record<string, number>;
}

export interface CandidateMatch {
  id: number;
  record_a_type?: 'incident' | 'sos';
  record_b_type?: 'incident' | 'sos';
  incident_a_id: number;
  incident_b_id: number;
  similarity_score: number;
  rationale: string;
  match_breakdown?: {
    category: number;
    location: number;
    temporal: number;
    lexical: number;
  };
  status: string;
  created_at: string;
  a_number: string;
  a_title: string;
  a_category: string;
  a_location: string;
  a_room?: string | null;
  a_description?: string;
  a_occurred_at?: string;
  a_status?: string;
  b_number: string;
  b_title: string;
  b_category: string;
  b_location: string;
  b_room?: string | null;
  b_description?: string;
  b_occurred_at?: string;
  b_status?: string;
  a_attachments?: IncidentAttachment[];
  b_attachments?: IncidentAttachment[];
}

export interface AuditLogEntry {
  id: number;
  actor_user_id?: number;
  action: string;
  resource_type: string;
  resource_id?: number;
  ip_address: string;
  details?: Record<string, unknown>;
  created_at: string;
  actor_name?: string;
  actor_role?: string;
}

export interface ResponderUser {
  id: number;
  user_id: number;
  category: string;
  department: string;
  status: 'available' | 'busy' | 'offline';
  current_location?: string;
  contact_phone: string;
  shift_notes?: string;
  full_name: string;
  email: string;
}

export interface NotificationItem {
  id: number;
  notification_type: string;
  message: string;
  is_read: boolean;
  link_url?: string;
  created_at: string;
}

export const adminService = {
  async getMetrics(): Promise<AdminMetrics> {
    return ApiClient.get<AdminMetrics>('/admin/metrics');
  },

  async getAuditLogs(): Promise<AuditLogEntry[]> {
    return ApiClient.get<AuditLogEntry[]>('/admin/audit-logs');
  },

  async getCandidateMatches(): Promise<CandidateMatch[]> {
    return ApiClient.get<CandidateMatch[]>('/admin/matches');
  },

  async getMatchById(id: number): Promise<CandidateMatch> {
    return ApiClient.get<CandidateMatch>(`/admin/matches/${id}`);
  },

  async reviewMatch(id: number, decision: 'merge' | 'dismiss', notes?: string): Promise<{ matchId: number; decision: string }> {
    return ApiClient.post<{ matchId: number; decision: string }>(`/admin/matches/${id}/review`, { decision, notes });
  },

  async mergeMatch(id: number, notes?: string): Promise<{ matchId: number; decision: string }> {
    return ApiClient.post<{ matchId: number; decision: string }>(`/admin/matches/${id}/merge`, { notes });
  },

  async dismissMatch(id: number): Promise<{ matchId: number; decision: string }> {
    return ApiClient.post<{ matchId: number; decision: string }>(`/admin/matches/${id}/dismiss`);
  },

  async getResponders(): Promise<ResponderUser[]> {
    return ApiClient.get<ResponderUser[]>('/responders');
  },
};

export const responderPortalService = {
  async getMyAssignments(): Promise<any[]> {
    return ApiClient.get<any[]>('/responders/my-assignments');
  },

  async updateDutyStatus(status: 'available' | 'busy' | 'offline', currentLocation?: string): Promise<any> {
    return ApiClient.put<any>('/responders/status', { status, currentLocation });
  },
};

export const notificationService = {
  async getNotifications(unreadOnly = false): Promise<NotificationItem[]> {
    return ApiClient.get<NotificationItem[]>('/notifications', { unread: unreadOnly ? '1' : undefined });
  },

  async markRead(id: number): Promise<void> {
    await ApiClient.put(`/notifications/${id}/read`);
  },

  async markAllRead(): Promise<void> {
    await ApiClient.put('/notifications/read-all');
  },
};

