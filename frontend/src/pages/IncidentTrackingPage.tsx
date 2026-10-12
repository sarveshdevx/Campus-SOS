import React, { useState, useEffect } from 'react';
import {
  Search,
  Filter,
  Clock,
  MapPin,
  CheckCircle2,
  Link as LinkIcon,
  Eye,
  RefreshCw,
  X,
} from 'lucide-react';
import { incidentService } from '../services/incidentService';
import { Incident } from '../types/incident';

interface IncidentTrackingPageProps {
  onNavigate: (tab: string) => void;
}

export const IncidentTrackingPage: React.FC<IncidentTrackingPageProps> = ({ onNavigate }) => {
  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedIncident, setSelectedIncident] = useState<Incident | null>(null);
  const [filterCategory, setFilterCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const loadIncidents = async () => {
    setIsLoading(true);
    setErrorMessage(null);
    try {
      const data = await incidentService.getIncidents({
        category: filterCategory !== 'all' ? filterCategory : undefined,
      });
      setIncidents(data);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to retrieve incidents.';
      setErrorMessage(msg);
      // Graceful fallback for expo showcase if backend is cold
      setIncidents([
        {
          id: 101,
          incidentNumber: 'INC-2026-0841',
          title: 'Damaged electrical conduit and flickering lights',
          category: 'maintenance',
          description: 'Sparks observed on floor 2 corridor ceiling conduit near lab 204.',
          locationName: 'CS Block — Room 204',
          status: 'in_progress',
          priority: 'high',
          isAnonymous: false,
          privacyLevel: 'standard',
          aiClassified: true,
          aiConfidence: 0.94,
          occurredAt: new Date(Date.now() - 3600000).toISOString(),
          createdAt: new Date(Date.now() - 3600000).toISOString(),
          updatedAt: new Date(Date.now() - 1800000).toISOString(),
        },
        {
          id: 102,
          incidentNumber: 'INC-2026-0839',
          title: 'Unattended suspicious backpack near south stairway',
          category: 'security',
          description: 'Large black rucksack left unattended for over 45 minutes.',
          locationName: 'Mechanical Block — Room 102',
          status: 'under_review',
          priority: 'medium',
          isAnonymous: true,
          privacyLevel: 'standard',
          aiClassified: false,
          occurredAt: new Date(Date.now() - 7200000).toISOString(),
          createdAt: new Date(Date.now() - 7200000).toISOString(),
          updatedAt: new Date(Date.now() - 7200000).toISOString(),
        },
        {
          id: 103,
          incidentNumber: 'INC-2026-0835',
          title: 'Student sprained ankle on wet basketball court',
          category: 'medical',
          description: 'First aid applied by peer, wheelchair assistance requested.',
          locationName: 'Basketball Court',
          status: 'resolved',
          priority: 'medium',
          isAnonymous: false,
          privacyLevel: 'standard',
          aiClassified: true,
          aiConfidence: 0.98,
          occurredAt: new Date(Date.now() - 18000000).toISOString(),
          createdAt: new Date(Date.now() - 18000000).toISOString(),
          updatedAt: new Date(Date.now() - 3600000).toISOString(),
        },
      ]);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadIncidents();
  }, [filterCategory]);

  const filteredList = incidents.filter((inc) => {
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchNum = inc.incidentNumber.toLowerCase().includes(q);
      const matchTitle = inc.title.toLowerCase().includes(q);
      const matchLoc = inc.locationName.toLowerCase().includes(q);
      if (!matchNum && !matchTitle && !matchLoc) return false;
    }
    return true;
  });

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'resolved':
      case 'closed':
        return <span className="badge badge-mint">{status.replace('_', ' ')}</span>;
      case 'in_progress':
      case 'dispatched':
        return <span className="badge badge-cobalt">{status.replace('_', ' ')}</span>;
      case 'under_review':
        return <span className="badge badge-yellow">{status.replace('_', ' ')}</span>;
      default:
        return <span className="badge badge-coral">{status.replace('_', ' ')}</span>;
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '2rem' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
            <Clock size={20} color="var(--mint)" />
            <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--mint)' }}>
              Live Incident Lifecycle
            </span>
          </div>
          <h1 style={{ fontSize: '2rem', fontWeight: 800, letterSpacing: '-0.02em' }}>
            Incident Tracker &amp; Audit Trail
          </h1>
          <p style={{ color: 'var(--text-secondary)', fontSize: '0.9375rem' }}>
            Track progress from initial logging to responder dispatch and verified resolution.
          </p>
        </div>

        {errorMessage && (
          <div style={{ width: '100%', backgroundColor: 'var(--yellow-light)', border: '1px solid var(--yellow-border)', color: 'var(--yellow)', padding: '0.75rem 1rem', borderRadius: 'var(--radius-md)', fontSize: '0.875rem' }}>
            {errorMessage} (Displaying demonstration incidents)
          </div>
        )}

        <div style={{ display: 'flex', gap: '0.75rem' }}>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={loadIncidents}
            disabled={isLoading}
          >
            <RefreshCw size={16} />
            Refresh
          </button>
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => onNavigate('report')}
          >
            + New Incident
          </button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div
        className="card"
        style={{
          padding: '1rem 1.25rem',
          display: 'flex',
          flexWrap: 'wrap',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '1rem',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', flex: '1 1 300px' }}>
          <Search size={18} color="var(--text-secondary)" />
          <input
            type="text"
            className="form-input"
            placeholder="Search by incident number, title, or location..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            style={{ padding: '0.5rem 0.75rem' }}
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
          <Filter size={18} color="var(--text-secondary)" />
          <select
            className="form-select"
            value={filterCategory}
            onChange={(e) => setFilterCategory(e.target.value)}
            style={{ padding: '0.5rem 0.75rem', width: 'auto' }}
          >
            <option value="all">All Categories</option>
            <option value="security">Security</option>
            <option value="medical">Medical</option>
            <option value="fire">Fire & Hazard</option>
            <option value="maintenance">Maintenance</option>
            <option value="anti_ragging">Anti-Ragging</option>
            <option value="sexual_harassment">Confidential / Title IX</option>
          </select>
        </div>
      </div>

      {/* Incidents Table / Card List */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
        {isLoading ? (
          <div className="card" style={{ textAlign: 'center', padding: '3rem', color: 'var(--text-secondary)' }}>
            Loading incidents...
          </div>
        ) : filteredList.length === 0 ? (
          <div className="card" style={{ textAlign: 'center', padding: '3rem', color: 'var(--text-secondary)' }}>
            No incidents found matching current filters.
          </div>
        ) : (
          filteredList.map((inc) => (
            <div
              key={inc.id}
              className="card"
              style={{
                display: 'flex',
                flexDirection: 'column',
                gap: '0.875rem',
                borderLeft: `4px solid ${
                  inc.priority === 'critical'
                    ? 'var(--coral)'
                    : inc.priority === 'high'
                    ? 'var(--yellow)'
                    : 'var(--cobalt)'
                }`,
              }}
            >
              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'flex-start',
                  flexWrap: 'wrap',
                  gap: '0.5rem',
                }}
              >
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem', marginBottom: '0.25rem' }}>
                    <span
                      style={{
                        fontFamily: 'var(--font-mono)',
                        fontSize: '0.875rem',
                        fontWeight: 700,
                        color: 'var(--cobalt)',
                      }}
                    >
                      {inc.incidentNumber}
                    </span>
                    {getStatusBadge(inc.status)}
                    <span className="badge badge-cobalt" style={{ textTransform: 'capitalize' }}>
                      {inc.category.replace('_', ' ')}
                    </span>
                    {inc.isAnonymous && (
                      <span className="badge badge-lavender">Anonymous Report</span>
                    )}
                  </div>
                  <h3 style={{ fontSize: '1.125rem', fontWeight: 700 }}>{inc.title}</h3>
                </div>

                <div style={{ textAlign: 'right', fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>
                  <div>Logged: {new Date(inc.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                  <div>Priority: <strong style={{ textTransform: 'uppercase' }}>{inc.priority}</strong></div>
                </div>
              </div>

              <p style={{ fontSize: '0.9375rem', color: 'var(--text-secondary)', lineHeight: 1.5 }}>
                {inc.description}
              </p>

              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  paddingTop: '0.5rem',
                  borderTop: '1px solid var(--bg-subtle)',
                  fontSize: '0.8125rem',
                  color: 'var(--text-secondary)',
                  flexWrap: 'wrap',
                  gap: '0.5rem',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
                  <MapPin size={15} color="var(--cobalt)" />
                  <span>{inc.locationName}</span>
                </div>

                {inc.aiClassified && (
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', color: 'var(--mint)' }}>
                    <CheckCircle2 size={15} />
                    <span>AI Verified Classification ({Math.round((inc.aiConfidence || 0.9) * 100)}%)</span>
                  </div>
                )}

                <button
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={() => setSelectedIncident(inc)}
                >
                  <Eye size={14} />
                  View Incident Record
                </button>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Similar Incident Matching & Human-in-the-Loop Info Card */}
      <div
        className="card"
        style={{
          backgroundColor: 'var(--bg-secondary)',
          border: '1px dashed var(--cobalt-border)',
          display: 'flex',
          alignItems: 'flex-start',
          gap: '1rem',
        }}
      >
        <LinkIcon size={24} color="var(--cobalt)" style={{ flexShrink: 0, marginTop: '0.25rem' }} />
        <div>
          <h3 style={{ fontSize: '1rem', fontWeight: 800, color: 'var(--cobalt)', marginBottom: '0.25rem' }}>
            Similar Incident Matching Engine (Human-in-the-Loop)
          </h3>
          <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', lineHeight: 1.5 }}>
            CampusSOS computes spatial proximity (within 500m), temporal proximity (within 30 mins),
            and lexical similarity across incident reports. To prevent inadvertent information leaks
            or erroneous merges, candidate duplicates are presented to authorized dispatchers for
            explicit human verification before merging.
          </p>
        </div>
      </div>

      {/* Selected Incident Details Modal */}
      {selectedIncident && (
        <div className="modal-overlay" onClick={() => setSelectedIncident(null)}>
          <div className="modal-dialog" onClick={(e) => e.stopPropagation()}>
            <div style={{ padding: '1.25rem 1.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid var(--bg-subtle)' }}>
              <div>
                <span className="badge badge-cobalt">{selectedIncident.incidentNumber}</span>
                <h3 style={{ fontSize: '1.25rem', fontWeight: 800, marginTop: '0.25rem' }}>{selectedIncident.title}</h3>
              </div>
              <button
                type="button"
                onClick={() => setSelectedIncident(null)}
                style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-secondary)' }}
              >
                <X size={20} />
              </button>
            </div>
            <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              <div>
                <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--text-secondary)', textTransform: 'uppercase' }}>Description</div>
                <p style={{ fontSize: '0.9375rem', marginTop: '0.25rem' }}>{selectedIncident.description}</p>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', fontSize: '0.875rem' }}>
                <div><strong>Location:</strong> {selectedIncident.locationName}</div>
                <div><strong>Category:</strong> {selectedIncident.category}</div>
                <div><strong>Status:</strong> {selectedIncident.status}</div>
                <div><strong>Privacy:</strong> {selectedIncident.privacyLevel}</div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

