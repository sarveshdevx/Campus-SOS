import { ApiClient } from './api';

export type DepartmentCategory =
  | 'medical'
  | 'security'
  | 'fire'
  | 'maintenance'
  | 'anti_ragging'
  | 'sexual_harassment';

export interface DepartmentContact {
  id: number;
  category: DepartmentCategory;
  departmentName: string;
  representativeName?: string | null;
  designation?: string | null;
  email?: string | null;
  phoneNumber?: string | null;
  officeLocation?: string | null;
  description?: string | null;
  isConfigured: boolean;
  isOfficialVerified: boolean;
  updatedByName?: string | null;
  updatedAt: string;
}

export interface UpdateDepartmentContactPayload {
  departmentName?: string;
  representativeName?: string | null;
  designation?: string | null;
  email?: string | null;
  phoneNumber?: string | null;
  officeLocation?: string | null;
  description?: string | null;
  isConfigured?: boolean;
  isOfficialVerified?: boolean;
}

export const contactService = {
  async getContacts(): Promise<DepartmentContact[]> {
    return ApiClient.get<DepartmentContact[]>('/contacts');
  },

  async getContactById(id: number): Promise<DepartmentContact> {
    return ApiClient.get<DepartmentContact>(`/contacts/${id}`);
  },

  async updateContact(
    id: number,
    payload: UpdateDepartmentContactPayload
  ): Promise<DepartmentContact> {
    return ApiClient.put<DepartmentContact>(`/contacts/${id}`, payload);
  },
};

