import { ApiClient } from './api';
import { SosAlert, TriggerSosPayload, EmergencyContact } from '../types/sos';

export const sosService = {
  async triggerSos(payload: TriggerSosPayload): Promise<SosAlert> {
    return ApiClient.post<SosAlert>('/sos/trigger', payload);
  },

  async getActiveAlerts(): Promise<SosAlert[]> {
    return ApiClient.get<SosAlert[]>('/sos/active');
  },

  async acknowledgeSos(alertId: number): Promise<SosAlert> {
    return ApiClient.post<SosAlert>(`/sos/${alertId}/acknowledge`);
  },

  async assignSos(alertId: number, responderId: number, notes?: string): Promise<{ id: number; status: string }> {
    return ApiClient.post<{ id: number; status: string }>(`/sos/${alertId}/assign`, {
      responderId,
      notes,
    });
  },

  async resolveSos(alertId: number, resolutionNotes: string): Promise<SosAlert> {
    return ApiClient.post<SosAlert>(`/sos/${alertId}/resolve`, { resolutionNotes });
  },

  async getEmergencyContacts(): Promise<EmergencyContact[]> {
    return ApiClient.get<EmergencyContact[]>('/sos/contacts');
  },
};
