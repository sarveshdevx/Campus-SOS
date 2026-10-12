import { ApiClient } from './api';
import { User, UserRole, ResponderCategory } from '../types/auth';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterPayload {
  fullName: string;
  email: string;
  password: string;
  password_confirmation?: string;
  studentId?: string;
  phoneNumber?: string;
  role?: UserRole;
  category?: ResponderCategory;
  department?: string;
}

export interface AuthSuccessResponse {
  user: User;
  csrfToken?: string;
}

export const authService = {
  async getMe(): Promise<User> {
    const data = await ApiClient.get<{ user: User; csrfToken?: string }>('/auth/me');
    return data.user;
  },

  async login(credentials: LoginCredentials): Promise<AuthSuccessResponse> {
    return ApiClient.post<AuthSuccessResponse>('/auth/login', credentials);
  },

  async register(payload: RegisterPayload): Promise<AuthSuccessResponse> {
    // Map snake_case or camelCase consistently for backend AuthValidator
    const requestBody = {
      fullName: payload.fullName,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.password_confirmation || payload.password,
      studentId: payload.studentId,
      phoneNumber: payload.phoneNumber,
    };
    return ApiClient.post<AuthSuccessResponse>('/auth/register', requestBody);
  },

  async logout(): Promise<{ message: string }> {
    return ApiClient.post<{ message: string }>('/auth/logout');
  },

  async getCsrfToken(): Promise<string> {
    return ApiClient.refreshCsrfToken();
  },
};

