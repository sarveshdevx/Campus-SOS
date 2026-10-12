import React, { useState } from 'react';
import { AlertTriangle, ShieldAlert, X, MapPin, Radio } from 'lucide-react';
import { sosService } from '../../services/sosService';
import {
  GENERAL_CAMPUS_LOCATIONS,
  BUILDING_LOCATIONS,
  isBuildingLocation,
  formatFullLocation,
  validateRoomNumber,
} from '../../constants/locations';
import { EMERGENCY_TYPES } from '../../constants/emergencyTypes';
import { ImageUploadArea, UploadedImageItem } from './ImageUploadArea';

interface FloatingSosButtonProps {
  onSosTriggered?: (alertId: number) => void;
}

export const FloatingSosButton: React.FC<FloatingSosButtonProps> = ({ onSosTriggered }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [locationName, setLocationName] = useState('Central Library');
  const [roomNumber, setRoomNumber] = useState('');
  const [emergencyType, setEmergencyType] = useState('');
  const [customDescription, setCustomDescription] = useState('');
  const [attachedImages, setAttachedImages] = useState<UploadedImageItem[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleEmergencyTypeChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value;
    setEmergencyType(val);
    if (val !== 'Custom') {
      setCustomDescription('');
    }
  };

  const handleOpen = () => {
    setErrorMessage(null);
    setIsOpen(true);
  };

  const handleClose = () => {
    if (!isSubmitting) {
      setIsOpen(false);
      setEmergencyType('');
      setCustomDescription('');
      setAttachedImages([]);
    }
  };

  const handleConfirmTrigger = async () => {
    setErrorMessage(null);

    if (!locationName) {
      setErrorMessage('Please select a campus location.');
      return;
    }

    if (isBuildingLocation(locationName)) {
      const roomCheck = validateRoomNumber(roomNumber);
      if (!roomCheck.valid) {
        setErrorMessage(roomCheck.error || 'Room number is required for building locations.');
        return;
      }
    }

    if (emergencyType === 'Custom' && !customDescription.trim()) {
      setErrorMessage('Please describe the emergency.');
      return;
    }

    setIsSubmitting(true);

    try {
      const formattedLocation = formatFullLocation(locationName, roomNumber);
      const alert = await sosService.triggerSos({
        locationName: formattedLocation,
        roomNumber: isBuildingLocation(locationName) ? roomNumber.trim() : undefined,
        emergencyType: emergencyType || undefined,
        emergencyDetails: emergencyType === 'Custom' ? customDescription.trim() : undefined,
        emergencyNotes: emergencyType === 'Custom' ? customDescription.trim() : (emergencyType || undefined),
        images: attachedImages.length > 0 ? attachedImages.map((img) => ({ data: img.data, name: img.name })) : undefined,
      });

      setIsOpen(false);
      setRoomNumber('');
      setEmergencyType('');
      setCustomDescription('');
      setAttachedImages([]);
      if (onSosTriggered) {
        onSosTriggered(alert.id);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to transmit SOS signal.';
      setErrorMessage(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <>
      {/* Persistent Floating Action Button */}
      <div className="floating-sos-container" aria-label="Emergency SOS activation">
        <div style={{ position: 'relative' }}>
          <div className="floating-sos-pulse" />
          <button
            type="button"
            className="floating-sos-btn"
            onClick={handleOpen}
            aria-haspopup="dialog"
            aria-expanded={isOpen}
            id="floating-sos-trigger"
          >
            <ShieldAlert size={20} strokeWidth={2.5} />
            <span>EMERGENCY SOS</span>
          </button>
        </div>
      </div>

      {/* Confirmation & Dispatch Modal */}
      {isOpen && (
        <div
          className="modal-overlay"
          role="dialog"
          aria-modal="true"
          aria-labelledby="sos-modal-title"
          onClick={handleClose}
        >
          <div
            className="modal-dialog"
            onClick={(e) => e.stopPropagation()}
            style={{ borderTop: '6px solid var(--coral)' }}
          >
            {/* Header */}
            <div
              style={{
                padding: '1.25rem 1.5rem',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                borderBottom: '1px solid var(--bg-subtle)',
                backgroundColor: 'var(--coral-light)',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                <div
                  style={{
                    backgroundColor: 'var(--coral)',
                    color: '#fff',
                    padding: '0.5rem',
                    borderRadius: 'var(--radius-md)',
                    display: 'flex',
                  }}
                >
                  <AlertTriangle size={24} />
                </div>
                <div>
                  <h2
                    id="sos-modal-title"
                    style={{
                      fontSize: '1.25rem',
                      fontWeight: 800,
                      color: 'var(--coral)',
                      lineHeight: 1.2,
                    }}
                  >
                    Confirm Emergency SOS
                  </h2>
                  <p style={{ fontSize: '0.8125rem', color: 'var(--text-secondary)' }}>
                    Immediate response will be dispatched to your location
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={handleClose}
                disabled={isSubmitting}
                style={{
                  background: 'transparent',
                  border: 'none',
                  cursor: 'pointer',
                  color: 'var(--text-secondary)',
                  padding: '0.25rem',
                  display: 'flex',
                }}
                aria-label="Close modal"
              >
                <X size={20} />
              </button>
            </div>

            {/* Body */}
            <div style={{ padding: '1.5rem' }}>
              <p
                style={{
                  fontSize: '0.9375rem',
                  color: 'var(--text-primary)',
                  marginBottom: '1.25rem',
                  lineHeight: 1.5,
                }}
              >
                This action notifies <strong>Campus Security</strong> and{' '}
                <strong>Medical Response</strong> teams immediately. Only activate in genuine
                emergencies.
              </p>

              {errorMessage && (
                <div
                  style={{
                    backgroundColor: 'var(--coral-light)',
                    border: '1px solid var(--coral-border)',
                    color: 'var(--coral)',
                    padding: '0.75rem',
                    borderRadius: 'var(--radius-md)',
                    fontSize: '0.875rem',
                    marginBottom: '1rem',
                  }}
                >
                  {errorMessage}
                </div>
              )}

              <div className="form-group">
                <label htmlFor="sos-location" className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
                  <MapPin size={16} color="var(--coral)" />
                  Confirm Current Location *
                </label>
                <select
                  id="sos-location"
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

              {/* Conditional Room Number for Buildings */}
              {isBuildingLocation(locationName) && (
                <div className="form-group" style={{ marginTop: '-0.25rem', marginBottom: '1.25rem' }}>
                  <label htmlFor="sos-room" className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem' }}>
                    <span>Room Number *</span>
                    <span style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--text-secondary)' }}>
                      (Required for {locationName} — e.g. 101, A-204, CS-12)
                    </span>
                  </label>
                  <input
                    id="sos-room"
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

              <div className="form-group">
                <label htmlFor="sos-floating-type" className="form-label">
                  Emergency Type (Optional)
                </label>
                <select
                  id="sos-floating-type"
                  className="form-select"
                  value={emergencyType}
                  onChange={handleEmergencyTypeChange}
                  disabled={isSubmitting}
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
                  <label htmlFor="sos-floating-custom" className="form-label">
                    Describe the Emergency
                  </label>
                  <input
                    id="sos-floating-custom"
                    type="text"
                    className="form-input"
                    placeholder="Briefly describe the emergency..."
                    value={customDescription}
                    onChange={(e) => setCustomDescription(e.target.value)}
                    disabled={isSubmitting}
                  />
                </div>
              )}

              {/* Optional Visual Evidence Upload Area (Max 2) */}
              <ImageUploadArea
                maxImages={2}
                images={attachedImages}
                onChange={setAttachedImages}
                disabled={isSubmitting}
              />

              <div
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '0.5rem',
                  fontSize: '0.8125rem',
                  color: 'var(--mint)',
                  backgroundColor: 'var(--mint-light)',
                  padding: '0.625rem 0.875rem',
                  borderRadius: 'var(--radius-md)',
                  marginBottom: '1.25rem',
                }}
              >
                <Radio size={16} />
                <span>Deterministic instant dispatch enabled (No AI delay).</span>
              </div>

              {/* Actions */}
              <div style={{ display: 'flex', gap: '0.75rem', justifyContent: 'flex-end' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={handleClose}
                  disabled={isSubmitting}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  className="btn btn-coral"
                  style={{ gap: '0.5rem' }}
                  onClick={handleConfirmTrigger}
                  disabled={isSubmitting}
                >
                  <ShieldAlert size={18} />
                  {isSubmitting ? 'Transmitting SOS...' : 'ACTIVATE SOS DISPATCH'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

