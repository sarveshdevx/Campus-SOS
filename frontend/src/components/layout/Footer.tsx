import React from 'react';

export const Footer: React.FC = () => {
  return (
    <footer
      id="contact"
      style={{
        backgroundColor: 'var(--bg-primary)',
        borderTop: 'var(--border-black-thick)',
        padding: '2.25rem 0',
        marginTop: '3.5rem',
        color: 'var(--text-secondary)',
      }}
    >
      <div className="container">
        {/* Bottom Credits & Architecture Details */}
        <div
          style={{
            display: 'flex',
            flexWrap: 'wrap',
            justifyContent: 'space-between',
            alignItems: 'center',
            gap: '1.25rem',
            fontSize: '0.8125rem',
            fontWeight: 600,
          }}
        >
          <div>
            <strong style={{ color: 'var(--text-primary)', fontSize: '0.9375rem' }}>CampusSOS</strong> — Working Student Project Expo System.
            <span style={{ display: 'block', marginTop: '0.25rem', color: 'var(--text-secondary)' }}>
              Decoupled Architecture: React + TypeScript Vite frontend talking to modular PHP 8.2 PDO &amp; MySQL backend.
            </span>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flexWrap: 'wrap' }}>
            <span className="badge badge-mint">Confidential Reporting</span>
            <span className="badge badge-coral">Deterministic SOS</span>
          </div>
        </div>
      </div>
    </footer>
  );
};
