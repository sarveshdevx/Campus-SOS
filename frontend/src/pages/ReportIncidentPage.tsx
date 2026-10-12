import React, { useState, useEffect } from 'react';
import {
  FileText,
  MapPin,
  Clock,
  Lock,
  CheckCircle,
  AlertCircle,
  Sparkles,
  User,
  EyeOff,
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import { incidentService } from '../services/incidentService';
import { IncidentCategory, PrivacyLevel } from '../types/incident';
import {
  GENERAL_CAMPUS_LOCATIONS,
  BUILDING_LOCATIONS,
  isBuildingLocation,
  formatFullLocation,
  validateRoomNumber,
} from '../constants/locations';
import { ImageUploadArea, UploadedImageItem } from '../components/common/ImageUploadArea';

interface ReportIncidentPageProps {
  onIncidentCreated?: (incidentId: number) => void;
  onNavigate: (tab: string, options?: { mode?: 'standard' | 'confidential' }) => void;
  initialMode?: 'standard' | 'confidential';
}

export const ReportIncidentPage: React.FC<ReportIncidentPageProps> = ({
  onIncidentCreated,
  onNavigate,
  initialMode = 'standard',
}) => {
  const { user } = useAuth();
  const [title, setTitle] = useState('');
  const [category, setCategory] = useState<IncidentCategory>(
    initialMode === 'confidential' ? 'sexual_harassment' : 'security'
  );
  const [description, setDescription] = useState('');
  const [locationName, setLocationName] = useState('Central Library');
  const [roomNumber, setRoomNumber] = useState('');
  const [occurredAt, setOccurredAt] = useState(() => new Date().toISOString().slice(0, 16));
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [privacyLevel, setPrivacyLevel] = useState<PrivacyLevel>(
    initialMode === 'confidential' ? 'confidential' : 'standard'
  );
  const [attachedImages, setAttachedImages] = useState<UploadedImageItem[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successIncident, setSuccessIncident] = useState<{
    id: number;
    incidentNumber: string;
    isAnonymous: boolean;
    photoCount: number;
    reporterName?: string;
    reporterStudentId?: string;
    reporterEmail?: string;
    reporterPhone?: string;
  } | null>(null);

  // Sync mode whenever initialMode prop updates
  useEffect(() => {
    if (initialMode === 'confidential') {
      setPrivacyLevel('confidential');
      setCategory('sexual_harassment');
    } else {
      setPrivacyLevel('standard');
      setCategory('security');
    }
  }, [initialMode]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);

    if (!title.trim() || !description.trim()) {
      setErrorMessage('Please provide both a title and a detailed description.');
      return;
    }

    if (!locationName) {
      setErrorMessage('Please select a valid campus location.');
      return;
    }

    if (isBuildingLocation(locationName)) {
      const roomCheck = validateRoomNumber(roomNumber);
      if (!roomCheck.valid) {
        setErrorMessage(roomCheck.error || 'Room number is required for building locations.');
        return;
      }
    }

    setIsSubmitting(true);

    try {
      const formattedLocation = formatFullLocation(locationName, roomNumber);
      const created = await incidentService.createIncident({
        title: title.trim(),
        category,
        description: description.trim(),
        locationName: formattedLocation,
        roomNumber: isBuildingLocation(locationName) ? roomNumber.trim() : undefined,
        occurredAt,
        isAnonymous,
        privacyLevel,
        reporterUserId: !isAnonymous && user ? user.id : undefined,
        images: attachedImages.length > 0 ? attachedImages.map((img) => ({ data: img.data, name: img.name })) : undefined,
      });

      setSuccessIncident({
        id: created.id,
        incidentNumber: created.incidentNumber,
        isAnonymous,
        photoCount: attachedImages.length,
        reporterName: isAnonymous ? 'Anonymous' : (created.reporterName || user?.fullName || 'Registered Student'),
        reporterStudentId: isAnonymous ? undefined : (created.reporterStudentId || user?.studentId),
        reporterEmail: isAnonymous ? undefined : (created.reporterEmail || user?.email),
        reporterPhone: isAnonymous ? undefined : (created.reporterPhone || user?.phoneNumber),
      });

      if (onIncidentCreated) {
        onIncidentCreated(created.id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to submit incident report.';
      setErrorMessage(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (successIncident) {
    return (
      <div className="card" style={{ maxWidth: '640px', margin: '2rem auto', textAlign: 'center', padding: '3rem 2rem' }}>
        <div
          style={{
            backgroundColor: 'var(--mint-light)',
            color: 'var(--mint)',
            width: '4rem',
            height: '4rem',
            borderRadius: 'var(--radius-full)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            margin: '0 auto 1.5rem auto',
          }}
        >
          <CheckCircle size={36} />
        </div>

        <span className="badge badge-mint" style={{ marginBottom: '0.75rem' }}>
          Report Submitted Successfully
        </span>
        <h2 style={{ fontSize: '1.75rem', fontWeight: 800, marginBottom: '0.5rem' }}>
          Incident Logged
        </h2>
        <p style={{ color: 'var(--text-secondary)', marginBottom: '1.5rem', fontSize: '0.9375rem' }}>
          Your report has been securely registered in the CampusSOS dispatch system.
        </p>

        {/* Detailed Submission Record Card */}
        <div
          style={{
            backgroundColor: 'var(--bg-secondary)',
            border: '2px solid #0F1115',
            borderRadius: 'var(--radius-md)',
            padding: '1.25rem',
            textAlign: 'left',
            marginBottom: '2rem',
            boxShadow: 'var(--shadow-sm)',
          }}
        >
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginBottom: '0.75rem',
              borderBottom: '1px solid var(--bg-subtle)',
              paddingBottom: '0.5rem',
            }}
          >
            <span style={{ fontWeight: 800, fontSize: '0.875rem', textTransform: 'uppercase', color: 'var(--text-secondary)' }}>
              Report Summary
            </span>
            {successIncident.isAnonymous ? (
              <span className="badge badge-lavender">ANONYMOUS</span>
            ) : (
              <span className="badge badge-mint">IDENTIFIED STUDENT</span>
            )}
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', fontSize: '0.875rem' }}>
            <div>
              <span style={{ color: 'var(--text-secondary)' }}>Incident ID:</span>{' '}
              <strong style={{ fontFamily: 'var(--font-mono)', color: 'var(--cobalt)' }}>
                {successIncident.incidentNumber}
              </strong>
            </div>

            {successIncident.isAnonymous ? (
              <div style={{ backgroundColor: '#FFFFFF', padding: '0.75rem', borderRadius: 'var(--radius-xs)', border: '1px solid var(--bg-subtle)' }}>
                <div style={{ fontWeight: 800, color: 'var(--text-primary)' }}>
                  Reported By: Anonymous Student
                </div>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.25rem' }}>
                  No name, student ID, or personal contact information is attached to this report.
                </div>
              </div>
            ) : (
              <div style={{ backgroundColor: '#FFFFFF', padding: '0.75rem', borderRadius: 'var(--radius-xs)', border: '1px solid var(--bg-subtle)' }}>
                <div style={{ fontWeight: 800, color: 'var(--text-primary)', marginBottom: '0.25rem' }}>
                  Reported By: {successIncident.reporterName}
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '0.375rem', fontSize: '0.8125rem' }}>
                  {successIncident.reporterStudentId && (
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Student ID:</span>{' '}
                      <strong>{successIncident.reporterStudentId}</strong>
                    </div>
                  )}
                  {successIncident.reporterEmail && (
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Email:</span>{' '}
                      <strong>{successIncident.reporterEmail}</strong>
                    </div>
                  )}
                  {successIncident.reporterPhone && (
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Phone:</span>{' '}
                      <strong>{successIncident.reporterPhone}</strong>
                    </div>
                  )}
                </div>
              </div>
            )}

            <div>
              <span style={{ color: 'var(--text-secondary)' }}>Visual Evidence:</span>{' '}
              <strong style={{ color: successIncident.photoCount > 0 ? 'var(--mint)' : 'var(--text-primary)' }}>
                {successIncident.photoCount > 0
                  ? `✓ ${successIncident.photoCount} incident photo(s) attached`
                  : 'None (Standard report without photos)'}
              </strong>
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'center' }}>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => {
              setSuccessIncident(null);
              setTitle('');
              setDescription('');
            }}
          >
            Submit Another Report
          </button>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => onNavigate('tracker')}
          >
            Track My Reports
          </button>
        </div>
      </div>
    );
  }

  return (
    <div style={{ maxWidth: '780px', margin: '0 auto' }}>
      <div style={{ marginBottom: '2rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem' }}>
          {privacyLevel === 'confidential' ? (
            <>
              <Lock size={20} color="var(--lavender)" />
              <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--lavender)' }}>
                Confidential Intake • Title IX &amp; Anti-Ragging
              </span>
            </>
          ) : (
            <>
              <FileText size={20} color="var(--cobalt)" />
              <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--cobalt)' }}>
                Safety Intake
              </span>
            </>
          )}
        </div>
        <h1 style={{ fontSize: '2rem', fontWeight: 800, letterSpacing: '-0.02em', marginBottom: '0.5rem' }}>
          {privacyLevel === 'confidential' ? 'File a Confidential Complaint' : 'File an Incident Report'}
        </h1>
        <p style={{ color: 'var(--text-secondary)', fontSize: '0.9375rem' }}>
          {privacyLevel === 'confidential'
            ? 'Restricted-access reporting for harassment, hazing, or sensitive campus concerns. Scoped strictly to verified support responders with complete privacy protection.'
            : 'Submit non-acute incidents and campus concerns. For immediate life-safety emergencies, use the floating SOS action.'}
        </p>
      </div>

      <form onSubmit={handleSubmit} className="card" style={{ padding: '2rem' }}>
        {privacyLevel === 'confidential' && (
          <div
            style={{
              backgroundColor: 'var(--lavender-light)',
              border: '2px solid #0F1115',
              padding: '1rem 1.25rem',
              borderRadius: 'var(--radius-md)',
              marginBottom: '1.5rem',
              display: 'flex',
              alignItems: 'flex-start',
              gap: '0.75rem',
              boxShadow: 'var(--shadow-sm)',
            }}
          >
            <Lock size={22} color="var(--lavender)" style={{ flexShrink: 0, marginTop: '0.125rem' }} />
            <div>
              <div style={{ fontWeight: 800, fontSize: '0.9375rem', color: '#0F1115' }}>
                Confidential Reporting Mode Active
              </div>
              <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', marginTop: '0.25rem', lineHeight: 1.4 }}>
                This submission receives confidential status. Automated duplicate matching and external transmission are strictly disabled, and the report is visible only to authorized internal support officers.
              </div>
            </div>
          </div>
        )}

        {errorMessage && (
          <div
            style={{
              backgroundColor: 'var(--coral-light)',
              border: '1px solid var(--coral-border)',
              color: 'var(--coral)',
              padding: '0.875rem',
              borderRadius: 'var(--radius-md)',
              fontSize: '0.875rem',
              marginBottom: '1.5rem',
              display: 'flex',
              alignItems: 'center',
              gap: '0.5rem',
            }}
          >
            <AlertCircle size={18} />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Incident Category */}
        <div className="form-group">
          <label htmlFor="inc-category" className="form-label">
            Incident Category *
          </label>
          <select
            id="inc-category"
            className="form-select"
            value={category}
            onChange={(e) => setCategory(e.target.value as IncidentCategory)}
            disabled={isSubmitting}
          >
            <option value="security">Campus Security (Theft, Intrusion, Suspicious Activity)</option>
            <option value="medical">Medical Assistance (Non-critical Injury, Illness, First-Aid)</option>
            <option value="fire">Fire & Hazard (Electrical Spills, Flammables, Blocked Exits)</option>
            <option value="maintenance">Facility Maintenance (Plumbing, Power Outage, Broken Fixture)</option>
            <option value="anti_ragging">Anti-Ragging Squad (Hostel Bullying, Hazing)</option>
            <option value="sexual_harassment">Confidential Support / Title IX</option>
            <option value="other">General Campus Concern</option>
          </select>
        </div>

        {/* Incident Title */}
        <div className="form-group">
          <label htmlFor="inc-title" className="form-label">
            Short Title / Summary *
          </label>
          <input
            id="inc-title"
            type="text"
            className="form-input"
            placeholder="e.g. Broken handrail on Academic Block B staircase"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            disabled={isSubmitting}
            required
          />
        </div>

        {/* Location & Time */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
          <div className="form-group">
            <label htmlFor="inc-location" className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
              <MapPin size={16} color="var(--cobalt)" />
              Campus Location *
            </label>
            <select
              id="inc-location"
              className="form-select"
              value={locationName}
              onChange={(e) => {
                const val = e.target.value;
                setLocationName(val);
                if (!isBuildingLocation(val)) {
                  setRoomNumber('');
                }
              }}
              disabled={isSubmitting}
              required
            >
              <optgroup label="General Campus Areas">
                {GENERAL_CAMPUS_LOCATIONS.map((loc) => (
                  <option key={loc} value={loc}>
                    {loc}
                  </option>
                ))}
              </optgroup>
              <optgroup label="Academic & Residential Buildings (Room Required)">
                {BUILDING_LOCATIONS.map((loc) => (
                  <option key={loc} value={loc}>
                    {loc}
                  </option>
                ))}
              </optgroup>
            </select>
          </div>

          <div className="form-group">
            <label htmlFor="inc-time" className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
              <Clock size={16} color="var(--cobalt)" />
              Date &amp; Time Observed
            </label>
            <input
              id="inc-time"
              type="datetime-local"
              className="form-input"
              value={occurredAt}
              onChange={(e) => setOccurredAt(e.target.value)}
              disabled={isSubmitting}
            />
          </div>
        </div>

        {/* Conditional Room Number for Buildings */}
        {isBuildingLocation(locationName) && (
          <div className="form-group" style={{ marginTop: '-0.25rem', marginBottom: '1.25rem' }}>
            <label htmlFor="inc-room" className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
              <span>Room Number *</span>
              <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-secondary)' }}>
                (Required for {locationName} — e.g. 101, A-204, CS-12)
              </span>
            </label>
            <input
              id="inc-room"
              type="text"
              className="form-input"
              placeholder="e.g. 101, A-204, CS-12, G1-05"
              value={roomNumber}
              onChange={(e) => setRoomNumber(e.target.value)}
              disabled={isSubmitting}
              required
            />
          </div>
        )}

        {/* Detailed Description */}
        <div className="form-group">
          <label htmlFor="inc-desc" className="form-label">
            Detailed Description *
          </label>
          <textarea
            id="inc-desc"
            className="form-textarea"
            rows={4}
            placeholder="Provide context, observations, individuals involved if safe to mention, and immediate hazards..."
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            disabled={isSubmitting}
            required
          />
        </div>

        {/* Visual Evidence (Optional Photos, Max 2) */}
        <ImageUploadArea
          maxImages={2}
          images={attachedImages}
          onChange={setAttachedImages}
          disabled={isSubmitting}
        />

        {/* Privacy & Anonymity Options */}
        <div
          style={{
            backgroundColor: 'var(--bg-secondary)',
            borderRadius: 'var(--radius-md)',
            padding: '1.25rem',
            margin: '1.25rem 0',
            display: 'flex',
            flexDirection: 'column',
            gap: '1rem',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <Lock size={18} color="var(--lavender)" />
            <h3 style={{ fontSize: '0.9375rem', fontWeight: 700 }}>
              Privacy &amp; Disclosure Controls
            </h3>
          </div>

          <label style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', cursor: 'pointer' }}>
            <input
              type="checkbox"
              checked={isAnonymous}
              onChange={(e) => setIsAnonymous(e.target.checked)}
              disabled={isSubmitting}
              style={{ width: '1.125rem', height: '1.125rem' }}
            />
            <span style={{ fontSize: '0.875rem', fontWeight: 600 }}>
              Submit Anonymously (Do not attach my name or student ID to this incident record)
            </span>
          </label>

          {/* Student Reporter Information Banner */}
          {!isAnonymous ? (
            <div
              style={{
                backgroundColor: '#FFFFFF',
                border: '2px solid #0F1115',
                borderRadius: 'var(--radius-sm)',
                padding: '1rem',
                boxShadow: 'var(--shadow-sm)',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <User size={18} color="var(--cobalt)" />
                  <span style={{ fontWeight: 800, fontSize: '0.875rem', textTransform: 'uppercase' }}>
                    Reporting Student Information
                  </span>
                </div>
                <span className="badge badge-mint">ATTACHED TO REPORT</span>
              </div>

              {user ? (
                <>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '0.5rem', fontSize: '0.8125rem' }}>
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Full Name:</span>{' '}
                      <strong>{user.fullName}</strong>
                    </div>
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Student ID:</span>{' '}
                      <strong>{user.studentId || 'N/A'}</strong>
                    </div>
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Email:</span>{' '}
                      <strong>{user.email}</strong>
                    </div>
                    <div>
                      <span style={{ color: 'var(--text-secondary)' }}>Phone:</span>{' '}
                      <strong>{user.phoneNumber || 'N/A'}</strong>
                    </div>
                  </div>
                  <p style={{ fontSize: '0.75rem', color: 'var(--text-muted)', marginTop: '0.5rem', marginBottom: 0 }}>
                    These verified student details will be attached to your report for safety dispatch and follow-up. Check <em>Submit Anonymously</em> above if you wish to withhold them.
                  </p>
                </>
              ) : (
                <div style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>
                  Submitting as unauthenticated guest. Sign in with student credentials to automatically attach your Student ID and contact records.
                </div>
              )}
            </div>
          ) : (
            <div
              style={{
                backgroundColor: 'var(--lavender-light)',
                border: '2px solid #0F1115',
                borderRadius: 'var(--radius-sm)',
                padding: '1rem',
                boxShadow: 'var(--shadow-sm)',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.25rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                  <EyeOff size={18} color="var(--lavender)" />
                  <span style={{ fontWeight: 800, fontSize: '0.875rem', textTransform: 'uppercase', color: '#0F1115' }}>
                    Anonymous Reporting Active
                  </span>
                </div>
                <span className="badge badge-lavender">IDENTITY PROTECTED</span>
              </div>
              <p style={{ fontSize: '0.8125rem', color: '#0F1115', margin: '0.25rem 0 0 0', lineHeight: 1.4 }}>
                Your student name, student ID, email, and phone number will <strong>not</strong> be linked to this incident. Responders and campus administrators will only see <em>Anonymous</em>.
              </p>
            </div>
          )}

          <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
            <span style={{ fontSize: '0.8125rem', fontWeight: 700, color: 'var(--text-secondary)' }}>
              Classification Level:
            </span>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', fontSize: '0.8125rem' }}>
              <input
                type="radio"
                name="privacy"
                value="standard"
                checked={privacyLevel === 'standard'}
                onChange={() => {
                  setPrivacyLevel('standard');
                  if (category === 'sexual_harassment') {
                    setCategory('security');
                  }
                }}
              />
              Standard
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', fontSize: '0.8125rem' }}>
              <input
                type="radio"
                name="privacy"
                value="confidential"
                checked={privacyLevel === 'confidential'}
                onChange={() => {
                  setPrivacyLevel('confidential');
                  if (category === 'security') {
                    setCategory('sexual_harassment');
                  }
                }}
              />
              Confidential (Restricted View)
            </label>
            <label style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', fontSize: '0.8125rem' }}>
              <input
                type="radio"
                name="privacy"
                value="sensitive"
                checked={privacyLevel === 'sensitive'}
                onChange={() => setPrivacyLevel('sensitive')}
              />
              Sensitive
            </label>
          </div>
        </div>

        {/* AI & Fallback Guarantee */}
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
            fontSize: '0.8125rem',
            color: 'var(--text-secondary)',
            marginBottom: '1.5rem',
          }}
        >
          <Sparkles size={16} color="var(--cobalt)" />
          <span>
            AI-assisted category verification active with deterministic rule-based fallback.
          </span>
        </div>

        {/* Submission Buttons */}
        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '1rem' }}>
          <button
            type="button"
            className="btn btn-secondary"
            onClick={() => onNavigate('home')}
            disabled={isSubmitting}
          >
            Cancel
          </button>
          <button
            type="submit"
            className="btn btn-primary btn-lg"
            disabled={isSubmitting}
          >
            {isSubmitting ? 'Registering Report...' : 'Submit Incident Report'}
          </button>
        </div>
      </form>
    </div>
  );
};

