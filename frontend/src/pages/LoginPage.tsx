import React, { useState } from 'react';
import { AlertCircle, Shield, CheckCircle2 } from 'lucide-react';
import { useAuth } from '../hooks/useAuth';

interface LoginPageProps {
  onSuccess: (role?: string) => void;
  onNavigateToRegister: () => void;
}

interface DemoAccount {
  label: string;
  email: string;
  roleTitle: string;
  badgeLabel: string;
}

const DEMO_ACCOUNTS: DemoAccount[] = [
  // Row 1: Admin Demo, Medical Responder
  { label: 'Admin Demo', email: 'admin@campussos.edu', roleTitle: 'Administrator', badgeLabel: 'ADMIN' },
  { label: 'Medical Responder', email: 'medical@campussos.edu', roleTitle: 'Medical Response', badgeLabel: 'MED' },

  // Row 2: Security Responder, Fire & Safety
  { label: 'Security Responder', email: 'security@campussos.edu', roleTitle: 'Campus Security', badgeLabel: 'SEC' },
  { label: 'Fire & Safety Responder', email: 'fire@campussos.edu', roleTitle: 'Fire & Safety', badgeLabel: 'FIRE' },

  // Row 3: Maintenance Responder, Anti-Ragging Responder
  { label: 'Maintenance Responder', email: 'maintenance@campussos.edu', roleTitle: 'Maintenance', badgeLabel: 'ENG' },
  { label: 'Anti-Ragging Responder', email: 'antiragging@campussos.edu', roleTitle: 'Anti-Ragging Committee', badgeLabel: 'ARC' },

  // Row 4: ICC Responder, Student Demo
  { label: 'ICC Responder', email: 'icc@campussos.edu', roleTitle: 'Internal Complaints Committee', badgeLabel: 'ICC' },
  { label: 'Student Demo', email: 'student@campussos.edu', roleTitle: 'Student', badgeLabel: 'STU' },
];

export const LoginPage: React.FC<LoginPageProps> = ({ onSuccess, onNavigateToRegister }) => {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [selectedDemo, setSelectedDemo] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setIsSubmitting(true);

    try {
      const user = await login({ email: email.trim(), password });
      onSuccess(user.role);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Invalid login credentials.';
      setErrorMessage(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSelectDemo = (account: DemoAccount) => {
    setEmail(account.email);
    setPassword('Password@2026');
    setSelectedDemo(account.email);
    setErrorMessage(null);
  };

  const activeAccount = DEMO_ACCOUNTS.find((acc) => acc.email === selectedDemo);

  return (
    <div style={{ maxWidth: '480px', margin: '2.5rem auto', padding: '0 1rem' }}>
      <div className="card" style={{ padding: '2.5rem 2rem' }}>
        <div style={{ textAlign: 'center', marginBottom: '1.75rem' }}>
          <div
            style={{
              backgroundColor: 'var(--cobalt)',
              color: '#fff',
              width: '3.5rem',
              height: '3.5rem',
              borderRadius: 'var(--radius-lg)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              margin: '0 auto 1rem auto',
              border: 'var(--border-black)',
              boxShadow: 'var(--shadow-neo-sm)',
            }}
          >
            <Shield size={28} />
          </div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 900, letterSpacing: '-0.03em', marginBottom: '0.25rem' }}>
            CampusSOS Login
          </h1>
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', fontWeight: 600 }}>
            Access student portal or authorized staff console
          </p>
        </div>

        {errorMessage && (
          <div
            style={{
              backgroundColor: 'var(--coral-light)',
              border: 'var(--border-black)',
              boxShadow: '2px 2px 0px #0F1115',
              color: 'var(--coral)',
              padding: '0.75rem 1rem',
              borderRadius: 'var(--radius-sm)',
              fontSize: '0.875rem',
              fontWeight: 700,
              marginBottom: '1.25rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
            }}
          >
            <AlertCircle size={18} />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Selected demo banner indicator */}
        {activeAccount && (
          <div
            style={{
              backgroundColor: 'var(--yellow-card)',
              border: 'var(--border-black)',
              boxShadow: '2px 2px 0px #0F1115',
              padding: '0.55rem 0.85rem',
              borderRadius: 'var(--radius-sm)',
              fontSize: '0.8125rem',
              fontWeight: 700,
              marginBottom: '1.25rem',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <CheckCircle2 size={16} color="#0F1115" />
              <span>
                Demo Selected: <strong>{activeAccount.label}</strong> ({activeAccount.roleTitle})
              </span>
            </div>
            <span
              className="badge badge-black"
              style={{ fontSize: '0.6875rem', padding: '0.1rem 0.4rem' }}
            >
              Ready
            </span>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label className="form-label" htmlFor="login-email">
              Campus Email
            </label>
            <input
              id="login-email"
              type="email"
              className="form-input"
              placeholder="e.g. student@campussos.edu"
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                if (selectedDemo && e.target.value !== selectedDemo) {
                  setSelectedDemo(null);
                }
              }}
              required
              disabled={isSubmitting}
            />
          </div>

          <div className="form-group">
            <label className="form-label" htmlFor="login-password">
              Password
            </label>
            <input
              id="login-password"
              type="password"
              className="form-input"
              placeholder="••••••••••••"
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
              }}
              required
              disabled={isSubmitting}
            />
          </div>

          <button
            type="submit"
            className="btn btn-primary btn-lg"
            style={{ width: '100%', marginTop: '0.5rem' }}
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Authenticating...' : 'Sign In'}
          </button>
        </form>

        {/* Expo Demo Accounts Grid */}
        <div
          style={{
            marginTop: '1.75rem',
            paddingTop: '1.25rem',
            borderTop: 'var(--border-black)',
          }}
        >
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              marginBottom: '0.625rem',
            }}
          >
            <span
              style={{
                fontWeight: 900,
                fontSize: '0.8125rem',
                letterSpacing: '0.02em',
                textTransform: 'uppercase',
                color: '#0F1115',
              }}
            >
              Expo Demo Accounts
            </span>
            <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
              Select to auto-fill
            </span>
          </div>

          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
              gap: '0.5rem',
            }}
          >
            {DEMO_ACCOUNTS.map((account) => {
              const isSelected = selectedDemo === account.email;
              return (
                <button
                  key={account.email}
                  type="button"
                  onClick={() => handleSelectDemo(account)}
                  disabled={isSubmitting}
                  style={{
                    backgroundColor: isSelected ? '#0F1115' : '#FFFFFF',
                    color: isSelected ? '#FFFFFF' : '#0F1115',
                    border: 'var(--border-black)',
                    boxShadow: isSelected ? 'none' : '2px 2px 0px #0F1115',
                    padding: '0.55rem 0.625rem',
                    borderRadius: 'var(--radius-sm)',
                    fontWeight: 700,
                    fontSize: '0.8125rem',
                    textAlign: 'left',
                    cursor: 'pointer',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '0.15rem',
                    transition: 'all 0.1s ease',
                    transform: isSelected ? 'translate(2px, 2px)' : 'none',
                  }}
                  title={`Auto-fill credentials for ${account.roleTitle}`}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontWeight: 800 }}>{account.label}</span>
                    {isSelected && (
                      <span
                        style={{
                          width: '8px',
                          height: '8px',
                          borderRadius: '50%',
                          backgroundColor: 'var(--lime)',
                          display: 'inline-block',
                        }}
                      />
                    )}
                  </div>
                  <span
                    style={{
                      fontSize: '0.6875rem',
                      fontWeight: 600,
                      color: isSelected ? 'rgba(255,255,255,0.7)' : 'var(--text-secondary)',
                    }}
                  >
                    {account.roleTitle}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        <div style={{ textAlign: 'center', marginTop: '1.25rem', fontSize: '0.875rem', fontWeight: 600 }}>
          Don't have an account?{' '}
          <button
            type="button"
            onClick={onNavigateToRegister}
            style={{
              background: 'none',
              border: 'none',
              color: 'var(--cobalt)',
              fontWeight: 800,
              cursor: 'pointer',
              textDecoration: 'underline',
            }}
          >
            Register Student Profile
          </button>
        </div>
      </div>
    </div>
  );
};
