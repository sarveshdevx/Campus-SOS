import React, { useState, useEffect } from 'react';
import {
  Radio,
  MapPin,
  Check,
  X,
  Lock,
  RefreshCw,
  Search,
  AlertTriangle,
  Clock,
  User,
  EyeOff,
  GitMerge,
  Eye,
  Camera,
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { adminService, AdminMetrics, CandidateMatch, AuditLogEntry, ResponderUser } from '../services/adminService';
import { incidentService, IncidentDetail } from '../services/incidentService';
import { sosService } from '../services/sosService';
import { Incident, IncidentAttachment } from '../types/incident';
import { SosAlert } from '../types/sos';
import { ImageLightboxModal } from '../components/common/ImageLightboxModal';

interface AdminDashboardPageProps {
  onNavigateToLogin?: () => void;
}

export const AdminDashboardPage: React.FC<AdminDashboardPageProps> = ({ onNavigateToLogin }) => {
  const { user, isAuthenticated } = useAuth();

  // Dashboard Data State
  const [metrics, setMetrics] = useState<AdminMetrics | null>(null);
  const [activeSos, setActiveSos] = useState<SosAlert[]>([]);
  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [candidateMatches, setCandidateMatches] = useState<CandidateMatch[]>([]);
  const [auditLogs, setAuditLogs] = useState<AuditLogEntry[]>([]);
  const [responders, setResponders] = useState<ResponderUser[]>([]);

  // UI state
  const [isLoading, setIsLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'overview' | 'incidents' | 'sos' | 'matches' | 'audit'>('overview');
  const [filterCategory, setFilterCategory] = useState<string>('all');
  const [filterStatus, setFilterStatus] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedIncident, setSelectedIncident] = useState<IncidentDetail | null>(null);

  // Assignment & Status Modal State
  const [assignModalIncident, setAssignModalIncident] = useState<Incident | null>(null);
  const [selectedResponderId, setSelectedResponderId] = useState<number>(0);
  const [assignNotes, setAssignNotes] = useState<string>('');
  const [statusUpdateIncident, setStatusUpdateIncident] = useState<Incident | null>(null);
  const [newStatus, setNewStatus] = useState<string>('in_progress');
  const [statusNotes, setStatusNotes] = useState<string>('');
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [lightboxAttachment, setLightboxAttachment] = useState<IncidentAttachment | null>(null);
  const [lightboxTitle, setLightboxTitle] = useState<string>('Visual Evidence');

  const fetchDashboardData = async () => {
    setIsLoading(true);
    try {
      const [m, sos, incs, matches, logs, resps] = await Promise.all([
        adminService.getMetrics().catch(() => null),
        sosService.getActiveAlerts().catch(() => []),
        incidentService.getIncidents().catch(() => []),
        adminService.getCandidateMatches().catch(() => []),
        adminService.getAuditLogs().catch(() => []),
        adminService.getResponders().catch(() => []),
      ]);

      if (m) setMetrics(m);
      setActiveSos(sos);
      setIncidents(incs);
      setCandidateMatches(matches);
      setAuditLogs(logs);
      setResponders(resps);
    } catch {
      // Data fetch error handled gracefully
    } finally {
      setIsLoading(false);
    }
  };

  // SOS Triage Modal State
  const [sosTriageAlert, setSosTriageAlert] = useState<SosAlert | null>(null);
  const [triageResponderId, setTriageResponderId] = useState<number>(0);
  const [triageNotes, setTriageNotes] = useState<string>('');

  useEffect(() => {
    if (isAuthenticated && user?.role === 'admin') {
      fetchDashboardData();
      const interval = setInterval(fetchDashboardData, 3000);
      return () => clearInterval(interval);
    }
  }, [isAuthenticated, user]);

  const handleResolveSos = async (id: number) => {
    try {
      await sosService.resolveSos(id, 'Resolved via Emergency Dispatch Admin Console');
      setActiveSos((prev) => prev.filter((item) => item.id !== id));
      setActionSuccess(`SOS alert #${id} marked resolved.`);
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to resolve SOS alert.');
    }
  };

  const handleAcknowledgeSos = async (id: number) => {
    try {
      await sosService.acknowledgeSos(id);
      fetchDashboardData();
      setActionSuccess(`SOS alert #${id} acknowledged.`);
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to acknowledge alert.');
    }
  };

  const handleSosTriageSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!sosTriageAlert || !triageResponderId) return;

    try {
      await sosService.assignSos(
        sosTriageAlert.id,
        triageResponderId,
        triageNotes || 'Manual dispatch by Administrator triage'
      );
      setSosTriageAlert(null);
      setTriageResponderId(0);
      setTriageNotes('');
      setActionSuccess(`SOS #${sosTriageAlert.alertCode || sosTriageAlert.id} successfully assigned to designated responder.`);
      fetchDashboardData();
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to assign responder.');
    }
  };

  // Duplicate Review Modal States
  const [selectedMatchDetail, setSelectedMatchDetail] = useState<CandidateMatch | null>(null);
  const [mergeConfirmMatch, setMergeConfirmMatch] = useState<CandidateMatch | null>(null);
  const [mergeNotes, setMergeNotes] = useState<string>('');
  const [isSubmittingMerge, setIsSubmittingMerge] = useState<boolean>(false);

  const handleReviewMatch = async (id: number, decision: 'merge' | 'dismiss') => {
    try {
      await adminService.reviewMatch(id, decision);
      setCandidateMatches((prev) => prev.filter((m) => m.id !== id));
      setActionSuccess(`Candidate match #${id} ${decision}ed.`);
      fetchDashboardData();
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to review candidate match.');
    }
  };

  const handleConfirmMergeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!mergeConfirmMatch) return;
    setIsSubmittingMerge(true);

    try {
      await adminService.reviewMatch(mergeConfirmMatch.id, 'merge', mergeNotes);
      setCandidateMatches((prev) => prev.filter((m) => m.id !== mergeConfirmMatch.id));
      setActionSuccess(`Duplicate candidate #${mergeConfirmMatch.id} confirmed and records successfully merged.`);
      setMergeConfirmMatch(null);
      setMergeNotes('');
      if (selectedMatchDetail?.id === mergeConfirmMatch.id) {
        setSelectedMatchDetail(null);
      }
      fetchDashboardData();
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to merge candidate records.');
    } finally {
      setIsSubmittingMerge(false);
    }
  };

  const handleAssignSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!assignModalIncident || !selectedResponderId) return;

    try {
      await incidentService.assignIncident(assignModalIncident.id, selectedResponderId, assignNotes);
      setAssignModalIncident(null);
      setSelectedResponderId(0);
      setAssignNotes('');
      setActionSuccess(`Incident ${assignModalIncident.incidentNumber} assigned successfully.`);
      fetchDashboardData();
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to assign incident.');
    }
  };

  const handleStatusUpdateSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!statusUpdateIncident) return;

    try {
      await incidentService.updateStatus(statusUpdateIncident.id, newStatus as any, statusNotes);
      setStatusUpdateIncident(null);
      setStatusNotes('');
      setActionSuccess(`Incident ${statusUpdateIncident.incidentNumber} updated to ${newStatus}.`);
      fetchDashboardData();
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to update status.');
    }
  };

  const handleViewIncidentDetail = async (id: number) => {
    try {
      const detail = await incidentService.getIncidentById(id);
      setSelectedIncident(detail);
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to load details.');
    }
  };

  if (!isAuthenticated || user?.role !== 'admin') {
    return (
      <div style={{ maxWidth: '600px', margin: '4rem auto', textAlign: 'center' }}>
        <div className="card" style={{ padding: '3rem 2rem' }}>
          <div
            style={{
              backgroundColor: 'var(--coral-light)',
              color: 'var(--coral)',
              width: '4rem',
              height: '4rem',
              borderRadius: 'var(--radius-pill)',
              border: 'var(--border-black-thick)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              margin: '0 auto 1.5rem auto',
            }}
          >
            <Lock size={32} />
          </div>
          <h2 style={{ fontSize: '1.75rem', fontWeight: 900, marginBottom: '0.5rem' }}>
            {isAuthenticated ? 'Administrator Access Restricted' : 'Authentication Required'}
          </h2>
          <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem', fontSize: '0.9375rem', fontWeight: 600 }}>
            {isAuthenticated && user
              ? `Signed in as ${user.role} (${user.email}). The Mission Control dispatch console is restricted to verified campus safety administrators.`
              : 'Please sign in with administrator credentials to access the emergency dispatch and triage console.'}
          </p>
          {onNavigateToLogin && (
            <button
              type="button"
              className="btn btn-black"
              onClick={onNavigateToLogin}
            >
              Sign In with Admin Account
            </button>
          )}
        </div>
      </div>
    );
  }

  const filteredIncidents = incidents.filter((inc) => {
    const matchesCat = filterCategory === 'all' || inc.category === filterCategory;
    const matchesStatus = filterStatus === 'all' || inc.status === filterStatus;
    const matchesSearch =
      !searchQuery ||
      inc.incidentNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
      inc.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      inc.locationName.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesCat && matchesStatus && matchesSearch;
  });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
            <span className="badge badge-coral">MISSION CONTROL</span>
            <span style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 700 }}>
              Campus Safety &amp; Emergency Dispatch
            </span>
          </div>
          <h1 style={{ fontSize: '2.25rem', fontWeight: 900, letterSpacing: '-0.03em' }}>
            Administrative Command Center
          </h1>
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.9375rem', fontWeight: 600 }}>
            Real-time emergency dispatch, responder assignment, and incident triage for verified staff.
          </p>
        </div>

        <button
          type="button"
          className="btn btn-secondary btn-sm"
          onClick={fetchDashboardData}
          disabled={isLoading}
        >
          <RefreshCw size={15} className={isLoading ? 'animate-spin' : ''} />
          <span>Refresh Data</span>
        </button>
      </div>

      {/* Action Notification Alerts */}
      {actionSuccess && (
        <div
          style={{
            backgroundColor: 'var(--lime-card)',
            border: 'var(--border-black)',
            boxShadow: 'var(--shadow-neo-sm)',
            padding: '0.875rem 1.25rem',
            borderRadius: 'var(--radius-sm)',
            fontWeight: 800,
            fontSize: '0.875rem',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Check size={18} />
            <span>{actionSuccess}</span>
          </div>
          <button type="button" onClick={() => setActionSuccess(null)} style={{ background: 'none', border: 'none', cursor: 'pointer' }}>
            <X size={16} />
          </button>
        </div>
      )}

      {actionError && (
        <div
          style={{
            backgroundColor: 'var(--coral-light)',
            border: 'var(--border-black)',
            boxShadow: 'var(--shadow-neo-sm)',
            color: 'var(--coral)',
            padding: '0.875rem 1.25rem',
            borderRadius: 'var(--radius-sm)',
            fontWeight: 800,
            fontSize: '0.875rem',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <AlertTriangle size={18} />
            <span>{actionError}</span>
          </div>
          <button type="button" onClick={() => setActionError(null)} style={{ background: 'none', border: 'none', cursor: 'pointer' }}>
            <X size={16} />
          </button>
        </div>
      )}

      {/* Metrics Row (Real Database Values) */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
          gap: '1.25rem',
        }}
      >
        <div className="card" style={{ backgroundColor: activeSos.length > 0 ? 'var(--coral-light)' : '#FFFFFF' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--coral)' }}>
            Active SOS Alerts
          </div>
          <div style={{ fontSize: '2.5rem', fontWeight: 900, margin: '0.25rem 0', color: 'var(--text-primary)' }}>
            {activeSos.length}
          </div>
          <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
            {activeSos.length > 0 ? 'Urgent action required' : 'No active alerts'}
          </div>
        </div>

        <div className="card" style={{ backgroundColor: '#FFFFFF' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--cobalt)' }}>
            Open Incidents
          </div>
          <div style={{ fontSize: '2.5rem', fontWeight: 900, margin: '0.25rem 0', color: 'var(--text-primary)' }}>
            {metrics?.openIncidentsCount ?? incidents.filter(i => ['reported', 'under_review', 'dispatched', 'in_progress'].includes(i.status)).length}
          </div>
          <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>Under triage &amp; handling</div>
        </div>

        <div className="card" style={{ backgroundColor: '#FFFFFF' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-primary)' }}>
            Unassigned Incidents
          </div>
          <div style={{ fontSize: '2.5rem', fontWeight: 900, margin: '0.25rem 0', color: 'var(--text-primary)' }}>
            {metrics?.unassignedCount ?? 0}
          </div>
          <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>Awaiting unit dispatch</div>
        </div>

        <div className="card" style={{ backgroundColor: '#FFFFFF' }}>
          <div style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--text-secondary)' }}>
            On-Duty Responders
          </div>
          <div style={{ fontSize: '2.5rem', fontWeight: 900, margin: '0.25rem 0', color: 'var(--text-primary)' }}>
            {responders.length}
          </div>
          <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>Across 6 campus categories</div>
        </div>
      </div>

      {/* Navigation Pills */}
      <div style={{ display: 'flex', gap: '0.5rem', borderBottom: 'var(--border-black)', paddingBottom: '0.75rem', flexWrap: 'wrap' }}>
        {[
          { id: 'overview', label: `Active SOS & Triage (${activeSos.length})` },
          { id: 'incidents', label: `Incident Directory (${incidents.length})` },
          { id: 'matches', label: `Duplicate Reviews (${candidateMatches.length})` },
          { id: 'audit', label: `Security Audit Trail (${auditLogs.length})` },
        ].map((tab) => (
          <button
            key={tab.id}
            type="button"
            className={`btn btn-sm ${activeTab === tab.id ? 'btn-black' : 'btn-secondary'}`}
            onClick={() => setActiveTab(tab.id as any)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* TAB 1: OVERVIEW & ACTIVE SOS */}
      {activeTab === 'overview' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
          {/* Active SOS Panel */}
          <section>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '1rem' }}>
              <Radio size={20} color="var(--coral)" className="animate-pulse" />
              <h2 style={{ fontSize: '1.25rem', fontWeight: 900, textTransform: 'uppercase' }}>
                Active Emergency SOS Queue
              </h2>
            </div>

            {activeSos.length === 0 ? (
              <div className="card" style={{ textAlign: 'center', padding: '3rem 2rem', backgroundColor: '#FFFFFF' }}>
                <Check size={36} color="var(--mint)" style={{ margin: '0 auto 0.75rem auto' }} />
                <h3 style={{ fontSize: '1.125rem', fontWeight: 800 }}>No Active SOS Alerts</h3>
                <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', fontWeight: 600 }}>
                  Campus safety perimeter clear. All recent signals acknowledged and resolved.
                </p>
              </div>
            ) : (
              <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                {activeSos.map((alert) => {
                  const isAwaitingTriage = alert.triageStatus === 'awaiting_triage' ||
                    (alert as any).triage_status === 'awaiting_triage' ||
                    (alert.emergencyType === 'Other Emergency' && !alert.assignedResponderId && !(alert as any).assigned_responder_id);
                  const isCustom = alert.emergencyType === 'Custom' || (alert as any).emergency_type === 'Custom';
                  const detectedCat = alert.detectedCategory || (alert as any).detected_category;
                  const assignedName = alert.assignedResponderName || (alert as any).assigned_responder_name;
                  const routingDesc = alert.routingDecision || (alert as any).routing_decision;
                  const details = alert.emergencyDetails || (alert as any).emergency_details;

                  return (
                    <div
                      key={alert.id}
                      className="card"
                      style={{
                        borderLeft: isAwaitingTriage ? '8px solid var(--lavender)' : '8px solid var(--coral)',
                        backgroundColor: '#FFFFFF',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: '1rem',
                        boxShadow: 'var(--shadow-sm)',
                      }}
                    >
                      <div style={{ maxWidth: '680px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.375rem', flexWrap: 'wrap' }}>
                          <span className="badge badge-coral">EMERGENCY SOS</span>
                          <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 900, fontSize: '1rem' }}>
                            {alert.alertCode || (alert as any).alert_code || `SOS-${alert.id}`}
                          </span>
                          <span className="badge badge-yellow">{alert.status.toUpperCase()}</span>
                          {(alert.emergencyType || (alert as any).emergency_type) && (
                            <span className="badge badge-cobalt">
                              {(alert.emergencyType || (alert as any).emergency_type).toUpperCase()}
                            </span>
                          )}
                          {detectedCat && (
                            <span className="badge badge-mint">
                              AI DETECTED: {detectedCat.toUpperCase()}
                            </span>
                          )}
                          {isAwaitingTriage ? (
                            <span className="badge badge-lavender">⚠️ AWAITING ADMIN TRIAGE</span>
                          ) : (
                            <span className="badge badge-mint">ROUTED TO UNIT</span>
                          )}
                        </div>

                        <div style={{ fontSize: '1.0625rem', fontWeight: 800, marginTop: '0.25rem' }}>
                          {(alert.userName || (alert as any).user_name)
                            ? `${alert.userName || (alert as any).user_name} (${alert.userPhone || (alert as any).user_phone || 'No phone'})`
                            : 'Student (Campus Portal)'}
                        </div>

                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--text-secondary)', fontSize: '0.875rem', fontWeight: 600, marginTop: '0.25rem', flexWrap: 'wrap' }}>
                          <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                            <MapPin size={15} />
                            {alert.locationName || (alert as any).location_name}
                            {(alert.roomNumber || (alert as any).room_number) ? ` • Room ${alert.roomNumber || (alert as any).room_number}` : ''}
                          </span>
                          <span>•</span>
                          <span style={{ display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                            <Clock size={15} />
                            {alert.triggeredAt || (alert as any).triggered_at}
                          </span>
                        </div>

                        {details && (
                          <div style={{ fontSize: '0.8125rem', color: isCustom ? 'var(--cobalt)' : 'var(--coral)', fontWeight: 700, marginTop: '0.375rem', backgroundColor: 'var(--bg-secondary)', padding: '0.375rem 0.625rem', borderRadius: 'var(--radius-xs)' }}>
                            💬 Description: {details}
                          </div>
                        )}

                        {alert.attachments && alert.attachments.length > 0 && (
                          <div style={{ marginTop: '0.375rem', display: 'flex', alignItems: 'center', gap: '0.375rem', flexWrap: 'wrap' }}>
                            <span style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--cobalt)', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                              <Camera size={13} /> Photos ({alert.attachments.length}):
                            </span>
                            {alert.attachments.map((att, aIdx) => (
                              <button
                                key={att.id || aIdx}
                                type="button"
                                onClick={() => {
                                  setLightboxAttachment(att);
                                  setLightboxTitle(`SOS Alert #${alert.id} Evidence Photo`);
                                }}
                                style={{
                                  display: 'flex',
                                  alignItems: 'center',
                                  gap: '0.25rem',
                                  padding: '2px 8px',
                                  borderRadius: 'var(--radius-xs)',
                                  border: '1px solid #0F1115',
                                  backgroundColor: '#FFFFFF',
                                  cursor: 'pointer',
                                  fontSize: '0.75rem',
                                  fontWeight: 700,
                                }}
                              >
                                📷 View Photo {aIdx + 1}
                              </button>
                            ))}
                          </div>
                        )}

                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 600, marginTop: '0.375rem' }}>
                          <strong>Routing / Assignment:</strong>{' '}
                          {assignedName ? (
                            <span style={{ color: 'var(--mint)', fontWeight: 700 }}>
                              Assigned to {assignedName}
                            </span>
                          ) : isAwaitingTriage ? (
                            <span style={{ color: 'var(--lavender)', fontWeight: 700 }}>
                              Pending Admin manual triage &amp; dispatch
                            </span>
                          ) : (
                            <span>{routingDesc || 'Awaiting unit acknowledgement'}</span>
                          )}
                          {routingDesc && assignedName ? ` • ${routingDesc}` : ''}
                        </div>
                      </div>

                      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'center' }}>
                        {isAwaitingTriage && (
                          <button
                            type="button"
                            className="btn btn-primary btn-sm"
                            onClick={() => {
                              setSosTriageAlert(alert);
                              setTriageResponderId(responders[0]?.id || 0);
                            }}
                          >
                            Triage &amp; Assign Unit
                          </button>
                        )}
                        {alert.status === 'triggered' && (
                          <button
                            type="button"
                            className="btn btn-secondary btn-sm"
                            onClick={() => handleAcknowledgeSos(alert.id)}
                          >
                            Acknowledge SOS
                          </button>
                        )}
                        <button
                          type="button"
                          className="btn btn-coral btn-sm"
                          onClick={() => handleResolveSos(alert.id)}
                        >
                          <Check size={16} /> Mark Resolved
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </section>

          {/* Quick Recent Incidents Triage */}
          <section>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h2 style={{ fontSize: '1.25rem', fontWeight: 900 }}>Recent Incident Triage</h2>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => setActiveTab('incidents')}
              >
                View Full Directory ({incidents.length})
              </button>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.875rem' }}>
              {incidents.slice(0, 5).map((inc) => (
                <div
                  key={inc.id}
                  className="card"
                  style={{
                    backgroundColor: '#FFFFFF',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: '1rem',
                  }}
                >
                  <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                      <span className="badge badge-cobalt">{inc.category}</span>
                      <span className="badge badge-yellow">{inc.status}</span>
                      <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 800, fontSize: '0.875rem' }}>
                        {inc.incidentNumber}
                      </span>
                    </div>
                    <div style={{ fontSize: '1rem', fontWeight: 800 }}>{inc.title}</div>
                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.8125rem', fontWeight: 600 }}>
                      {inc.locationName} • Reported by: {inc.isAnonymous ? 'Anonymous' : (inc.reporterName ? `${inc.reporterName}${inc.reporterStudentId ? ` (${inc.reporterStudentId})` : ''}` : 'Student')}
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem' }}>
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleViewIncidentDetail(inc.id)}
                    >
                      Detail
                    </button>
                    <button
                      type="button"
                      className="btn btn-primary btn-sm"
                      onClick={() => setAssignModalIncident(inc)}
                    >
                      Assign
                    </button>
                    <button
                      type="button"
                      className="btn btn-black btn-sm"
                      onClick={() => setStatusUpdateIncident(inc)}
                    >
                      Status
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </section>
        </div>
      )}

      {/* TAB 2: INCIDENT DIRECTORY */}
      {activeTab === 'incidents' && (
        <section style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {/* Search & Filter Bar */}
          <div
            className="card"
            style={{
              padding: '1.25rem',
              backgroundColor: '#FFFFFF',
              display: 'flex',
              gap: '1rem',
              flexWrap: 'wrap',
              alignItems: 'center',
            }}
          >
            <div style={{ flex: '1', minWidth: '220px', position: 'relative' }}>
              <input
                type="text"
                className="form-input"
                placeholder="Search incident #, title, or location..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                style={{ paddingLeft: '2.25rem' }}
              />
              <Search size={16} style={{ position: 'absolute', left: '0.75rem', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
            </div>

            <select
              className="form-select"
              value={filterCategory}
              onChange={(e) => setFilterCategory(e.target.value)}
              style={{ width: 'auto' }}
            >
              <option value="all">All Categories</option>
              <option value="medical">Medical</option>
              <option value="security">Security</option>
              <option value="fire">Fire</option>
              <option value="maintenance">Maintenance</option>
              <option value="anti_ragging">Anti-Ragging</option>
              <option value="sexual_harassment">Confidential Support</option>
            </select>

            <select
              className="form-select"
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              style={{ width: 'auto' }}
            >
              <option value="all">All Statuses</option>
              <option value="reported">Reported</option>
              <option value="under_review">Under Review</option>
              <option value="dispatched">Dispatched</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>

          {/* Incidents Table / List */}
          {filteredIncidents.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '3rem', backgroundColor: '#FFFFFF' }}>
              <p style={{ fontWeight: 700, color: 'var(--text-secondary)' }}>No incidents match the active filters.</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {filteredIncidents.map((inc) => (
                <div
                  key={inc.id}
                  className="card"
                  style={{
                    backgroundColor: '#FFFFFF',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    gap: '1rem',
                  }}
                >
                  <div style={{ maxWidth: '650px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                      <span className="badge badge-cobalt">{inc.category}</span>
                      <span className="badge badge-mint">{inc.status}</span>
                      {inc.privacyLevel === 'confidential' && <span className="badge badge-lavender">CONFIDENTIAL</span>}
                      <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 900, fontSize: '0.875rem' }}>
                        {inc.incidentNumber}
                      </span>
                    </div>
                    <div style={{ fontSize: '1.0625rem', fontWeight: 800 }}>{inc.title}</div>
                    <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', margin: '0.25rem 0', fontWeight: 500 }}>
                      {inc.description}
                    </p>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', fontWeight: 600 }}>
                      📍 {inc.locationName} • 🕒 {inc.occurredAt} • Reporter: {inc.isAnonymous ? 'Anonymous' : (inc.reporterName ? `${inc.reporterName}${inc.reporterStudentId ? ` (${inc.reporterStudentId})` : ''}` : 'Registered Student')}
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleViewIncidentDetail(inc.id)}
                    >
                      View Details
                    </button>
                    <button
                      type="button"
                      className="btn btn-primary btn-sm"
                      onClick={() => setAssignModalIncident(inc)}
                    >
                      Assign Unit
                    </button>
                    <button
                      type="button"
                      className="btn btn-black btn-sm"
                      onClick={() => setStatusUpdateIncident(inc)}
                    >
                      Change Status
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* TAB 3: CANDIDATE DUPLICATE REVIEWS */}
      {activeTab === 'matches' && (
        <section style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
          <div>
            <h2 style={{ fontSize: '1.25rem', fontWeight: 900 }}>Candidate Similar Incident Matches</h2>
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', fontWeight: 600 }}>
              Deterministic temporal and geographic proximity engine identifies candidate duplicate reports. Requires explicit administrator verification.
            </p>
          </div>

          {candidateMatches.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '3rem', backgroundColor: '#FFFFFF' }}>
              <Check size={36} color="var(--mint)" style={{ margin: '0 auto 0.5rem auto' }} />
              <h3 style={{ fontSize: '1.125rem', fontWeight: 800 }}>No Duplicate Candidates Pending Review</h3>
              <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', fontWeight: 600 }}>
                All candidate similar incidents and SOS alerts have been reviewed and audited.
              </p>
            </div>
          ) : (
            candidateMatches.map((m) => {
              const scorePct = Math.round(m.similarity_score * 100);
              const isHighConfidence = m.similarity_score >= 0.8;
              const typeALabel = m.record_a_type === 'sos' ? 'EMERGENCY SOS' : 'INCIDENT REPORT';
              const typeBLabel = m.record_b_type === 'sos' ? 'EMERGENCY SOS' : 'INCIDENT REPORT';

              return (
                <div key={m.id} className="card" style={{ backgroundColor: '#FFFFFF', display: 'flex', flexDirection: 'column', gap: '1rem', borderLeft: isHighConfidence ? '8px solid var(--coral)' : '8px solid var(--mint)' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
                      <span className={`badge ${isHighConfidence ? 'badge-coral' : 'badge-yellow'}`}>
                        SIMILARITY: {scorePct}%
                      </span>
                      {isHighConfidence ? (
                        <span className="badge badge-coral">HIGH CONFIDENCE MATCH</span>
                      ) : (
                        <span className="badge badge-mint">POSSIBLE MATCH</span>
                      )}
                      <span style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                        Candidate #{m.id} • {m.rationale}
                      </span>
                    </div>

                    <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                      <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={() => setSelectedMatchDetail(m)}
                      >
                        <Eye size={15} /> View Details
                      </button>
                      <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={() => handleReviewMatch(m.id, 'dismiss')}
                      >
                        <X size={15} /> Dismiss
                      </button>
                      <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        onClick={() => {
                          setMergeConfirmMatch(m);
                          setMergeNotes('');
                        }}
                      >
                        <GitMerge size={15} /> Confirm Merge
                      </button>
                    </div>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem', backgroundColor: 'var(--bg-secondary)', padding: '1rem', borderRadius: 'var(--radius-sm)' }}>
                    <div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', marginBottom: '0.25rem' }}>
                        <span className={`badge ${m.record_a_type === 'sos' ? 'badge-coral' : 'badge-cobalt'}`}>{typeALabel}</span>
                        <span style={{ fontWeight: 800, color: 'var(--cobalt)', fontSize: '0.875rem' }}>{m.a_number}</span>
                      </div>
                      <div style={{ fontWeight: 800, fontSize: '0.9375rem' }}>{m.a_title}</div>
                      <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', marginTop: '0.125rem' }}>
                        📍 {m.a_location}{m.a_room ? ` • Room ${m.a_room}` : ''} ({m.a_category?.toUpperCase()})
                      </div>
                      {m.a_occurred_at && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.125rem' }}>
                          🕒 {m.a_occurred_at}
                        </div>
                      )}
                      {m.a_description && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '0.375rem', backgroundColor: '#FFFFFF', padding: '0.375rem 0.5rem', borderRadius: '4px', border: '1px solid var(--border-color)' }}>
                          {m.a_description.length > 120 ? m.a_description.substring(0, 120) + '...' : m.a_description}
                        </div>
                      )}
                    </div>

                    <div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', marginBottom: '0.25rem' }}>
                        <span className={`badge ${m.record_b_type === 'sos' ? 'badge-coral' : 'badge-yellow'}`}>{typeBLabel}</span>
                        <span style={{ fontWeight: 800, color: 'var(--text-secondary)', fontSize: '0.875rem' }}>{m.b_number}</span>
                      </div>
                      <div style={{ fontWeight: 800, fontSize: '0.9375rem' }}>{m.b_title}</div>
                      <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', marginTop: '0.125rem' }}>
                        📍 {m.b_location}{m.b_room ? ` • Room ${m.b_room}` : ''} ({m.b_category?.toUpperCase()})
                      </div>
                      {m.b_occurred_at && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.125rem' }}>
                          🕒 {m.b_occurred_at}
                        </div>
                      )}
                      {m.b_description && (
                        <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '0.375rem', backgroundColor: '#FFFFFF', padding: '0.375rem 0.5rem', borderRadius: '4px', border: '1px solid var(--border-color)' }}>
                          {m.b_description.length > 120 ? m.b_description.substring(0, 120) + '...' : m.b_description}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              );
            })
          )}
        </section>
      )}

      {/* TAB 4: AUDIT LOGS */}
      {activeTab === 'audit' && (
        <section style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <h2 style={{ fontSize: '1.25rem', fontWeight: 900 }}>Security &amp; Administrative Audit Trail</h2>
            <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', fontWeight: 600 }}>
              Immutable logging of dispatch actions, status transitions, and user management.
            </p>
          </div>

          <div className="card" style={{ backgroundColor: '#FFFFFF', padding: '0', overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.875rem' }}>
              <thead>
                <tr style={{ borderBottom: 'var(--border-black)', backgroundColor: 'var(--bg-secondary)' }}>
                  <th style={{ padding: '0.75rem 1rem', fontWeight: 800 }}>Timestamp</th>
                  <th style={{ padding: '0.75rem 1rem', fontWeight: 800 }}>Actor</th>
                  <th style={{ padding: '0.75rem 1rem', fontWeight: 800 }}>Action</th>
                  <th style={{ padding: '0.75rem 1rem', fontWeight: 800 }}>Resource</th>
                  <th style={{ padding: '0.75rem 1rem', fontWeight: 800 }}>IP Address</th>
                </tr>
              </thead>
              <tbody>
                {auditLogs.map((log) => (
                  <tr key={log.id} style={{ borderBottom: '1px solid var(--bg-subtle)' }}>
                    <td style={{ padding: '0.75rem 1rem', fontFamily: 'var(--font-mono)', fontSize: '0.75rem' }}>
                      {log.created_at}
                    </td>
                    <td style={{ padding: '0.75rem 1rem', fontWeight: 700 }}>
                      {log.actor_name || `User #${log.actor_user_id || 'System'}`} ({log.actor_role || 'system'})
                    </td>
                    <td style={{ padding: '0.75rem 1rem' }}>
                      <span className="badge badge-cobalt">{log.action}</span>
                    </td>
                    <td style={{ padding: '0.75rem 1rem', fontFamily: 'var(--font-mono)' }}>
                      {log.resource_type} #{log.resource_id}
                    </td>
                    <td style={{ padding: '0.75rem 1rem', color: 'var(--text-muted)' }}>
                      {log.ip_address}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {/* DETAIL MODAL */}
      {selectedIncident && (
        <div className="modal-overlay" onClick={() => setSelectedIncident(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '640px' }}>
            <div style={{ padding: '1.5rem', backgroundColor: 'var(--cobalt-card)', borderBottom: 'var(--border-black)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <span className="badge badge-cobalt">{selectedIncident.category}</span>
                  <h3 style={{ fontSize: '1.25rem', fontWeight: 900, marginTop: '0.25rem' }}>
                    {selectedIncident.incidentNumber || (selectedIncident as any).incident_number}
                  </h3>
                </div>
                <button type="button" onClick={() => setSelectedIncident(null)} style={{ background: 'none', border: 'none', cursor: 'pointer' }}>
                  <X size={20} />
                </button>
              </div>
            </div>

            <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem', maxHeight: '70vh', overflowY: 'auto' }}>
              <div>
                <h4 style={{ fontWeight: 800, fontSize: '1.125rem' }}>{selectedIncident.title}</h4>
                <p style={{ color: 'var(--text-secondary)', marginTop: '0.25rem', lineHeight: 1.5 }}>
                  {selectedIncident.description}
                </p>
              </div>

              {/* REPORTED BY SECTION */}
              {selectedIncident.isAnonymous || (selectedIncident as any).is_anonymous ? (
                <div
                  style={{
                    backgroundColor: 'var(--lavender-light)',
                    border: '2px solid #0F1115',
                    borderRadius: 'var(--radius-sm)',
                    padding: '0.875rem 1rem',
                    boxShadow: 'var(--shadow-sm)',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <EyeOff size={16} color="var(--lavender)" />
                      <span style={{ fontWeight: 800, fontSize: '0.8125rem', textTransform: 'uppercase', color: '#0F1115' }}>
                        Reported By
                      </span>
                    </div>
                    <span className="badge badge-lavender">ANONYMOUS REPORT</span>
                  </div>
                  <div style={{ fontSize: '1.0625rem', fontWeight: 900, color: '#0F1115' }}>
                    Anonymous Student
                  </div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginTop: '0.25rem' }}>
                    This incident was submitted anonymously. Student name, student ID, and personal contact details are withheld.
                  </div>
                </div>
              ) : (
                <div
                  style={{
                    backgroundColor: 'var(--mint-light)',
                    border: '2px solid #0F1115',
                    borderRadius: 'var(--radius-sm)',
                    padding: '0.875rem 1rem',
                    boxShadow: 'var(--shadow-sm)',
                  }}
                >
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.375rem' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                      <User size={16} color="var(--mint)" />
                      <span style={{ fontWeight: 800, fontSize: '0.8125rem', textTransform: 'uppercase', color: '#0F1115' }}>
                        Reported By
                      </span>
                    </div>
                    <span className="badge badge-mint">IDENTIFIED STUDENT</span>
                  </div>
                  <div style={{ fontSize: '1.0625rem', fontWeight: 900, color: '#0F1115', marginBottom: '0.375rem' }}>
                    {selectedIncident.reporterName || (selectedIncident as any).reporter_name || 'Registered Student'}
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '0.375rem', fontSize: '0.8125rem' }}>
                    <div>
                      <strong>Student ID:</strong> {selectedIncident.reporterStudentId || (selectedIncident as any).reporter_student_id || 'N/A'}
                    </div>
                    <div>
                      <strong>Email:</strong> {selectedIncident.reporterEmail || (selectedIncident as any).reporter_email || 'N/A'}
                    </div>
                    <div>
                      <strong>Phone:</strong> {selectedIncident.reporterPhone || (selectedIncident as any).reporter_phone || 'N/A'}
                    </div>
                  </div>
                </div>
              )}

              {/* INCIDENT CONTEXT */}
              <div style={{ backgroundColor: 'var(--bg-secondary)', border: '1px solid var(--bg-subtle)', padding: '0.875rem', borderRadius: 'var(--radius-sm)', fontSize: '0.8125rem' }}>
                <div><strong>Location:</strong> {selectedIncident.locationName || (selectedIncident as any).location_name}</div>
                <div><strong>Occurred:</strong> {selectedIncident.occurredAt || (selectedIncident as any).occurred_at}</div>
                <div><strong>Current Status:</strong> {selectedIncident.status}</div>
                <div><strong>Privacy:</strong> {selectedIncident.privacyLevel || (selectedIncident as any).privacy_level}</div>
              </div>

              {/* VISUAL EVIDENCE SECTION */}
              <div>
                <h5 style={{ fontWeight: 800, fontSize: '0.9375rem', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
                  <Camera size={16} color="var(--cobalt)" />
                  <span>Visual Evidence ({selectedIncident.attachments?.length || 0})</span>
                </h5>
                {selectedIncident.attachments && selectedIncident.attachments.length > 0 ? (
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '0.75rem' }}>
                    {selectedIncident.attachments.map((att, idx) => {
                      const imgUrl = att.url || att.filePath || att.file_path || '';
                      return (
                        <div
                          key={att.id || idx}
                          onClick={() => {
                            setLightboxAttachment(att);
                            setLightboxTitle(`${selectedIncident.incidentNumber || (selectedIncident as any).incident_number || 'Incident'} — Photo ${idx + 1}`);
                          }}
                          style={{
                            border: '2px solid #0F1115',
                            borderRadius: 'var(--radius-xs)',
                            overflow: 'hidden',
                            backgroundColor: '#FFFFFF',
                            cursor: 'pointer',
                            boxShadow: 'var(--shadow-neo-sm)',
                          }}
                          title="Click to enlarge"
                        >
                          <div style={{ height: '90px', overflow: 'hidden', backgroundColor: 'var(--bg-secondary)', position: 'relative' }}>
                            <img
                              src={imgUrl}
                              alt={att.fileName || `Photo ${idx + 1}`}
                              style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                            />
                            <span style={{ position: 'absolute', bottom: '4px', right: '4px', backgroundColor: 'rgba(0,0,0,0.7)', color: '#fff', fontSize: '0.625rem', padding: '1px 4px', borderRadius: '2px' }}>
                              Enlarge 🔍
                            </span>
                          </div>
                          <div style={{ padding: '0.25rem 0.5rem', fontSize: '0.6875rem', fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {att.fileName || att.file_name || `Photo ${idx + 1}`}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p style={{ fontSize: '0.8125rem', color: 'var(--text-muted)' }}>No visual evidence photographs attached to this report.</p>
                )}
              </div>

              {/* Assignments */}
              <div>
                <h5 style={{ fontWeight: 800, fontSize: '0.9375rem', marginBottom: '0.5rem' }}>Assigned Responders</h5>
                {selectedIncident.assignments && selectedIncident.assignments.length > 0 ? (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                    {selectedIncident.assignments.map((a) => (
                      <div key={a.id} style={{ padding: '0.5rem 0.75rem', border: 'var(--border-black-thin)', borderRadius: 'var(--radius-xs)', fontSize: '0.8125rem' }}>
                        <strong>{a.responder_name}</strong> ({a.category}) — Status: <span className="badge badge-mint">{a.assignment_status}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p style={{ fontSize: '0.8125rem', color: 'var(--text-muted)' }}>No responders assigned yet.</p>
                )}
              </div>

              {/* Updates Timeline */}
              <div>
                <h5 style={{ fontWeight: 800, fontSize: '0.9375rem', marginBottom: '0.5rem' }}>Activity History</h5>
                {selectedIncident.updates && selectedIncident.updates.length > 0 ? (
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                    {selectedIncident.updates.map((u) => {
                      const isAnon = selectedIncident.isAnonymous || (selectedIncident as any).is_anonymous;
                      const actorDisplay = isAnon && (u.actor_name === 'Anonymous' || u.actor_name === 'Anonymous Student' || !u.actor_name || u.actor_user_id === null)
                        ? 'Anonymous Student'
                        : (u.actor_name || 'Staff');
                      return (
                        <div key={u.id} style={{ padding: '0.5rem', backgroundColor: '#FFFFFF', border: '1px solid var(--bg-subtle)', borderRadius: 'var(--radius-xs)', fontSize: '0.8125rem' }}>
                          <div style={{ display: 'flex', justifyContent: 'space-between', color: 'var(--text-muted)', fontSize: '0.75rem' }}>
                            <span>{actorDisplay} ({u.new_status})</span>
                            <span>{u.created_at}</span>
                          </div>
                          <div style={{ marginTop: '0.25rem' }}>{u.update_text}</div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p style={{ fontSize: '0.8125rem', color: 'var(--text-muted)' }}>No historical updates recorded.</p>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ASSIGN MODAL */}
      {assignModalIncident && (
        <div className="modal-overlay" onClick={() => setAssignModalIncident(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div style={{ padding: '1.25rem', backgroundColor: 'var(--cobalt-card)', borderBottom: 'var(--border-black)' }}>
              <h3 style={{ fontWeight: 900, fontSize: '1.125rem' }}>
                Assign Responder — {assignModalIncident.incidentNumber}
              </h3>
            </div>
            <form onSubmit={handleAssignSubmit} style={{ padding: '1.5rem' }}>
              <div className="form-group">
                <label className="form-label">Select Authorized Responder *</label>
                <select
                  className="form-select"
                  value={selectedResponderId}
                  onChange={(e) => setSelectedResponderId(Number(e.target.value))}
                  required
                >
                  <option value="">-- Choose Unit --</option>
                  {responders.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.full_name} ({r.category.toUpperCase()} - {r.department}) [{r.status}]
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Dispatch Notes for Unit</label>
                <textarea
                  className="form-textarea"
                  rows={3}
                  placeholder="e.g. Please proceed to Central Library immediately with first-aid kit."
                  value={assignNotes}
                  onChange={(e) => setAssignNotes(e.target.value)}
                />
              </div>

              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.25rem' }}>
                <button type="button" className="btn btn-secondary" onClick={() => setAssignModalIncident(null)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary" style={{ flex: 1 }}>
                  Confirm Assignment
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* STATUS UPDATE MODAL */}
      {statusUpdateIncident && (
        <div className="modal-overlay" onClick={() => setStatusUpdateIncident(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div style={{ padding: '1.25rem', backgroundColor: 'var(--yellow-light)', borderBottom: 'var(--border-black)' }}>
              <h3 style={{ fontWeight: 900, fontSize: '1.125rem' }}>
                Update Status — {statusUpdateIncident.incidentNumber}
              </h3>
            </div>
            <form onSubmit={handleStatusUpdateSubmit} style={{ padding: '1.5rem' }}>
              <div className="form-group">
                <label className="form-label">New Status *</label>
                <select
                  className="form-select"
                  value={newStatus}
                  onChange={(e) => setNewStatus(e.target.value)}
                  required
                >
                  <option value="under_review">Under Review</option>
                  <option value="dispatched">Dispatched</option>
                  <option value="in_progress">In Progress</option>
                  <option value="resolved">Resolved</option>
                  <option value="closed">Closed</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Update Note (Chronological Record)</label>
                <textarea
                  className="form-textarea"
                  rows={3}
                  placeholder="e.g. Electrical team arrived and isolated breaker box 204."
                  value={statusNotes}
                  onChange={(e) => setStatusNotes(e.target.value)}
                  required
                />
              </div>

              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.25rem' }}>
                <button type="button" className="btn btn-secondary" onClick={() => setStatusUpdateIncident(null)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-black" style={{ flex: 1 }}>
                  Commit Status Transition
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* SOS TRIAGE & RESPONDER DISPATCH MODAL */}
      {sosTriageAlert && (
        <div className="modal-overlay" onClick={() => setSosTriageAlert(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div style={{ padding: '1.25rem', backgroundColor: 'var(--lavender-light)', borderBottom: 'var(--border-black)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <span className="badge badge-coral" style={{ marginBottom: '0.25rem', display: 'inline-block' }}>EMERGENCY SOS TRIAGE</span>
                  <h3 style={{ fontWeight: 900, fontSize: '1.125rem' }}>
                    Triage Alert #{sosTriageAlert.alertCode || sosTriageAlert.id}
                  </h3>
                </div>
                <button
                  type="button"
                  style={{ background: 'none', border: 'none', cursor: 'pointer', padding: '0.25rem' }}
                  onClick={() => setSosTriageAlert(null)}
                >
                  <X size={20} />
                </button>
              </div>
            </div>

            <form onSubmit={handleSosTriageSubmit} style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <div style={{ backgroundColor: 'var(--bg-secondary)', border: '1px solid var(--bg-subtle)', padding: '0.875rem', borderRadius: 'var(--radius-sm)', fontSize: '0.8125rem', display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
                <div><strong>Location:</strong> {sosTriageAlert.locationName || (sosTriageAlert as any).location_name} {(sosTriageAlert.roomNumber || (sosTriageAlert as any).room_number) ? `(Room ${sosTriageAlert.roomNumber || (sosTriageAlert as any).room_number})` : ''}</div>
                <div><strong>Emergency Type:</strong> {sosTriageAlert.emergencyType || (sosTriageAlert as any).emergency_type || 'Unspecified'}</div>
                {(sosTriageAlert.emergencyDetails || (sosTriageAlert as any).emergency_details) && (
                  <div style={{ backgroundColor: '#FFFFFF', padding: '0.5rem', border: '1px solid #DDD', borderRadius: '4px' }}>
                    <strong>Reporter Note / Details:</strong> {sosTriageAlert.emergencyDetails || (sosTriageAlert as any).emergency_details}
                  </div>
                )}
                {(sosTriageAlert.detectedCategory || (sosTriageAlert as any).detected_category) && (
                  <div><strong>AI Detected Category:</strong> <span className="badge badge-mint">{(sosTriageAlert.detectedCategory || (sosTriageAlert as any).detected_category).toUpperCase()}</span></div>
                )}
              </div>

              <div className="form-group">
                <label className="form-label">Assign to Designated Responder Unit *</label>
                <select
                  className="form-select"
                  value={triageResponderId}
                  onChange={(e) => setTriageResponderId(Number(e.target.value))}
                  required
                >
                  <option value="">-- Select Target Responder Unit --</option>
                  {responders.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.full_name} — {r.department} ({r.category.toUpperCase()}) [{r.status}]
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">Admin Triage / Dispatch Instructions</label>
                <textarea
                  className="form-textarea"
                  rows={3}
                  placeholder="e.g. Please respond immediately. Perimeter requires attention."
                  value={triageNotes}
                  onChange={(e) => setTriageNotes(e.target.value)}
                />
              </div>

              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '0.5rem' }}>
                <button type="button" className="btn btn-secondary" onClick={() => setSosTriageAlert(null)}>
                  Cancel
                </button>
                <button type="submit" className="btn btn-primary" style={{ flex: 1 }}>
                  Confirm Dispatch to Unit
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CANDIDATE MATCH DETAILS MODAL */}
      {selectedMatchDetail && (
        <div className="modal-overlay" onClick={() => setSelectedMatchDetail(null)}>
          <div className="modal-dialog" style={{ maxWidth: '850px' }} onClick={(e) => e.stopPropagation()}>
            <div style={{ padding: '1.25rem', backgroundColor: 'var(--bg-secondary)', borderBottom: 'var(--border-black)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <div>
                <span className="badge badge-yellow" style={{ marginBottom: '0.25rem', display: 'inline-block' }}>DUPLICATE CANDIDATE REVIEW</span>
                <h3 style={{ fontWeight: 900, fontSize: '1.25rem' }}>
                  Candidate #{selectedMatchDetail.id} — {Math.round(selectedMatchDetail.similarity_score * 100)}% Similarity
                </h3>
              </div>
              <button
                type="button"
                style={{ background: 'none', border: 'none', cursor: 'pointer', padding: '0.25rem' }}
                onClick={() => setSelectedMatchDetail(null)}
              >
                <X size={20} />
              </button>
            </div>

            <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1.25rem', maxHeight: '70vh', overflowY: 'auto' }}>
              {/* RATIONALE & FACTORS */}
              <div style={{ backgroundColor: '#FFFFFF', border: 'var(--border-black-thin)', borderRadius: 'var(--radius-sm)', padding: '1rem' }}>
                <div style={{ fontWeight: 800, fontSize: '0.9375rem', marginBottom: '0.375rem' }}>Match Rationale &amp; Confidence Breakdown</div>
                <p style={{ color: 'var(--text-secondary)', fontSize: '0.875rem', lineHeight: 1.5, margin: 0 }}>
                  {selectedMatchDetail.rationale}
                </p>

                {selectedMatchDetail.match_breakdown && (
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '0.5rem', marginTop: '0.75rem', textAlign: 'center' }}>
                    <div style={{ backgroundColor: 'var(--bg-secondary)', padding: '0.5rem', borderRadius: '4px' }}>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Category (25%)</div>
                      <div style={{ fontWeight: 900, fontSize: '1rem' }}>{Math.round((selectedMatchDetail.match_breakdown.category || 0) * 100)}%</div>
                    </div>
                    <div style={{ backgroundColor: 'var(--bg-secondary)', padding: '0.5rem', borderRadius: '4px' }}>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Location (35%)</div>
                      <div style={{ fontWeight: 900, fontSize: '1rem' }}>{Math.round((selectedMatchDetail.match_breakdown.location || 0) * 100)}%</div>
                    </div>
                    <div style={{ backgroundColor: 'var(--bg-secondary)', padding: '0.5rem', borderRadius: '4px' }}>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Temporal (20%)</div>
                      <div style={{ fontWeight: 900, fontSize: '1rem' }}>{Math.round((selectedMatchDetail.match_breakdown.temporal || 0) * 100)}%</div>
                    </div>
                    <div style={{ backgroundColor: 'var(--bg-secondary)', padding: '0.5rem', borderRadius: '4px' }}>
                      <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)' }}>Lexical (20%)</div>
                      <div style={{ fontWeight: 900, fontSize: '1rem' }}>{Math.round((selectedMatchDetail.match_breakdown.lexical || 0) * 100)}%</div>
                    </div>
                  </div>
                )}
              </div>

              {/* SIDE-BY-SIDE RECORD COMPARISON */}
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1rem' }}>
                {/* RECORD A */}
                <div style={{ border: '2px solid var(--cobalt)', borderRadius: 'var(--radius-sm)', padding: '1rem', backgroundColor: '#FFFFFF' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                    <span className="badge badge-cobalt">PRIMARY RECORD A ({selectedMatchDetail.record_a_type === 'sos' ? 'SOS' : 'INCIDENT'})</span>
                    <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 800 }}>{selectedMatchDetail.a_number}</span>
                  </div>
                  <h4 style={{ fontWeight: 800, fontSize: '1rem', marginBottom: '0.25rem' }}>{selectedMatchDetail.a_title}</h4>
                  <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', marginBottom: '0.5rem' }}>
                    <strong>Category:</strong> {selectedMatchDetail.a_category?.toUpperCase()} • <strong>Status:</strong> {selectedMatchDetail.a_status || 'Open'}
                  </div>
                  <div style={{ fontSize: '0.8125rem', marginBottom: '0.25rem' }}>
                    <strong>Location:</strong> {selectedMatchDetail.a_location} {selectedMatchDetail.a_room ? `(Room ${selectedMatchDetail.a_room})` : ''}
                  </div>
                  {selectedMatchDetail.a_occurred_at && (
                    <div style={{ fontSize: '0.8125rem', marginBottom: '0.5rem', color: 'var(--text-muted)' }}>
                      <strong>Time:</strong> {selectedMatchDetail.a_occurred_at}
                    </div>
                  )}
                  {selectedMatchDetail.a_description && (
                    <div style={{ fontSize: '0.8125rem', backgroundColor: 'var(--bg-secondary)', padding: '0.625rem', borderRadius: '4px', lineHeight: 1.4, border: '1px solid var(--border-color)' }}>
                      <strong>Description:</strong><br />
                      {selectedMatchDetail.a_description}
                    </div>
                  )}
                  {selectedMatchDetail.a_attachments && selectedMatchDetail.a_attachments.length > 0 && (
                    <div style={{ marginTop: '0.75rem', borderTop: '1px solid var(--border-color)', paddingTop: '0.5rem' }}>
                      <div style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--cobalt)', marginBottom: '0.375rem', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                        <Camera size={13} /> Record A Evidence Photos ({selectedMatchDetail.a_attachments.length}):
                      </div>
                      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                        {selectedMatchDetail.a_attachments.map((att, aIdx) => {
                          const imgUrl = att.url || att.filePath || att.file_path || '';
                          return (
                            <div
                              key={att.id || aIdx}
                              onClick={() => {
                                setLightboxAttachment(att);
                                setLightboxTitle(`Record A (${selectedMatchDetail.a_number}) Photo`);
                              }}
                              style={{ width: '60px', height: '60px', borderRadius: '4px', overflow: 'hidden', border: '1px solid #0F1115', cursor: 'pointer' }}
                              title="Click to enlarge"
                            >
                              <img src={imgUrl} alt={`A Photo ${aIdx + 1}`} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}
                </div>

                {/* RECORD B */}
                <div style={{ border: '2px solid var(--coral)', borderRadius: 'var(--radius-sm)', padding: '1rem', backgroundColor: '#FFFFFF' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                    <span className="badge badge-coral">CANDIDATE RECORD B ({selectedMatchDetail.record_b_type === 'sos' ? 'SOS' : 'INCIDENT'})</span>
                    <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 800 }}>{selectedMatchDetail.b_number}</span>
                  </div>
                  <h4 style={{ fontWeight: 800, fontSize: '1rem', marginBottom: '0.25rem' }}>{selectedMatchDetail.b_title}</h4>
                  <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', marginBottom: '0.5rem' }}>
                    <strong>Category:</strong> {selectedMatchDetail.b_category?.toUpperCase()} • <strong>Status:</strong> {selectedMatchDetail.b_status || 'Open'}
                  </div>
                  <div style={{ fontSize: '0.8125rem', marginBottom: '0.25rem' }}>
                    <strong>Location:</strong> {selectedMatchDetail.b_location} {selectedMatchDetail.b_room ? `(Room ${selectedMatchDetail.b_room})` : ''}
                  </div>
                  {selectedMatchDetail.b_occurred_at && (
                    <div style={{ fontSize: '0.8125rem', marginBottom: '0.5rem', color: 'var(--text-muted)' }}>
                      <strong>Time:</strong> {selectedMatchDetail.b_occurred_at}
                    </div>
                  )}
                  {selectedMatchDetail.b_description && (
                    <div style={{ fontSize: '0.8125rem', backgroundColor: 'var(--bg-secondary)', padding: '0.625rem', borderRadius: '4px', lineHeight: 1.4, border: '1px solid var(--border-color)' }}>
                      <strong>Description:</strong><br />
                      {selectedMatchDetail.b_description}
                    </div>
                  )}
                  {selectedMatchDetail.b_attachments && selectedMatchDetail.b_attachments.length > 0 && (
                    <div style={{ marginTop: '0.75rem', borderTop: '1px solid var(--border-color)', paddingTop: '0.5rem' }}>
                      <div style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--coral)', marginBottom: '0.375rem', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                        <Camera size={13} /> Record B Evidence Photos ({selectedMatchDetail.b_attachments.length}):
                      </div>
                      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                        {selectedMatchDetail.b_attachments.map((att, bIdx) => {
                          const imgUrl = att.url || att.filePath || att.file_path || '';
                          return (
                            <div
                              key={att.id || bIdx}
                              onClick={() => {
                                setLightboxAttachment(att);
                                setLightboxTitle(`Record B (${selectedMatchDetail.b_number}) Photo`);
                              }}
                              style={{ width: '60px', height: '60px', borderRadius: '4px', overflow: 'hidden', border: '1px solid #0F1115', cursor: 'pointer' }}
                              title="Click to enlarge"
                            >
                              <img src={imgUrl} alt={`B Photo ${bIdx + 1}`} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div style={{ padding: '1.25rem', backgroundColor: 'var(--bg-secondary)', borderTop: 'var(--border-black)', display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', flexWrap: 'wrap' }}>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => setSelectedMatchDetail(null)}
              >
                Close
              </button>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => {
                  handleReviewMatch(selectedMatchDetail.id, 'dismiss');
                  setSelectedMatchDetail(null);
                }}
              >
                <X size={16} /> Dismiss Candidate
              </button>
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => {
                  setMergeConfirmMatch(selectedMatchDetail);
                  setMergeNotes('');
                  setSelectedMatchDetail(null);
                }}
              >
                <GitMerge size={16} /> Proceed to Merge
              </button>
            </div>
          </div>
        </div>
      )}

      {/* CONFIRM MERGE MODAL */}
      {mergeConfirmMatch && (
        <div className="modal-overlay" onClick={() => !isSubmittingMerge && setMergeConfirmMatch(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div style={{ padding: '1.25rem', backgroundColor: 'var(--mint-light)', borderBottom: 'var(--border-black)' }}>
              <h3 style={{ fontWeight: 900, fontSize: '1.125rem' }}>
                Confirm Duplicate Merge — Candidate #{mergeConfirmMatch.id}
              </h3>
            </div>

            <form onSubmit={handleConfirmMergeSubmit} style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <div style={{ backgroundColor: 'var(--bg-secondary)', padding: '0.875rem', borderRadius: 'var(--radius-sm)', fontSize: '0.8125rem', lineHeight: 1.5, border: '1px solid var(--bg-subtle)' }}>
                <strong>Merge Summary:</strong>
                <ul style={{ margin: '0.5rem 0 0 1rem', padding: 0 }}>
                  <li>Primary Record: <strong>{mergeConfirmMatch.a_number}</strong> ({mergeConfirmMatch.a_title})</li>
                  <li>Duplicate Record to Merge: <strong>{mergeConfirmMatch.b_number}</strong> ({mergeConfirmMatch.b_title})</li>
                  <li>Original records and complete audit history will be permanently preserved.</li>
                  <li>Active Emergency SOS dispatches and field responder assignments remain uninterrupted.</li>
                </ul>
              </div>

              <div className="form-group">
                <label className="form-label">Administrator Merge Notes (Optional)</label>
                <textarea
                  className="form-textarea"
                  rows={3}
                  placeholder="e.g. Verified identical incident reported by multiple bystanders. Consolidating into primary investigation case."
                  value={mergeNotes}
                  onChange={(e) => setMergeNotes(e.target.value)}
                  disabled={isSubmittingMerge}
                />
              </div>

              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '0.5rem' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => setMergeConfirmMatch(null)}
                  disabled={isSubmittingMerge}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="btn btn-primary"
                  style={{ flex: 1 }}
                  disabled={isSubmittingMerge}
                >
                  {isSubmittingMerge ? 'Committing Merge...' : 'Confirm & Commit Merge'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Lightbox Enlargement Modal for Evidence Photos */}
      <ImageLightboxModal
        attachment={lightboxAttachment}
        onClose={() => setLightboxAttachment(null)}
        title={lightboxTitle}
      />
    </div>
  );
};
