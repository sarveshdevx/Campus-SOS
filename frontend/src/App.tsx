import React, { useState, useEffect } from 'react';
import { AuthProvider } from './features/auth/AuthContext';
import { Navbar } from './components/layout/Navbar';
import { Footer } from './components/layout/Footer';
import { FloatingSosButton } from './components/common/FloatingSosButton';
import { HomePage } from './pages/HomePage';
import { ReportIncidentPage } from './pages/ReportIncidentPage';
import { IncidentTrackingPage } from './pages/IncidentTrackingPage';
import { AdminDashboardPage } from './pages/AdminDashboardPage';
import { ResponderDashboardPage } from './pages/ResponderDashboardPage';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { ContactPage } from './pages/ContactPage';
import { ShieldAlert } from 'lucide-react';

function parseRouteFromLocation(): { tab: string; mode: 'standard' | 'confidential' } {
  const pathname = window.location.pathname.replace(/^\/+/, '').replace(/\/+$/, '');
  const searchParams = new URLSearchParams(window.location.search);
  const hash = window.location.hash.replace(/^#/, '');

  let tab = 'home';
  if (pathname === 'report' || searchParams.get('tab') === 'report' || hash === 'report') {
    tab = 'report';
  } else if (pathname === 'tracker' || searchParams.get('tab') === 'tracker' || hash === 'tracker') {
    tab = 'tracker';
  } else if (pathname === 'contact' || searchParams.get('tab') === 'contact' || hash === 'contact') {
    tab = 'contact';
  } else if (pathname === 'admin' || searchParams.get('tab') === 'admin' || hash === 'admin') {
    tab = 'admin';
  } else if (pathname === 'responders' || searchParams.get('tab') === 'responders' || hash === 'responders') {
    tab = 'responders';
  } else if (pathname === 'login' || searchParams.get('tab') === 'login' || hash === 'login') {
    tab = 'login';
  } else if (pathname === 'register' || searchParams.get('tab') === 'register' || hash === 'register') {
    tab = 'register';
  }

  const modeParam = searchParams.get('mode');
  const mode: 'standard' | 'confidential' = modeParam === 'confidential' ? 'confidential' : 'standard';

  return { tab, mode };
}

export const AppContent: React.FC = () => {
  const initialRoute = parseRouteFromLocation();
  const [currentTab, setCurrentTab] = useState<string>(initialRoute.tab);
  const [reportMode, setReportMode] = useState<'standard' | 'confidential'>(initialRoute.mode);
  const [activeSosNotification, setActiveSosNotification] = useState<string | null>(null);

  useEffect(() => {
    const handlePopState = () => {
      const route = parseRouteFromLocation();
      setCurrentTab(route.tab);
      setReportMode(route.mode);
    };
    window.addEventListener('popstate', handlePopState);
    return () => window.removeEventListener('popstate', handlePopState);
  }, []);

  const handleNavigate = (tab: string, options?: { mode?: 'standard' | 'confidential' }) => {
    const mode = options?.mode || 'standard';
    setReportMode(mode);
    setCurrentTab(tab);

    // Sync browser URL
    let targetPath = tab === 'home' ? '/' : `/${tab}`;
    if (tab === 'report') {
      targetPath = mode === 'confidential' ? '/report?mode=confidential' : '/report';
    }

    if (window.location.pathname + window.location.search !== targetPath) {
      window.history.pushState({ tab, mode }, '', targetPath);
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSosTriggered = (alertId: number) => {
    setActiveSosNotification(`Alert #SOS-${alertId} dispatched to Campus Security & Medical.`);
  };

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      {/* Top Navigation */}
      <Navbar
        currentTab={currentTab}
        onSelectTab={handleNavigate}
        activeSosCount={activeSosNotification ? 1 : 0}
      />

      {/* High-visibility active emergency banner if SOS was fired */}
      {activeSosNotification && (
        <aside
          role="status"
          aria-live="assertive"
          style={{
            backgroundColor: 'var(--coral)',
            color: '#FFFFFF',
            padding: '0.75rem 1rem',
            textAlign: 'center',
            fontWeight: 700,
            fontSize: '0.9375rem',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '0.75rem',
          }}
        >
          <ShieldAlert size={20} />
          <span>{activeSosNotification}</span>
          <button
            type="button"
            onClick={() => setActiveSosNotification(null)}
            style={{
              background: 'rgba(255,255,255,0.2)',
              border: 'none',
              color: '#fff',
              padding: '0.25rem 0.625rem',
              borderRadius: 'var(--radius-sm)',
              cursor: 'pointer',
              fontWeight: 700,
              fontSize: '0.75rem',
            }}
          >
            Dismiss
          </button>
        </aside>
      )}

      {/* Main Content Area */}
      <main className="container" style={{ flex: 1, padding: '2rem 1.25rem' }}>
        {currentTab === 'home' && (
          <HomePage
            onNavigate={handleNavigate}
            onSosTriggered={handleSosTriggered}
          />
        )}
        {currentTab === 'report' && (
          <ReportIncidentPage
            onIncidentCreated={() => setCurrentTab('tracker')}
            onNavigate={handleNavigate}
            initialMode={reportMode}
          />
        )}
        {currentTab === 'tracker' && <IncidentTrackingPage onNavigate={handleNavigate} />}
        {currentTab === 'contact' && <ContactPage onNavigate={handleNavigate} />}
        {currentTab === 'admin' && (
          <AdminDashboardPage onNavigateToLogin={() => setCurrentTab('login')} />
        )}
        {currentTab === 'responders' && <ResponderDashboardPage />}
        {currentTab === 'login' && (
          <LoginPage
            onSuccess={(role?: string) => {
              if (role === 'admin') {
                handleNavigate('admin');
              } else if (role === 'responder') {
                handleNavigate('responders');
              } else {
                handleNavigate('home');
              }
            }}
            onNavigateToRegister={() => handleNavigate('register')}
          />
        )}
        {currentTab === 'register' && (
          <RegisterPage
            onSuccess={() => handleNavigate('home')}
            onNavigateToLogin={() => handleNavigate('login')}
          />
        )}
      </main>

      {/* Persistent Floating SOS Button (Visible across portal; landing page uses hero SOS) */}
      {currentTab !== 'home' && (
        <FloatingSosButton onSosTriggered={handleSosTriggered} />
      )}

      {/* Global Footer with Hotlines and Spec Credits */}
      <Footer />
    </div>
  );
};

export function App() {
  return (
    <AuthProvider>
      <AppContent />
    </AuthProvider>
  );
}

export default App;

