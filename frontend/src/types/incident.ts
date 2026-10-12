import { ResponderCategory } from './auth';

export type IncidentCategory = ResponderCategory | 'other';

export type IncidentStatus =
  | 'reported'
  | 'under_review'
  | 'dispatched'
  | 'in_progress'
  | 'resolved'
  | 'closed'
  | 'rejected';

export type IncidentPriority = 'low' | 'medium' | 'high' | 'critical';

export type PrivacyLevel = 'standard' | 'confidential' | 'sensitive';

export interface IncidentAttachment {
  id: number;
  incidentId?: number;
  incident_id?: number;
  sosAlertId?: number;
  sos_alert_id?: number;
  filePath: string;
  file_path?: string;
  fileName: string;
  file_name?: string;
  fileType: string;
  file_type?: string;
  fileSize: number;
  file_size?: number;
  url?: string;
  downloadUrl?: string;
  uploadedAt: string;
  uploaded_at?: string;
}

export interface IncidentUpdate {
  id: number;
  incidentId: number;
  authorId: number;
  authorName?: string;
  updateType: 'status_change' | 'note' | 'dispatch';
  message: string;
  isInternal: boolean;
  createdAt: string;
}

export interface IncidentMatch {
  id: number;
  primaryIncidentId: number;
  matchedIncidentId: number;
  similarityScore: number;
  matchReason: string;
  status: 'suggested' | 'verified_duplicate' | 'dismissed';
  matchedIncident?: Incident;
  reviewedBy?: number;
  reviewedAt?: string;
  createdAt: string;
}

export interface Incident {
  id: number;
  incidentNumber: string;
  userId?: number;
  reporterUserId?: number;
  reporterName?: string;
  reporterStudentId?: string;
  reporterEmail?: string;
  reporterPhone?: string;
  isAnonymous: boolean;
  privacyLevel: PrivacyLevel;
  category: IncidentCategory;
  title: string;
  description: string;
  locationName: string;
  roomNumber?: string;
  coordinatesLat?: number;
  coordinatesLng?: number;
  occurredAt: string;
  status: IncidentStatus;
  priority: IncidentPriority;
  aiClassified: boolean;
  aiConfidence?: number;
  createdAt: string;
  updatedAt: string;
  attachments?: IncidentAttachment[];
  updates?: IncidentUpdate[];
  matches?: IncidentMatch[];
}

export interface CreateIncidentPayload {
  title: string;
  description: string;
  category: IncidentCategory;
  locationName: string;
  roomNumber?: string;
  occurredAt?: string;
  isAnonymous: boolean;
  privacyLevel: PrivacyLevel;
  reporterUserId?: number;
  images?: Array<{ data: string; name?: string }>;
}

