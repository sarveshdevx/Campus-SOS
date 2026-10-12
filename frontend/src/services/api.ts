import { ApiResponse, ApiError } from '../types/api';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';

export class ApiClient {
  private static csrfToken: string | null = null;

  public static setCsrfToken(token: string | null): void {
    this.csrfToken = token;
  }

  public static getCsrfToken(): string | null {
    return this.csrfToken;
  }

  /**
   * Fetch a fresh CSRF token from the server.
   */
  public static async refreshCsrfToken(): Promise<string> {
    try {
      const res = await fetch(`${BASE_URL}/auth/csrf`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'include',
      });
      const data = await res.json();
      if (data && data.success && data.data && data.data.csrfToken) {
        this.csrfToken = data.data.csrfToken;
        return this.csrfToken as string;
      }
    } catch {
      // CSRF endpoint unreachable
    }
    return '';
  }

  private static async request<T>(
    endpoint: string,
    options: RequestInit = {},
    isRetry = false
  ): Promise<T> {
    const url = `${BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const method = (options.method || 'GET').toUpperCase();

    const headers = new Headers(options.headers || {});
    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json');
    }

    if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    // Attach CSRF token on state-changing requests
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      if (!this.csrfToken && endpoint !== '/auth/csrf') {
        await this.refreshCsrfToken();
      }
      if (this.csrfToken && !headers.has('X-CSRF-Token')) {
        headers.set('X-CSRF-Token', this.csrfToken);
      }
    }

    const config: RequestInit = {
      ...options,
      headers,
      credentials: 'include', // Includes PHP session cookie (PHPSESSID)
    };

    try {
      const response = await fetch(url, config);
      const text = await response.text();
      let json: ApiResponse<T> | ApiError;

      try {
        json = JSON.parse(text);
      } catch {
        throw new Error(
          `Invalid JSON response from server (HTTP ${response.status}): ${text.slice(0, 150)}`
        );
      }

      // Check for CSRF token update in responses
      if ('data' in json && json.data && typeof json.data === 'object' && 'csrfToken' in (json.data as any)) {
        this.csrfToken = (json.data as any).csrfToken;
      }

      // If CSRF mismatch occurred and we haven't retried yet, refresh token and retry
      if (response.status === 403 && (json as ApiError).errorCode === 'CSRF_TOKEN_MISMATCH' && !isRetry) {
        await this.refreshCsrfToken();
        return this.request<T>(endpoint, options, true);
      }

      if (!response.ok || !json.success) {
        const errorMsg = json.message || `Request failed with status ${response.status}`;
        const err = new Error(errorMsg) as Error & { status?: number; data?: unknown; errorCode?: string; errors?: Record<string, string[]> };
        err.status = response.status;
        err.data = json;
        if ('errorCode' in json) err.errorCode = json.errorCode;
        if ('errors' in json) err.errors = json.errors;
        throw err;
      }

      return (json as ApiResponse<T>).data;
    } catch (err: unknown) {
      if (err instanceof Error) {
        throw err;
      }
      throw new Error('An unexpected network error occurred.');
    }
  }

  public static get<T>(endpoint: string, queryParams?: Record<string, string | number | boolean | undefined>): Promise<T> {
    let url = endpoint;
    if (queryParams) {
      const params = new URLSearchParams();
      Object.entries(queryParams).forEach(([key, val]) => {
        if (val !== undefined) params.append(key, String(val));
      });
      const qs = params.toString();
      if (qs) url += `?${qs}`;
    }
    return this.request<T>(url, { method: 'GET' });
  }

  public static post<T>(endpoint: string, body?: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body),
    });
  }

  public static put<T>(endpoint: string, body?: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: body instanceof FormData ? body : JSON.stringify(body),
    });
  }

  public static delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE' });
  }
}
