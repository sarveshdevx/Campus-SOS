import React, { useState } from 'react';
import {
  FileText,
  Lock,
  ListFilter,
  ArrowRight,
  Siren,
  Sparkles,
  HeartPulse,
  Shield,
  Flame,
  Wrench,
  Users,
} from 'lucide-react';
import { sosService } from '../services/sosService';
import {
  GENERAL_CAMPUS_LOCATIONS,
  BUILDING_LOCATIONS,
  isBuildingLocation,
  formatFullLocation,
  validateRoomNumber,
} from '../constants/locations';
import { EMERGENCY_TYPES } from '../constants/emergencyTypes';

interface HomePageProps {
  onNavigate: (tab: string, options?: { mode?: 'standard' | 'confidential' }) => void;
  onSosTriggered?: (alertId: number) => void;
}

export const HomePage: React.FC<HomePageProps> = ({ onNavigate, onSosTriggered }) => {
  const [sosModalOpen, setSosModalOpen] = useState(false);
  const [isSubmittingSos, setIsSubmittingSos] = useState(false);
  const [sosLocation, setSosLocation] = useState('Central Library');
  const [sosRoomNumber, setSosRoomNumber] = useState('');
  const [emergencyType, setEmergencyType] = useState('');
  const [customDescription, setCustomDescription] = useState('');
  const [sosError, setSosError] = useState<string | null>(null);

  const handleEmergencyTypeChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    setEmergencyType(val);
    if (val !== 'Custom') {
      setCustomDescription('');
    }
  };

  const handleHeroSosSubmit = async () => {
    setSosError(null);

    if (!sosLocation) {
      setSosError('Please select a campus location.');
      return;
    }

    if (isBuildingLocation(sosLocation)) {
      const roomCheck = validateRoomNumber(sosRoomNumber);
      if (!roomCheck.valid) {
        setSosError(roomCheck.error || 'Room number is required for building locations.');
        return;
      }
    }

    if (emergencyType === 'Custom' && !customDescription.trim()) {
      setSosError('Please describe the emergency.');
      return;
    }

    setIsSubmittingSos(true);

    try {
      const formattedLocation = formatFullLocation(sosLocation, sosRoomNumber);
      const alert = await sosService.triggerSos({
        locationName: formattedLocation,
        roomNumber: isBuildingLocation(sosLocation) ? sosRoomNumber.trim() : undefined,
        emergencyType: emergencyType || undefined,
        emergencyDetails: emergencyType === 'Custom' ? customDescription.trim() : undefined,
        emergencyNotes: emergencyType === 'Custom' ? customDescription.trim() : (emergencyType || undefined),
      });

      setSosModalOpen(false);
      setSosRoomNumber('');
      setEmergencyType('');
      setCustomDescription('');
      if (onSosTriggered) {
        onSosTriggered(alert.id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to dispatch emergency SOS.';
      setSosError(msg);
    } finally {
      setIsSubmittingSos(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '3.5rem', position: 'relative' }}>
      {/* ============================================================== */}
      {/* HERO SECTION — EXACT RECREATION OF APPROVED REFERENCE IMAGE    */}
      {/* ============================================================== */}
      <section
        style={{
          position: 'relative',
          paddingTop: '2.5rem',
          paddingBottom: '3.5rem',
          minHeight: '620px',
        }}
      >
        {/* Floating Oval Stamp Left: SAFE STUDENTS STRONGER CAMPUSES */}
        <div
          className="sticker-oval"
          style={{
            position: 'absolute',
            top: '0rem',
            left: '3rem',
            transform: 'rotate(-10deg)',
            zIndex: 10,
            pointerEvents: 'none',
          }}
        >
          SAFE<br />STUDENTS<br />STRONGER<br />CAMPUSES
        </div>

        {/* Hand-drawn arrow from stamp to headline */}
        <svg
          style={{
            position: 'absolute',
            top: '6rem',
            left: '11rem',
            width: '45px',
            height: '35px',
            pointerEvents: 'none',
            zIndex: 10,
          }}
          viewBox="0 0 50 40"
          fill="none"
          stroke="#0F1115"
          strokeWidth="2.5"
          strokeLinecap="round"
        >
          <path d="M 5,5 Q 30,12 40,30 M 30,28 L 40,30 L 38,20" />
        </svg>

        {/* Floating Note Card Top-Right: Report. Get help. Make a difference. */}
        <div
          className="sticker-badge"
          style={{
            position: 'absolute',
            top: '0rem',
            right: '10rem',
            transform: 'rotate(4deg)',
            zIndex: 10,
            pointerEvents: 'none',
            padding: '0.75rem 1.25rem',
          }}
        >
          <div style={{ fontSize: '0.875rem', fontWeight: 800, lineHeight: 1.3 }}>
            Report.<br />Get help.<br />Make a difference.
          </div>
        </div>

        {/* Doodle radiating lines top right */}
        <svg
          style={{
            position: 'absolute',
            top: '-1rem',
            right: '18rem',
            width: '35px',
            height: '30px',
            pointerEvents: 'none',
          }}
          viewBox="0 0 40 30"
          stroke="#0F1115"
          strokeWidth="2.5"
          strokeLinecap="round"
        >
          <line x1="10" y1="20" x2="5" y2="5" />
          <line x1="25" y1="25" x2="28" y2="10" />
        </svg>

        {/* Left Cut-out: Campus Clock Tower Building with yellow circular backdrop */}
        <div
          style={{
            position: 'absolute',
            left: '-1.5rem',
            top: '7rem',
            width: '230px',
            zIndex: 2,
            pointerEvents: 'none',
          }}
          className="hero-building-cutout"
        >
          {/* Yellow half-sun / circle backdrop behind building */}
          <div
            style={{
              position: 'absolute',
              top: '4rem',
              left: '4rem',
              width: '150px',
              height: '150px',
              borderRadius: '50%',
              backgroundColor: 'var(--yellow)',
              zIndex: -1,
            }}
          />
          <img
            src="/campus-building.png"
            alt="Campus Architecture Landmark"
            onError={(e) => {
              (e.currentTarget as HTMLElement).style.display = 'none';
            }}
            style={{
              width: '100%',
              height: 'auto',
              display: 'block',
              filter: 'grayscale(100%) contrast(110%)',
              borderBottom: 'var(--border-black-thick)',
            }}
          />
        </div>

        {/* Right Cut-out: Happy Student with coral sunburst behind and purple starburst bottom */}
        <div
          style={{
            position: 'absolute',
            right: '-1.5rem',
            top: '1rem',
            width: '230px',
            zIndex: 2,
            pointerEvents: 'none',
          }}
          className="hero-student-cutout"
        >
          {/* Coral circle backdrop behind head */}
          <div
            style={{
              position: 'absolute',
              top: '0rem',
              right: '-1rem',
              width: '140px',
              height: '140px',
              borderRadius: '50%',
              backgroundColor: 'var(--coral)',
              zIndex: -1,
            }}
          />

          {/* Student photograph */}
          <img
            src="/student-hero.png"
            alt="Campus Student"
            onError={(e) => {
              (e.currentTarget as HTMLElement).style.display = 'none';
            }}
            style={{
              width: '100%',
              height: 'auto',
              display: 'block',
              filter: 'grayscale(100%) contrast(115%)',
            }}
          />

          {/* Purple multi-point starburst shape beneath student */}
          <div
            style={{
              position: 'absolute',
              bottom: '-3.5rem',
              right: '2rem',
              width: '110px',
              height: '110px',
              backgroundColor: 'var(--lavender)',
              clipPath:
                'polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%)',
              zIndex: 3,
            }}
          />

          {/* Hand-written text: YOUR SAFETY MATTERS */}
          <div
            style={{
              position: 'absolute',
              bottom: '4rem',
              left: '-5rem',
              transform: 'rotate(-8deg)',
              fontWeight: 900,
              fontSize: '0.8125rem',
              lineHeight: 1.1,
              letterSpacing: '0.05em',
              textAlign: 'center',
            }}
          >
            YOUR<br />SAFETY<br />MATTERS
            <svg
              style={{ width: '30px', height: '15px', marginTop: '2px' }}
              viewBox="0 0 30 15"
              fill="none"
              stroke="#0F1115"
              strokeWidth="2"
            >
              <path d="M 5,5 Q 15,14 25,5 M 20,4 L 25,5 L 23,10" />
            </svg>
          </div>
        </div>

        {/* Center Editorial Typography Headline */}
        <div
          style={{
            maxWidth: '820px',
            margin: '0 auto',
            textAlign: 'center',
            position: 'relative',
            zIndex: 5,
          }}
        >
          <h1
            style={{
              fontSize: 'clamp(2.75rem, 6.5vw, 5.25rem)',
              fontWeight: 900,
              lineHeight: 0.96,
              letterSpacing: '-0.04em',
              textTransform: 'uppercase',
              marginBottom: '1.25rem',
              fontFamily: 'var(--font-sans)',
            }}
          >
            <div>A SAFER</div>
            <div style={{ position: 'relative', display: 'inline-block', color: 'var(--cobalt)' }}>
              CAMPUS
              {/* Neon Lime accent rectangular tag next to CAMPUS */}
              <span
                style={{
                  display: 'inline-block',
                  backgroundColor: 'var(--neon-lime)',
                  border: 'var(--border-black)',
                  width: '2.5rem',
                  height: '1.1rem',
                  marginLeft: '0.5rem',
                  verticalAlign: 'middle',
                }}
              />
            </div>
            <div>TOGETHER.</div>
          </h1>

          <p
            style={{
              fontSize: 'clamp(1rem, 1.8vw, 1.25rem)',
              color: 'var(--text-secondary)',
              maxWidth: '560px',
              margin: '0 auto 2rem auto',
              lineHeight: 1.45,
              fontWeight: 600,
            }}
          >
            A smarter way to report incidents, get immediate help and build a safer,
            stronger campus for everyone.
          </p>

          {/* Hero Emergency SOS Button (Pill button with red coral & siren) */}
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.625rem' }}>
            <button
              type="button"
              className="hero-sos-pill"
              onClick={() => setSosModalOpen(true)}
              aria-label="Emergency SOS activation"
            >
              <Siren size={24} strokeWidth={2.5} />
              <span>EMERGENCY SOS</span>
              <ArrowRight size={22} strokeWidth={2.5} />
            </button>
            <span style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
              For immediate danger or urgent situations.
            </span>
          </div>
        </div>

        {/* ============================================================== */}
        {/* THREE HERO ACTION CARDS (Blue, Lime, Lavender)                 */}
        {/* ============================================================== */}
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
            gap: '1.5rem',
            marginTop: '3.5rem',
            position: 'relative',
            zIndex: 5,
          }}
        >
          {/* Card 1: Report an Incident (Pastel Blue) */}
          <div
            className="feature-trio-card card-blue"
            onClick={() => onNavigate('report', { mode: 'standard' })}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => e.key === 'Enter' && onNavigate('report', { mode: 'standard' })}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
              <div
                style={{
                  width: '2.75rem',
                  height: '2.75rem',
                  borderRadius: 'var(--radius-sm)',
                  backgroundColor: 'transparent',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  color: '#0F1115',
                }}
              >
                <FileText size={28} strokeWidth={2.2} />
              </div>
              <div>
                <h2 style={{ fontSize: '1.125rem', fontWeight: 900, marginBottom: '0.125rem' }}>
                  Report
                  <div style={{ fontSize: '0.9375rem', fontWeight: 800 }}>an Incident</div>
                </h2>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                  Tell us what happened.
                </div>
              </div>
            </div>
            <ArrowRight size={20} strokeWidth={2.5} />
          </div>

          {/* Card 2: Confidential Complaint (Pastel Lime) */}
          <div
            className="feature-trio-card card-lime"
            onClick={() => onNavigate('report', { mode: 'confidential' })}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => e.key === 'Enter' && onNavigate('report', { mode: 'confidential' })}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
              <div
                style={{
                  width: '2.75rem',
                  height: '2.75rem',
                  borderRadius: 'var(--radius-sm)',
                  backgroundColor: 'transparent',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  color: '#0F1115',
                }}
              >
                <Lock size={28} strokeWidth={2.2} />
              </div>
              <div>
                <h2 style={{ fontSize: '1.125rem', fontWeight: 900, marginBottom: '0.125rem' }}>
                  Confidential
                  <div style={{ fontSize: '0.9375rem', fontWeight: 800 }}>Complaint</div>
                </h2>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                  Share privately or anonymously.
                </div>
              </div>
            </div>
            <ArrowRight size={20} strokeWidth={2.5} />
          </div>

          {/* Card 3: Track Your Reports (Pastel Lavender) */}
          <div
            className="feature-trio-card card-lavender"
            onClick={() => onNavigate('tracker')}
            role="button"
            tabIndex={0}
            onKeyDown={(e) => e.key === 'Enter' && onNavigate('tracker')}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem' }}>
              <div
                style={{
                  width: '2.75rem',
                  height: '2.75rem',
                  borderRadius: 'var(--radius-sm)',
                  backgroundColor: 'transparent',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  color: '#0F1115',
                }}
              >
                <ListFilter size={28} strokeWidth={2.2} />
              </div>
              <div>
                <h2 style={{ fontSize: '1.125rem', fontWeight: 900, marginBottom: '0.125rem' }}>
                  Track
                  <div style={{ fontSize: '0.9375rem', fontWeight: 800 }}>Your Reports</div>
                </h2>
                <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                  Stay updated in real time.
                </div>
              </div>
            </div>
            <ArrowRight size={20} strokeWidth={2.5} />
          </div>
        </div>
      </section>

      {/* ============================================================== */}
      {/* LOWER EDITORIAL SECTION (Neon Lime Banner & Walking Students)  */}
      {/* ============================================================== */}
      <section
        style={{
          position: 'relative',
          backgroundColor: 'var(--neon-lime)',
          border: 'var(--border-black-thick)',
          borderRadius: 'var(--radius-lg)',
          boxShadow: 'var(--shadow-neo-lg)',
          overflow: 'hidden',
          padding: '2.5rem 2rem',
          minHeight: '260px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}
      >
        <div style={{ maxWidth: '420px', zIndex: 5 }}>
          <h2
            style={{
              fontSize: 'clamp(1.75rem, 3.5vw, 2.5rem)',
              fontWeight: 900,
              lineHeight: 1.05,
              textTransform: 'uppercase',
              letterSpacing: '-0.03em',
              marginBottom: '0.75rem',
            }}
          >
            STUDENTS.<br />
            COMMUNITY.<br />
            SAFETY. <ArrowRight size={26} strokeWidth={3} style={{ display: 'inline', verticalAlign: 'middle' }} />
          </h2>
          <p style={{ fontSize: '0.9375rem', fontWeight: 700, color: '#0F1115', lineHeight: 1.4 }}>
            Empowering university students, proctors, and safety staff with instant deterministic emergency response.
          </p>
        </div>

        {/* Decorative graphic shapes (Blue pill/circle and zigzag scribble) */}
        <div
          style={{
            position: 'absolute',
            bottom: '-2rem',
            left: '42%',
            width: '160px',
            height: '160px',
            borderRadius: '50%',
            backgroundColor: 'var(--cobalt)',
            border: 'var(--border-black-thick)',
            zIndex: 1,
          }}
        />

        <div
          style={{
            position: 'absolute',
            bottom: '-3rem',
            left: '52%',
            width: '100px',
            height: '180px',
            backgroundColor: 'var(--lavender)',
            border: 'var(--border-black-thick)',
            borderRadius: 'var(--radius-sm)',
            transform: 'rotate(15deg)',
            zIndex: 2,
          }}
        />

        {/* Cutout: Students walking with backpacks on bottom right */}
        <div
          style={{
            position: 'absolute',
            right: '0',
            bottom: '0',
            width: '320px',
            zIndex: 4,
            pointerEvents: 'none',
          }}
        >
          <img
            src="/students-walking.png"
            alt="Students on campus"
            onError={(e) => {
              (e.currentTarget as HTMLElement).style.display = 'none';
            }}
            style={{
              width: '100%',
              height: 'auto',
              display: 'block',
              filter: 'grayscale(100%) contrast(120%)',
            }}
          />
        </div>
      </section>

      {/* ============================================================== */}
      {/* 6 AUTHORIZED RESPONDER CATEGORIES (Consistent Neo-Brutalist)   */}
      {/* ============================================================== */}
      <section style={{ marginTop: '1rem' }}>
        <div style={{ marginBottom: '1.5rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
            <Sparkles size={18} color="var(--cobalt)" />
            <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase', color: 'var(--cobalt)' }}>
              Dedicated Campus Teams
            </span>
          </div>
          <h2 style={{ fontSize: '1.75rem', fontWeight: 900, letterSpacing: '-0.02em' }}>
            Authorized Safety Dispatch Categories
          </h2>
          <p style={{ fontSize: '0.9375rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
            Every incident report routes directly to verified campus responders.
          </p>
        </div>

        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
            gap: '1.25rem',
          }}
        >
          {[
            {
              id: 'medical',
              title: 'Medical Response',
              desc: 'First-aid, paramedic dispatch, medical transport, ambulance coordination.',
              icon: HeartPulse,
              bg: 'var(--coral-light)',
            },
            {
              id: 'security',
              title: 'Campus Security',
              desc: 'Patrols, gate control, unauthorized intrusion, theft, physical protection.',
              icon: Shield,
              bg: 'var(--cobalt-card)',
            },
            {
              id: 'fire',
              title: 'Fire & Safety',
              desc: 'Smoke detection, electrical sparks, lab chemical hazards, evacuation.',
              icon: Flame,
              bg: 'var(--yellow-light)',
            },
            {
              id: 'maintenance',
              title: 'Facility Maintenance',
              desc: 'Water leakages, power outages, broken locks, pathway hazards.',
              icon: Wrench,
              bg: 'var(--lime-card)',
            },
            {
              id: 'anti_ragging',
              title: 'Anti-Ragging Squad',
              desc: 'Immediate intervention against hazing, coercion, harassment in hostels.',
              icon: Users,
              bg: 'var(--lavender-card)',
            },
            {
              id: 'sexual_harassment',
              title: 'Confidential Support',
              desc: 'Zero-tolerance ICC / Title IX support with end-to-end anonymity.',
              icon: Lock,
              bg: 'var(--lavender-light)',
            },
          ].map((cat) => {
            const Icon = cat.icon;
            return (
              <div
                key={cat.id}
                className="card card-interactive"
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '0.75rem',
                  backgroundColor: '#FFFFFF',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                  <div
                    style={{
                      backgroundColor: cat.bg,
                      color: '#0F1115',
                      padding: '0.625rem',
                      borderRadius: 'var(--radius-sm)',
                      border: 'var(--border-black-thin)',
                      display: 'flex',
                    }}
                  >
                    <Icon size={20} />
                  </div>
                  <h3 style={{ fontSize: '1.0625rem', fontWeight: 800 }}>{cat.title}</h3>
                </div>
                <p style={{ fontSize: '0.875rem', color: 'var(--text-secondary)', lineHeight: 1.45, fontWeight: 500 }}>
                  {cat.desc}
                </p>
              </div>
            );
          })}
        </div>
      </section>

      {/* ============================================================== */}
      {/* HERO EMERGENCY SOS DISPATCH MODAL                              */}
      {/* ============================================================== */}
      {sosModalOpen && (
        <div
          className="modal-overlay"
          role="dialog"
          aria-modal="true"
          onClick={() => !isSubmittingSos && setSosModalOpen(false)}
        >
          <div
            className="modal-dialog"
            onClick={(e) => e.stopPropagation()}
            style={{ borderTop: '8px solid var(--coral)' }}
          >
            <div
              style={{
                padding: '1.5rem',
                backgroundColor: 'var(--coral-light)',
                borderBottom: 'var(--border-black)',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <div
                  style={{
                    backgroundColor: 'var(--coral)',
                    color: '#0F1115',
                    padding: '0.5rem',
                    borderRadius: 'var(--radius-sm)',
                    border: 'var(--border-black)',
                  }}
                >
                  <Siren size={24} />
                </div>
                <div>
                  <h2 style={{ fontSize: '1.25rem', fontWeight: 900, textTransform: 'uppercase' }}>
                    Confirm Emergency SOS
                  </h2>
                  <p style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                    Deterministic emergency dispatch to Campus Security &amp; Medical.
                  </p>
                </div>
              </div>
            </div>

            <div style={{ padding: '1.5rem' }}>
              {sosError && (
                <div
                  style={{
                    backgroundColor: 'var(--coral-light)',
                    border: 'var(--border-black)',
                    color: 'var(--coral)',
                    padding: '0.75rem',
                    borderRadius: 'var(--radius-sm)',
                    marginBottom: '1rem',
                    fontSize: '0.875rem',
                    fontWeight: 700,
                  }}
                >
                  {sosError}
                </div>
              )}

              <div className="form-group">
                <label className="form-label">Campus Location *</label>
                <select
                  className="form-select"
                  value={sosLocation}
                  onChange={(e) => {
                    const val = e.target.value;
                    setSosLocation(val);
                    if (!isBuildingLocation(val)) {
                      setSosRoomNumber('');
                    }
                  }}
                  disabled={isSubmittingSos}
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

              {/* Conditional Room Number for Buildings */}
              {isBuildingLocation(sosLocation) && (
                <div className="form-group" style={{ marginTop: '-0.25rem', marginBottom: '1.25rem' }}>
                  <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
                    <span>Room Number *</span>
                    <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-secondary)' }}>
                      (Required for {sosLocation} — e.g. 101, A-204, CS-12)
                    </span>
                  </label>
                  <input
                    type="text"
                    className="form-input"
                    placeholder="e.g. 101, A-204, CS-12, G1-05"
                    value={sosRoomNumber}
                    onChange={(e) => setSosRoomNumber(e.target.value)}
                    disabled={isSubmittingSos}
                    required
                  />
                </div>
              )}

              <div className="form-group">
                <label htmlFor="sos-emergency-type" className="form-label">Emergency Type (Optional)</label>
                <select
                  id="sos-emergency-type"
                  className="form-select"
                  value={emergencyType}
                  onChange={handleEmergencyTypeChange}
                  disabled={isSubmittingSos}
                >
                  <option value="">Select emergency type</option>
                  {EMERGENCY_TYPES.map((type) => (
                    <option key={type} value={type}>
                      {type}
                    </option>
                  ))}
                </select>
              </div>

              {emergencyType === 'Custom' && (
                <div className="form-group" style={{ marginTop: '-0.25rem' }}>
                  <label htmlFor="sos-custom-description" className="form-label">Describe the Emergency</label>
                  <input
                    id="sos-custom-description"
                    type="text"
                    className="form-input"
                    placeholder="Briefly describe the emergency..."
                    value={customDescription}
                    onChange={(e) => setCustomDescription(e.target.value)}
                    disabled={isSubmittingSos}
                  />
                </div>
              )}

              <div style={{ display: 'flex', gap: '0.75rem', marginTop: '1.5rem' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => {
                    setSosModalOpen(false);
                    setEmergencyType('');
                    setCustomDescription('');
                  }}
                  disabled={isSubmittingSos}
                  style={{ flex: 1 }}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="btn btn-coral"
                  onClick={handleHeroSosSubmit}
                  disabled={isSubmittingSos}
                  style={{ flex: 2 }}
                >
                  {isSubmittingSos ? 'Transmitting Signal...' : 'CONFIRM & DISPATCH'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Responsive adjustments for mobile / tablet */}
      <style>{`
        @media (max-width: 992px) {
          .hero-building-cutout, .hero-student-cutout {
            display: none !important;
          }
        }
      `}</style>
    </div>
  );
};
