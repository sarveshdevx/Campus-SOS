import { ApiClient } from './api';
import {
  Incident,
  CreateIncidentPayload,
  IncidentStatus,
} from '../types/incident';

export interface IncidentFilterOptions {
  category?: string;
  status?: string;
  priority?: string;
  unassigned_only?: boolean;
}

export interface IncidentAssignmentRecord {
  id: number;
  incident_id: number;
  responder_id: number;
  assignment_status: string;
  assigned_at: string;
  notes?: string;
  responder_name?: string;
  responder_email?: string;
  category?: string;
  contact_phone?: string;
}

export interface IncidentUpdateRecord {
  id: number;
  incident_id: number;
  actor_user_id?: number;
  previous_status?: string;
  new_status: string;
  update_text: string;
  is_internal: boolean;
  created_at: string;
  actor_name?: string;
  actor_role?: string;
}

export interface IncidentDetail extends Omit<Incident, 'updates'> {
  updates?: any[];
  assignments?: IncidentAssignmentRecord[];
  reporter_name?: string;
  reporter_student_id?: string;
  reporter_email?: string;
  reporter_phone?: string;
  is_anonymous?: number | boolean;
  incident_number?: string;
  location_name?: string;
  occurred_at?: string;
  privacy_level?: string;
}

export const incidentService = {
  async getIncidents(filters?: IncidentFilterOptions): Promise<Incident[]> {
    return ApiClient.get<Incident[]>('/incidents', filters as Record<string, string | number | boolean | undefined>);
  },

  async getIncidentById(id: number): Promise<IncidentDetail> {
    return ApiClient.get<IncidentDetail>(`/incidents/${id}`);
  },

  async createIncident(payload: CreateIncidentPayload): Promise<Incident> {
    return ApiClient.post<Incident>('/incidents', payload);
  },

  async updateStatus(
    id: number,
    status: IncidentStatus,
    notes?: string,
    isInternal?: boolean
  ): Promise<{ id: number; status: string }> {
    return ApiClient.put<{ id: number; status: string }>(`/incidents/${id}/status`, {
      status,
      notes,
      isInternal,
    });
  },

  async assignIncident(
    id: number,
    responderId: number,
    notes?: string
  ): Promise<{ incidentId: number; responderId: number }> {
    return ApiClient.post<{ incidentId: number; responderId: number }>(`/incidents/${id}/assign`, {
      responderId,
      notes,
    });
  },

  async acknowledgeAssignment(id: number): Promise<{ incidentId: number; status: string }> {
    return ApiClient.post<{ incidentId: number; status: string }>(`/incidents/${id}/acknowledge`);
  },
};
