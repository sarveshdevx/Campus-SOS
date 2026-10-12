import React, { useState, useEffect } from 'react';
import {
  Phone,
  Mail,
  MapPin,
  Radio,
  RefreshCw,
  CheckCircle,
  User,
  EyeOff,
  Camera,
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { adminService, responderPortalService, ResponderUser } from '../services/adminService';
import { incidentService, IncidentDetail } from '../services/incidentService';
import { sosService } from '../services/sosService';
import { contactService, DepartmentContact } from '../services/contactService';
import { SosAlert } from '../types/sos';
import { IncidentAttachment } from '../types/incident';
import { ImageLightboxModal } from '../components/common/ImageLightboxModal';

interface ResolveTarget {
  type: 'sos' | 'incident';
  id: number;
  titleOrIdentifier: string;
  category?: string;
  currentStatus: string;
}

export const ResponderDashboardPage: React.FC = () => {
  const { user, isAuthenticated } = useAuth();

  const [roster, setRosters] = useState<ResponderUser[]>([]);
  const [assignedIncidents, setAssignedIncidents] = useState<any[]>([]);
  const [departmentIncidents, setDepartmentIncidents] = useState<any[]>([]);
  const [departmentContacts, setDepartmentContacts] = useState<DepartmentContact[]>([]);
  const [activeSosAlerts, setActiveSosAlerts] = useState<SosAlert[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedIncident, setSelectedIncident] = useState<IncidentDetail | null>(null);

  // Status toggle & actions
  const [currentDutyStatus, setCurrentDutyStatus] = useState<'available' | 'offline'>('available');
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  // Resolution modal state
  const [resolveTarget, setResolveTarget] = useState<ResolveTarget | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState('');
  const [isSubmittingResolve, setIsSubmittingResolve] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);

  // Lightbox modal state
  const [lightboxAttachment, setLightboxAttachment] = useState<IncidentAttachment | null>(null);
  const [lightboxTitle, setLightboxTitle] = useState<string>('Visual Evidence');

  const fetchResponderData = async () => {
    setIsLoading(true);
    try {
      const [resps, myIncs, activeSos, deptIncs, contacts] = await Promise.all([
        adminService.getResponders().catch(() => []),
        responderPortalService.getMyAssignments().catch(() => []),
        sosService.getActiveAlerts().catch(() => []),
        incidentService.getIncidents().catch(() => []),
        contactService.getContacts().catch(() => []),
      ]);

      setRosters(resps);
      setAssignedIncidents(myIncs.filter((a: any) => a.incident_status !== 'resolved' && a.assignment_status !== 'completed'));
      setActiveSosAlerts(activeSos);
      setDepartmentIncidents(deptIncs.filter((inc: any) => inc.status !== 'resolved' && inc.status !== 'closed' && inc.status !== 'rejected'));
      setDepartmentContacts(contacts);

      // If user is responder, update on-duty state
      if (user?.role === 'responder' && user.responderProfile) {
        setCurrentDutyStatus(user.responderProfile.status === 'available' ? 'available' : 'offline');
      }
    } catch {
      // Handled gracefully
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchResponderData();
    const interval = setInterval(fetchResponderData, 3000);
    return () => clearInterval(interval);
  }, [user]);

  const handleDutyStatusChange = async (newStatus: 'available' | 'offline') => {
    try {
      await responderPortalService.updateDutyStatus(newStatus);
      setCurrentDutyStatus(newStatus);
      setActionSuccess(`On-duty status updated to ${newStatus.toUpperCase()}.`);
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to update status.');
    }
  };

  const handleAcknowledgeIncident = async (incidentId: number) => {
    try {
      await incidentService.acknowledgeAssignment(incidentId);
      setActionSuccess(`Assignment for incident #${incidentId} acknowledged.`);
      fetchResponderData();
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to acknowledge assignment.');
    }
  };

  const handleViewDetail = async (id: number) => {
    try {
      const detail = await incidentService.getIncidentById(id);
      setSelectedIncident(detail);
    } catch (err: unknown) {
      setActionError(err instanceof Error ? err.message : 'Failed to load details.');
    }
  };

  const handleOpenResolveModal = (target: ResolveTarget) => {
    setResolveTarget(target);
    setResolutionNotes('');
    setModalError(null);
  };

  const handleConfirmResolve = async () => {
    if (!resolveTarget) return;
    setIsSubmittingResolve(true);
    setModalError(null);

    const note = resolutionNotes.trim() || (resolveTarget.type === 'sos' ? 'Resolved by on-duty campus safety team.' : 'Marked as resolved by responder.');

    try {
      if (resolveTarget.type === 'sos') {
        await sosService.resolveSos(resolveTarget.id, note);
        // Optimistically update activeSosAlerts list
        setActiveSosAlerts((prev) => prev.filter((a) => a.id !== resolveTarget.id));
        setActionSuccess(`Emergency SOS #${resolveTarget.id} successfully marked as resolved.`);
      } else {
        await incidentService.updateStatus(resolveTarget.id, 'resolved', note);
        // Optimistically remove resolved incident immediately from active lists
        setDepartmentIncidents((prev) => prev.filter((inc) => inc.id !== resolveTarget.id));
        setAssignedIncidents((prev) => prev.filter((a) => a.incident_id !== resolveTarget.id));
        if (selectedIncident && selectedIncident.id === resolveTarget.id) {
          setSelectedIncident(null);
        }
        setActionSuccess(`Incident #${resolveTarget.titleOrIdentifier} marked as resolved.`);
      }
      setResolveTarget(null);
      fetchResponderData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to resolve item.';
      setModalError(msg);
      setActionError(msg);
    } finally {
      setIsSubmittingResolve(false);
    }
  };

  const isResponder = isAuthenticated && user?.role === 'responder';
  const responderProfile = user?.responderProfile;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
            <span className="badge badge-mint">RESPONDER PORTAL</span>
            <span style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 700 }}>
              Campus Safety Operations
            </span>
          </div>
          <h1 style={{ fontSize: '2.25rem', fontWeight: 900, letterSpacing: '-0.03em' }}>
            Authorized Responders Console
          </h1>
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.9375rem', fontWeight: 600 }}>
            Active duty roster, assigned emergency calls, and unit status management across 6 safety categories.
          </p>
        </div>

        <button
          type="button"
          className="btn btn-secondary btn-sm"
          onClick={fetchResponderData}
          disabled={isLoading}
        >
          <RefreshCw size={15} className={isLoading ? 'animate-spin' : ''} />
          <span>Refresh Roster</span>
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
          }}
        >
          ✓ {actionSuccess}
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
          }}
        >
          ⚠️ {actionError}
        </div>
      )}

      {/* Responder Specific Duty Status Card if Signed In */}
      {isResponder && responderProfile && (
        <div
          className="card"
          style={{
            backgroundColor: 'var(--cobalt-card)',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            flexWrap: 'wrap',
            gap: '1rem',
          }}
        >
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
              <span className="badge badge-cobalt">{responderProfile.category.toUpperCase()} UNIT</span>
              <span style={{ fontWeight: 800, fontSize: '1.125rem' }}>{user.fullName}</span>
            </div>
            <div style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
              Department: {responderProfile.department} • Phone: {responderProfile.contactPhone}
            </div>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <span style={{ fontSize: '0.875rem', fontWeight: 800 }}>Duty Status:</span>
            <div style={{ display: 'flex', gap: '0.375rem' }}>
              {(['available', 'offline'] as const).map((st) => (
                <button
                  key={st}
                  type="button"
                  className={`btn btn-sm ${currentDutyStatus === st ? 'btn-black' : 'btn-secondary'}`}
                  onClick={() => handleDutyStatusChange(st)}
                >
                  {st.toUpperCase()}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Active Urgent SOS Calls Bar */}
      {activeSosAlerts.length > 0 && (
        <section
          style={{
            backgroundColor: 'var(--coral-light)',
            border: 'var(--border-black-thick)',
            boxShadow: 'var(--shadow-neo-md)',
            borderRadius: 'var(--radius-md)',
            padding: '1.25rem 1.5rem',
            display: 'flex',
            flexDirection: 'column',
            gap: '1rem',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.75rem' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <Radio size={24} color="var(--coral)" className="animate-pulse" />
              <div>
                <div style={{ fontWeight: 900, fontSize: '1.125rem', color: '#0F1115' }}>
                  {activeSosAlerts.length} ACTIVE EMERGENCY SOS BROADCAST(S)
                </div>
                <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                  Immediate real-time campus safety dispatches requiring field resolution.
                </div>
              </div>
            </div>
            <span className="badge badge-coral">HIGH PRIORITY DISPATCH</span>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            {activeSosAlerts.map((sos: any) => (
              <div
                key={sos.id}
                className="card"
                style={{
                  backgroundColor: '#FFFFFF',
                  padding: '1rem 1.25rem',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  flexWrap: 'wrap',
                  gap: '1rem',
                  border: 'var(--border-black)',
                  boxShadow: 'var(--shadow-neo-sm)',
                }}
              >
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                    <span className="badge badge-coral">SOS #{sos.id}</span>
                    <span className="badge badge-yellow">{sos.status.toUpperCase()}</span>
                    {(sos.emergencyType || sos.emergency_type) && (
                      <span className="badge badge-cobalt">{(sos.emergencyType || sos.emergency_type).toUpperCase()}</span>
                    )}
                  </div>
                  <div style={{ fontWeight: 800, fontSize: '1.0625rem' }}>
                    📍 {sos.locationName || sos.location_name}{sos.roomNumber || sos.room_number ? ` • Room ${sos.roomNumber || sos.room_number}` : ''}
                  </div>
                  {(sos.emergencyDetails || sos.emergency_details) && (
                    <div style={{ fontSize: '0.8125rem', color: 'var(--coral)', fontWeight: 700, marginTop: '0.2rem' }}>
                      ⚠️ Details: {sos.emergencyDetails || sos.emergency_details}
                    </div>
                  )}
                  <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600, marginTop: '0.125rem' }}>
                    Triggered by: {sos.userName || sos.user_name || 'Campus Member'} • {sos.triggeredAt || sos.triggered_at}
                  </div>

                  {sos.attachments && sos.attachments.length > 0 && (
                    <div style={{ marginTop: '0.375rem', display: 'flex', alignItems: 'center', gap: '0.375rem', flexWrap: 'wrap' }}>
                      <span style={{ fontSize: '0.75rem', fontWeight: 800, color: 'var(--cobalt)', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                        <Camera size={13} /> Photos ({sos.attachments.length}):
                      </span>
                      {sos.attachments.map((att: any, aIdx: number) => (
                        <button
                          key={att.id || aIdx}
                          type="button"
                          onClick={() => {
                            setLightboxAttachment(att);
                            setLightboxTitle(`SOS Alert #${sos.id} Evidence Photo`);
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
                </div>

                <div style={{ display: 'flex', gap: '0.5rem' }}>
                  {(isResponder || user?.role === 'admin') && (
                    <button
                      type="button"
                      className="btn btn-mint btn-sm"
                      onClick={() =>
                        handleOpenResolveModal({
                          type: 'sos',
                          id: sos.id,
                          titleOrIdentifier: `SOS #${sos.id} (${sos.locationName || sos.location_name})`,
                          currentStatus: sos.status,
                        })
                      }
                    >
                      <CheckCircle size={14} />
                      <span>Resolve SOS</span>
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* My Assigned Incidents (If signed in as responder) */}
      {isResponder && (
        <section>
          <h2 style={{ fontSize: '1.35rem', fontWeight: 900, marginBottom: '1rem' }}>
            My Assigned Incident Cases ({assignedIncidents.length})
          </h2>

          {assignedIncidents.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '2.5rem', backgroundColor: '#FFFFFF' }}>
              <p style={{ fontWeight: 700, color: 'var(--text-secondary)' }}>
                No active incident cases currently dispatched to your unit.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.875rem' }}>
              {assignedIncidents.map((a) => (
                <div
                  key={a.id}
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
                      <span className="badge badge-cobalt">{a.category}</span>
                      <span className="badge badge-yellow">{a.assignment_status}</span>
                      <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 800 }}>{a.incident_number}</span>
                    </div>
                    <div style={{ fontSize: '1.0625rem', fontWeight: 800 }}>{a.title}</div>
                    <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                      📍 {a.location_name} • Assigned: {a.assigned_at}
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleViewDetail(a.incident_id)}
                    >
                      Case Notes
                    </button>
                    {a.assignment_status === 'assigned' && (
                      <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        onClick={() => handleAcknowledgeIncident(a.incident_id)}
                      >
                        Acknowledge Call
                      </button>
                    )}
                    {a.incident_status !== 'resolved' && a.assignment_status !== 'completed' ? (
                      <button
                        type="button"
                        className="btn btn-mint btn-sm"
                        onClick={() =>
                          handleOpenResolveModal({
                            type: 'incident',
                            id: a.incident_id,
                            titleOrIdentifier: `${a.incident_number} - ${a.title}`,
                            category: a.category,
                            currentStatus: a.incident_status || a.assignment_status,
                          })
                        }
                      >
                        <CheckCircle size={14} />
                        <span>Mark as Resolved</span>
                      </button>
                    ) : (
                      <span className="badge badge-mint">RESOLVED</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Role-Specific Department Incident Stream */}
      {isResponder && responderProfile && (
        <section>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem', flexWrap: 'wrap', gap: '0.5rem' }}>
            <h2 style={{ fontSize: '1.35rem', fontWeight: 900 }}>
              {responderProfile.category.replace('_', ' ').toUpperCase()} Department Incident Queue ({departmentIncidents.length})
            </h2>
            <span className="badge badge-mint">{responderProfile.department}</span>
          </div>

          {departmentIncidents.length === 0 ? (
            <div className="card" style={{ textAlign: 'center', padding: '2rem', backgroundColor: '#FFFFFF' }}>
              <p style={{ fontWeight: 700, color: 'var(--text-secondary)' }}>
                No active incident reports currently logged under {responderProfile.department}.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.875rem' }}>
              {departmentIncidents.map((inc: any) => (
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
                      {inc.isConfidential && <span className="badge badge-purple">CONFIDENTIAL</span>}
                      <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 800 }}>
                        {inc.incidentNumber || inc.incident_number}
                      </span>
                    </div>
                    <div style={{ fontSize: '1.0625rem', fontWeight: 800 }}>{inc.title}</div>
                    <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                      📍 {inc.locationName || inc.location_name} {inc.roomNumber ? `• ${inc.roomNumber}` : ''} • Severity: {inc.severity}
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleViewDetail(inc.id)}
                    >
                      Case Notes
                    </button>
                    {inc.status !== 'resolved' ? (
                      <button
                        type="button"
                        className="btn btn-mint btn-sm"
                        onClick={() =>
                          handleOpenResolveModal({
                            type: 'incident',
                            id: inc.id,
                            titleOrIdentifier: `${inc.incidentNumber || inc.incident_number} - ${inc.title}`,
                            category: inc.category,
                            currentStatus: inc.status,
                          })
                        }
                      >
                        <CheckCircle size={14} />
                        <span>Mark as Resolved</span>
                      </button>
                    ) : (
                      <span className="badge badge-mint">RESOLVED</span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* 6 Category Responder Directory (Real Roster) */}
      <section>
        <h2 style={{ fontSize: '1.35rem', fontWeight: 900, marginBottom: '1rem' }}>
          Campus Safety Department Roster (6 Categories)
        </h2>

        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))',
            gap: '1.25rem',
          }}
        >
          {roster.map((r) => {
            const isAvail = r.status === 'available';
            return (
              <div
                key={r.id}
                className="card card-interactive"
                style={{
                  backgroundColor: '#FFFFFF',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '0.875rem',
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <div>
                    <span className="badge badge-cobalt" style={{ marginBottom: '0.375rem' }}>
                      {r.category.toUpperCase()}
                    </span>
                    <h3 style={{ fontSize: '1.125rem', fontWeight: 800 }}>{r.full_name}</h3>
                    <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                      {r.department}
                    </div>
                  </div>

                  <span className={`badge ${isAvail ? 'badge-mint' : 'badge-coral'}`}>
                    {isAvail ? 'AVAILABLE' : 'OFFLINE'}
                  </span>
                </div>

                {(() => {
                  const dc = departmentContacts.find((c) => c.category === r.category);
                  const phone = dc?.phoneNumber || r.contact_phone;
                  const email = dc?.email;
                  const location = dc?.officeLocation || r.current_location || 'Campus Center Patrol';

                  return (
                    <div style={{ fontSize: '0.8125rem', display: 'flex', flexDirection: 'column', gap: '0.375rem', borderTop: '1px solid var(--bg-subtle)', paddingTop: '0.75rem' }}>
                      {phone ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <Phone size={14} color="var(--cobalt)" />
                          <a
                            href={'tel:' + phone.replace(/[^0-9+]/g, '')}
                            style={{ fontWeight: 700, color: 'var(--cobalt)', textDecoration: 'none' }}
                          >
                            {phone}
                          </a>
                        </div>
                      ) : (
                        <span style={{ fontSize: '0.75rem', color: 'var(--coral)', fontWeight: 600 }}>
                          Phone: Contact details not configured
                        </span>
                      )}

                      {email && (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                          <Mail size={14} color="var(--cobalt)" />
                          <a
                            href={`mailto:${email}`}
                            style={{ fontWeight: 600, color: 'var(--text-primary)', textDecoration: 'none' }}
                          >
                            {email}
                          </a>
                        </div>
                      )}

                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', color: 'var(--text-secondary)' }}>
                        <MapPin size={14} />
                        <span>{location}</span>
                      </div>
                    </div>
                  );
                })()}
              </div>
            );
          })}
        </div>
      </section>

      {/* DETAIL MODAL */}
      {selectedIncident && (
        <div className="modal-overlay" onClick={() => setSelectedIncident(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '600px' }}>
            <div style={{ padding: '1.5rem', backgroundColor: 'var(--cobalt-card)', borderBottom: 'var(--border-black)' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <span className="badge badge-cobalt">{selectedIncident.category}</span>
                  <h3 style={{ fontSize: '1.25rem', fontWeight: 900, marginTop: '0.25rem' }}>
                    {selectedIncident.incidentNumber || (selectedIncident as any).incident_number}
                  </h3>
                </div>
                <button type="button" onClick={() => setSelectedIncident(null)} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: '1.25rem', fontWeight: 800 }}>
                  ✕
                </button>
              </div>
            </div>

            <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem', maxHeight: '70vh', overflowY: 'auto' }}>
              <div>
                <h4 style={{ fontWeight: 800, fontSize: '1.0625rem' }}>{selectedIncident.title}</h4>
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

              <div style={{ backgroundColor: 'var(--bg-secondary)', border: '1px solid var(--bg-subtle)', padding: '0.875rem', borderRadius: 'var(--radius-sm)', fontSize: '0.8125rem' }}>
                <div><strong>Location:</strong> {selectedIncident.locationName || (selectedIncident as any).location_name}</div>
                <div><strong>Occurred:</strong> {selectedIncident.occurredAt || (selectedIncident as any).occurred_at}</div>
                <div><strong>Status:</strong> {selectedIncident.status}</div>
              </div>

              {/* Visual Evidence Section */}
              <div>
                <h5 style={{ fontWeight: 800, fontSize: '0.9375rem', marginBottom: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
                  <Camera size={16} />
                  <span>Visual Evidence ({selectedIncident.attachments?.length || 0})</span>
                </h5>
                {selectedIncident.attachments && selectedIncident.attachments.length > 0 ? (
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '0.75rem' }}>
                    {selectedIncident.attachments.map((att, idx) => {
                      const imgUrl = att.url || att.filePath || (att as any).file_path || '';
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
                            {att.fileName || (att as any).file_name || `Photo ${idx + 1}`}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <p style={{ fontSize: '0.8125rem', color: 'var(--text-muted)' }}>No visual evidence photographs attached to this report.</p>
                )}
              </div>

              {/* Updates Timeline */}
              <div>
                <h5 style={{ fontWeight: 800, fontSize: '0.9375rem', marginBottom: '0.5rem' }}>Investigation Log</h5>
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

              {/* Action Button inside detail modal */}
              {(isResponder || user?.role === 'admin') && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', paddingTop: '0.5rem', borderTop: '1px solid var(--bg-subtle)' }}>
                  {selectedIncident.status !== 'resolved' ? (
                    <button
                      type="button"
                      className="btn btn-mint btn-sm"
                      onClick={() => {
                        const id = selectedIncident.id;
                        const title = selectedIncident.incidentNumber || (selectedIncident as any).incident_number;
                        handleOpenResolveModal({
                          type: 'incident',
                          id,
                          titleOrIdentifier: `${title} - ${selectedIncident.title}`,
                          category: selectedIncident.category,
                          currentStatus: selectedIncident.status,
                        });
                      }}
                    >
                      <CheckCircle size={14} />
                      <span>Mark as Resolved</span>
                    </button>
                  ) : (
                    <span className="badge badge-mint">CASE RESOLVED</span>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* RESOLUTION CONFIRMATION MODAL */}
      {resolveTarget && (
        <div className="modal-overlay" onClick={() => !isSubmittingResolve && setResolveTarget(null)}>
          <div
            className="modal-dialog"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '520px', border: 'var(--border-black-thick)', boxShadow: 'var(--shadow-neo-xl)' }}
          >
            <div
              style={{
                padding: '1.25rem 1.5rem',
                backgroundColor: 'var(--neon-lime-light)',
                borderBottom: 'var(--border-black)',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <CheckCircle size={20} color="var(--text-primary)" />
                <h3 style={{ fontSize: '1.25rem', fontWeight: 900 }}>
                  Confirm Resolution
                </h3>
              </div>
              <button
                type="button"
                onClick={() => !isSubmittingResolve && setResolveTarget(null)}
                disabled={isSubmittingResolve}
                style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: '1.25rem', fontWeight: 800 }}
              >
                ✕
              </button>
            </div>

            <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
              {modalError && (
                <div
                  style={{
                    backgroundColor: 'var(--coral-light)',
                    border: 'var(--border-black)',
                    color: 'var(--coral)',
                    padding: '0.75rem 1rem',
                    borderRadius: 'var(--radius-sm)',
                    fontWeight: 700,
                    fontSize: '0.8125rem',
                  }}
                >
                  ⚠️ {modalError}
                </div>
              )}

              <div
                style={{
                  backgroundColor: 'var(--bg-secondary)',
                  border: 'var(--border-black-thin)',
                  borderRadius: 'var(--radius-sm)',
                  padding: '1rem',
                  fontSize: '0.875rem',
                }}
              >
                <div style={{ fontWeight: 800, color: 'var(--text-primary)', marginBottom: '0.25rem' }}>
                  {resolveTarget.type === 'sos' ? '🚨 Emergency SOS Broadcast' : '📋 Incident Case'}
                </div>
                <div style={{ fontWeight: 900, fontSize: '1rem', marginBottom: '0.5rem' }}>
                  {resolveTarget.titleOrIdentifier}
                </div>
                <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', alignItems: 'center' }}>
                  <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', fontWeight: 700 }}>Current Status:</span>
                  <span className="badge badge-yellow">{resolveTarget.currentStatus}</span>
                  {resolveTarget.category && (
                    <span className="badge badge-cobalt">{resolveTarget.category}</span>
                  )}
                </div>
              </div>

              <div>
                <label
                  htmlFor="resolution-notes"
                  style={{ display: 'block', fontWeight: 800, fontSize: '0.875rem', marginBottom: '0.375rem' }}
                >
                  Resolution Notes & Actions Taken:
                </label>
                <textarea
                  id="resolution-notes"
                  rows={3}
                  value={resolutionNotes}
                  onChange={(e) => setResolutionNotes(e.target.value)}
                  placeholder={
                    resolveTarget.type === 'sos'
                      ? 'e.g., Responder arrived on site, medical aid rendered, zone cleared.'
                      : 'e.g., Facility repair completed, student confirmed issue resolved, case closed.'
                  }
                  disabled={isSubmittingResolve}
                  style={{
                    width: '100%',
                    padding: '0.75rem',
                    borderRadius: 'var(--radius-sm)',
                    border: 'var(--border-black)',
                    fontFamily: 'var(--font-sans)',
                    fontSize: '0.875rem',
                    boxShadow: 'var(--shadow-neo-sm)',
                    resize: 'vertical',
                  }}
                />
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem', marginTop: '0.5rem' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => setResolveTarget(null)}
                  disabled={isSubmittingResolve}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="btn btn-mint"
                  onClick={handleConfirmResolve}
                  disabled={isSubmittingResolve}
                >
                  {isSubmittingResolve ? (
                    <>
                      <RefreshCw size={14} className="animate-spin" />
                      <span>Resolving...</span>
                    </>
                  ) : (
                    <>
                      <CheckCircle size={16} />
                      <span>Confirm & Mark Resolved</span>
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* LIGHTBOX MODAL */}
      <ImageLightboxModal
        isOpen={!!lightboxAttachment}
        attachment={lightboxAttachment}
        onClose={() => setLightboxAttachment(null)}
        title={lightboxTitle}
      />
    </div>
  );
};
