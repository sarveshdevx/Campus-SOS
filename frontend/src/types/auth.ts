export type UserRole = 'student' | 'admin' | 'responder';

export type ResponderCategory =
  | 'medical'
  | 'security'
  | 'fire'
  | 'maintenance'
  | 'anti_ragging'
  | 'sexual_harassment';

export interface User {
  id: number;
  fullName: string;
  email: string;
  phoneNumber?: string;
  studentId?: string;
  role: UserRole;
  isActive: boolean;
  responderProfile?: ResponderProfile;
  createdAt: string;
}

export interface ResponderProfile {
  id: number;
  userId: number;
  category: ResponderCategory;
  department: string;
  status: 'available' | 'busy' | 'offline';
  currentLocation?: string;
  contactPhone: string;
}

export interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

