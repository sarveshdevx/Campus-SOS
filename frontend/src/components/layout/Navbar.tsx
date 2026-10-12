import React, { useState } from 'react';
import { ArrowUpRight, Menu, X, LogOut } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';

interface NavbarProps {
  currentTab: string;
  onSelectTab: (tab: string, options?: { mode?: 'standard' | 'confidential' }) => void;
  activeSosCount?: number;
}

export const Navbar: React.FC<NavbarProps> = ({
  currentTab,
  onSelectTab,
  activeSosCount = 0,
}) => {
  const { user, isAuthenticated, logout } = useAuth();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  // Strictly gate admin link to verified administrator role from authentication state
  const isAdmin = Boolean(isAuthenticated && user && user.role === 'admin');

  const handleNavClick = (tabId: string, options?: { mode?: 'standard' | 'confidential' }) => {
    if (tabId === 'contact') {
      onSelectTab('contact');
      setMobileMenuOpen(false);
      return;
    }
    if (tabId === 'report') {
      onSelectTab('report', options || { mode: 'standard' });
      setMobileMenuOpen(false);
      return;
    }
    onSelectTab(tabId, options);
    setMobileMenuOpen(false);
  };

  return (
    <header
      style={{
        backgroundColor: 'var(--bg-primary)',
        borderBottom: 'var(--border-black)',
        position: 'sticky',
        top: 0,
        zIndex: 900,
        boxShadow: '0 2px 0px rgba(15, 17, 21, 0.08)',
      }}
    >
      <div
        className="container"
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          height: '4.75rem',
        }}
      >
        {/* Wordmark: CampusSOS. */}
        <div
          onClick={() => handleNavClick('home')}
          style={{
            display: 'flex',
            alignItems: 'baseline',
            cursor: 'pointer',
            userSelect: 'none',
          }}
        >
          <span
            style={{
              fontSize: '1.75rem',
              fontWeight: 900,
              letterSpacing: '-0.04em',
              color: '#0F1115',
              fontFamily: 'var(--font-sans)',
            }}
          >
            Campus<span style={{ color: 'var(--cobalt)' }}>SOS.</span>
          </span>
        </div>

        {/* Center Nav Links: Home, Incident Report, Contact, Track Reports, and strictly Admin if authenticated admin */}
        <nav
          style={{
            display: 'none',
            alignItems: 'center',
            gap: '1.85rem',
          }}
          className="desktop-nav"
          aria-label="Main Navigation"
        >
          <button
            type="button"
            onClick={() => handleNavClick('home')}
            style={{
              background: 'none',
              border: 'none',
              padding: '0.25rem 0',
              fontSize: '0.9375rem',
              fontWeight: currentTab === 'home' ? 800 : 600,
              color: '#0F1115',
              cursor: 'pointer',
              borderBottom: currentTab === 'home' ? '2px solid #0F1115' : '2px solid transparent',
              transition: 'border-color var(--transition-fast)',
            }}
          >
            Home
          </button>

          <button
            type="button"
            onClick={() => handleNavClick('report', { mode: 'standard' })}
            style={{
              background: 'none',
              border: 'none',
              padding: '0.25rem 0',
              fontSize: '0.9375rem',
              fontWeight: currentTab === 'report' ? 800 : 600,
              color: '#0F1115',
              cursor: 'pointer',
              borderBottom: currentTab === 'report' ? '2px solid #0F1115' : '2px solid transparent',
              transition: 'border-color var(--transition-fast)',
            }}
          >
            Incident Report
          </button>

          <button
            type="button"
            onClick={() => handleNavClick('contact')}
            style={{
              background: 'none',
              border: 'none',
              padding: '0.25rem 0',
              fontSize: '0.9375rem',
              fontWeight: currentTab === 'contact' ? 800 : 600,
              color: '#0F1115',
              cursor: 'pointer',
              borderBottom: currentTab === 'contact' ? '2px solid #0F1115' : '2px solid transparent',
              transition: 'border-color var(--transition-fast)',
            }}
          >
            Contact
          </button>

          <button
            type="button"
            onClick={() => handleNavClick('tracker')}
            style={{
              background: 'none',
              border: 'none',
              padding: '0.25rem 0',
              fontSize: '0.9375rem',
              fontWeight: currentTab === 'tracker' ? 800 : 600,
              color: '#0F1115',
              cursor: 'pointer',
              borderBottom: currentTab === 'tracker' ? '2px solid #0F1115' : '2px solid transparent',
              transition: 'border-color var(--transition-fast)',
            }}
          >
            Track Reports
          </button>

          {/* Admin link strictly visible to authenticated administrators */}
          {isAdmin && (
            <button
              type="button"
              onClick={() => handleNavClick('admin')}
              style={{
                background: 'none',
                border: 'none',
                padding: '0.25rem 0',
                fontSize: '0.9375rem',
                fontWeight: currentTab === 'admin' ? 800 : 600,
                color: '#0F1115',
                cursor: 'pointer',
                borderBottom: currentTab === 'admin' ? '2px solid #0F1115' : '2px solid transparent',
                transition: 'border-color var(--transition-fast)',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.35rem',
              }}
            >
              <span>Admin</span>
              {activeSosCount > 0 && (
                <span
                  style={{
                    backgroundColor: 'var(--coral)',
                    color: '#0F1115',
                    border: '1px solid #0F1115',
                    borderRadius: 'var(--radius-pill)',
                    padding: '0.1rem 0.4rem',
                    fontSize: '0.6875rem',
                    fontWeight: 900,
                  }}
                >
                  {activeSosCount}
                </span>
              )}
            </button>
          )}

          {/* Responder portal link strictly visible to authenticated responders */}
          {isAuthenticated && user?.role === 'responder' && (
            <button
              type="button"
              onClick={() => handleNavClick('responders')}
              style={{
                background: 'none',
                border: 'none',
                padding: '0.25rem 0',
                fontSize: '0.9375rem',
                fontWeight: currentTab === 'responders' ? 800 : 600,
                color: '#0F1115',
                cursor: 'pointer',
                borderBottom: currentTab === 'responders' ? '2px solid #0F1115' : '2px solid transparent',
                transition: 'border-color var(--transition-fast)',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.35rem',
              }}
            >
              <span>Responder Portal</span>
            </button>
          )}
        </nav>

        {/* Right CTA / Auth Status */}
        <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
          {isAuthenticated && user ? (
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <div
                style={{
                  textAlign: 'right',
                  fontSize: '0.8125rem',
                  lineHeight: 1.2,
                }}
              >
                <div style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                  {user.fullName}
                </div>
                <div
                  style={{
                    color: user.role === 'admin' ? 'var(--coral)' : 'var(--cobalt)',
                    textTransform: 'capitalize',
                    fontWeight: 700,
                  }}
                >
                  {user.role === 'responder' && user.responderProfile
                    ? `Responder (${user.responderProfile.category})`
                    : user.role}
                </div>
              </div>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={logout}
                title="Log out"
                aria-label="Log out"
              >
                <LogOut size={15} />
              </button>
            </div>
          ) : (
            <button
              type="button"
              className="btn btn-black"
              onClick={() => handleNavClick('login')}
              style={{
                borderRadius: 'var(--radius-pill)',
                padding: '0.625rem 1.35rem',
                fontSize: '0.9375rem',
                fontWeight: 800,
                display: 'inline-flex',
                alignItems: 'center',
                gap: '0.5rem',
              }}
            >
              <span>Get Started</span>
              <ArrowUpRight size={18} strokeWidth={2.5} />
            </button>
          )}

          {/* Mobile hamburger */}
          <button
            type="button"
            className="mobile-menu-trigger"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            style={{
              background: '#FFFFFF',
              border: 'var(--border-black)',
              borderRadius: 'var(--radius-sm)',
              padding: '0.5rem',
              cursor: 'pointer',
              display: 'none',
            }}
            aria-label="Toggle navigation menu"
          >
            {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
          </button>
        </div>
      </div>

      {/* Responsive Styles Injection */}
      <style>{`
        @media (min-width: 860px) {
          .desktop-nav {
            display: flex !important;
          }
          .mobile-menu-trigger {
            display: none !important;
          }
        }
        @media (max-width: 859px) {
          .desktop-nav {
            display: none !important;
          }
          .mobile-menu-trigger {
            display: flex !important;
          }
        }
      `}</style>

      {/* Mobile Drawer */}
      {mobileMenuOpen && (
        <div
          style={{
            backgroundColor: 'var(--bg-primary)',
            borderBottom: 'var(--border-black-thick)',
            padding: '1.25rem 1.5rem',
            display: 'flex',
            flexDirection: 'column',
            gap: '0.5rem',
          }}
        >
          <button
            type="button"
            onClick={() => handleNavClick('home')}
            style={{
              background: 'none',
              border: 'none',
              textAlign: 'left',
              fontSize: '1.0625rem',
              fontWeight: currentTab === 'home' ? 800 : 700,
              color: '#0F1115',
              padding: '0.5rem 0',
              cursor: 'pointer',
            }}
          >
            Home
          </button>

          <button
            type="button"
            onClick={() => handleNavClick('report', { mode: 'standard' })}
            style={{
              background: 'none',
              border: 'none',
              textAlign: 'left',
              fontSize: '1.0625rem',
              fontWeight: currentTab === 'report' ? 800 : 700,
              color: '#0F1115',
              padding: '0.5rem 0',
              cursor: 'pointer',
            }}
          >
            Incident Report
          </button>

          <button
            type="button"
            onClick={() => handleNavClick('contact')}
            style={{
              background: 'none',
              border: 'none',
              textAlign: 'left',
              fontSize: '1.0625rem',
              fontWeight: currentTab === 'contact' ? 800 : 700,
              color: '#0F1115',
              padding: '0.5rem 0',
              cursor: 'pointer',
            }}
          >
            Contact
          </button>

          <button
            type="button"
            onClick={() => handleNavClick('tracker')}
            style={{
              background: 'none',
              border: 'none',
              textAlign: 'left',
              fontSize: '1.0625rem',
              fontWeight: currentTab === 'tracker' ? 800 : 700,
              color: '#0F1115',
              padding: '0.5rem 0',
              cursor: 'pointer',
            }}
          >
            Track Reports
          </button>

          {/* Admin link strictly for verified administrator role */}
          {isAdmin && (
            <button
              type="button"
              onClick={() => handleNavClick('admin')}
              style={{
                background: 'none',
                border: 'none',
                textAlign: 'left',
                fontSize: '1.0625rem',
                fontWeight: currentTab === 'admin' ? 800 : 700,
                color: 'var(--coral)',
                padding: '0.5rem 0',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '0.5rem',
              }}
            >
              <span>Admin Console</span>
              {activeSosCount > 0 && (
                <span
                  style={{
                    backgroundColor: 'var(--coral)',
                    color: '#fff',
                    borderRadius: 'var(--radius-pill)',
                    padding: '0.1rem 0.5rem',
                    fontSize: '0.75rem',
                    fontWeight: 900,
                  }}
                >
                  {activeSosCount}
                </span>
              )}
            </button>
          )}

          {/* Responder link strictly for verified responder role */}
          {isAuthenticated && user?.role === 'responder' && (
            <button
              type="button"
              onClick={() => handleNavClick('responders')}
              style={{
                background: 'none',
                border: 'none',
                textAlign: 'left',
                fontSize: '1.0625rem',
                fontWeight: currentTab === 'responders' ? 800 : 700,
                color: 'var(--cobalt)',
                padding: '0.5rem 0',
                cursor: 'pointer',
              }}
            >
              Responder Console
            </button>
          )}

          {/* Session / Account controls inside mobile drawer */}
          <div style={{ borderTop: '1px solid var(--border-black)', margin: '0.5rem 0' }} />

          {isAuthenticated && user ? (
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', paddingTop: '0.25rem' }}>
              <div>
                <div style={{ fontWeight: 800, fontSize: '0.9375rem', color: 'var(--text-primary)' }}>
                  {user.fullName}
                </div>
                <div style={{ color: 'var(--cobalt)', fontSize: '0.8125rem', fontWeight: 700, textTransform: 'capitalize' }}>
                  {user.role}
                </div>
              </div>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => {
                  logout();
                  setMobileMenuOpen(false);
                }}
              >
                Log Out
              </button>
            </div>
          ) : (
            <button
              type="button"
              className="btn btn-black"
              onClick={() => handleNavClick('login')}
              style={{
                borderRadius: 'var(--radius-pill)',
                padding: '0.75rem 1.5rem',
                fontSize: '0.9375rem',
                fontWeight: 800,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '0.5rem',
              }}
            >
              <span>Get Started</span>
              <ArrowUpRight size={18} strokeWidth={2.5} />
            </button>
          )}
        </div>
      )}
    </header>
  );
};
