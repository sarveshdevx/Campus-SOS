import { IncidentAttachment } from './incident';

export type SosStatus =
  | 'triggered'
  | 'acknowledged'
  | 'dispatched'
  | 'resolved'
  | 'false_alarm';

export interface SosAlert {
  id: number;
  alertCode: string;
  userId?: number;
  userName?: string;
  userPhone?: string;
  status: SosStatus;
  locationName: string;
  roomNumber?: string;
  emergencyType?: string;
  emergencyDetails?: string;
  assignedResponderId?: number;
  assignedResponderName?: string;
  assignedDepartment?: string;
  detectedCategory?: string;
  routingDecision?: string;
  triageStatus?: 'awaiting_triage' | 'routed';
  coordinatesLat?: number;
  coordinatesLng?: number;
  triggeredAt: string;
  acknowledgedAt?: string;
  resolvedAt?: string;
  resolutionNotes?: string;
  createdAt: string;
  attachments?: IncidentAttachment[];

  // Optional snake_case aliases
  alert_code?: string;
  user_name?: string;
  user_phone?: string;
  location_name?: string;
  room_number?: string;
  emergency_type?: string;
  emergency_details?: string;
  assigned_responder_id?: number;
  assigned_responder_name?: string;
  assigned_department?: string;
  detected_category?: string;
  routing_decision?: string;
  triage_status?: string;
  triggered_at?: string;
}

export interface EmergencyContact {
  id: number;
  userId: number;
  contactName: string;
  relationship: string;
  phoneNumber: string;
  email?: string;
  isPrimary: boolean;
  createdAt: string;
}

export interface TriggerSosPayload {
  locationName: string;
  roomNumber?: string;
  emergencyType?: string;
  emergencyDetails?: string;
  emergencyNotes?: string;
  coordinatesLat?: number;
  coordinatesLng?: number;
  images?: Array<{ data: string; name?: string }>;
}

