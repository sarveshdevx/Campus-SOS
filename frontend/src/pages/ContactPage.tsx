import React, { useState, useEffect } from 'react';
import {
  HeartPulse,
  Shield,
  Flame,
  Wrench,
  Users,
  Scale,
  Mail,
  Phone,
  MapPin,
  User as UserIcon,
  Edit3,
  AlertCircle,
  CheckCircle2,
  X,
  ExternalLink,
} from 'lucide-react';
import { useAuth } from '../hooks/useAuth';
import {
  contactService,
  DepartmentContact,
  DepartmentCategory,
  UpdateDepartmentContactPayload,
} from '../services/contactService';

interface ContactPageProps {
  onNavigate?: (tab: string) => void;
}

export const ContactPage: React.FC<ContactPageProps> = () => {
  const { user, isAuthenticated } = useAuth();
  const isAdmin = Boolean(isAuthenticated && user && user.role === 'admin');

  const [contacts, setContacts] = useState<DepartmentContact[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Admin edit modal state
  const [editingContact, setEditingContact] = useState<DepartmentContact | null>(null);
  const [editForm, setEditForm] = useState<UpdateDepartmentContactPayload>({});
  const [isSaving, setIsSaving] = useState<boolean>(false);
  const [modalError, setModalError] = useState<string | null>(null);

  const loadContacts = async () => {
    setIsLoading(true);
    setErrorMessage(null);
    try {
      const data = await contactService.getContacts();
      setContacts(data);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Unable to load department contacts.';
      setErrorMessage(msg);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadContacts();
  }, []);

  const getCategoryIcon = (category: DepartmentCategory) => {
    switch (category) {
      case 'medical':
        return <HeartPulse size={26} color="var(--mint-text, #0D684D)" />;
      case 'security':
        return <Shield size={26} color="var(--cobalt, #0047AB)" />;
      case 'fire':
        return <Flame size={26} color="var(--coral, #FF5A36)" />;
      case 'maintenance':
        return <Wrench size={26} color="#854D0E" />;
      case 'anti_ragging':
        return <Users size={26} color="#5B21B6" />;
      case 'sexual_harassment':
        return <Scale size={26} color="#4C1D95" />;
      default:
        return <Shield size={26} />;
    }
  };

  const getCategoryBadgeClass = (category: DepartmentCategory) => {
    switch (category) {
      case 'medical':
        return 'badge-mint';
      case 'security':
        return 'badge-cobalt';
      case 'fire':
        return 'badge-coral';
      case 'maintenance':
        return 'badge-yellow';
      case 'anti_ragging':
        return 'badge-purple';
      case 'sexual_harassment':
        return 'badge-purple';
      default:
        return 'badge-black';
    }
  };

  const openEditModal = (c: DepartmentContact) => {
    setEditingContact(c);
    setEditForm({
      departmentName: c.departmentName,
      representativeName: c.representativeName || '',
      designation: c.designation || '',
      email: c.email || '',
      phoneNumber: c.phoneNumber || '',
      officeLocation: c.officeLocation || '',
      description: c.description || '',
      isConfigured: c.isConfigured,
      isOfficialVerified: c.isOfficialVerified,
    });
    setModalError(null);
  };

  const closeEditModal = () => {
    setEditingContact(null);
    setEditForm({});
    setModalError(null);
  };

  const handleSaveContact = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingContact) return;

    setIsSaving(true);
    setModalError(null);

    // Client-side validations
    if (editForm.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(editForm.email.trim())) {
      setModalError('Please enter a valid email address.');
      setIsSaving(false);
      return;
    }

    if (editForm.phoneNumber && !/^[+]?[0-9\s\-().]{3,25}$/.test(editForm.phoneNumber.trim())) {
      setModalError('Please enter a valid phone number (3 to 25 digits/symbols).');
      setIsSaving(false);
      return;
    }

    try {
      await contactService.updateContact(editingContact.id, {
        departmentName: editForm.departmentName?.trim(),
        representativeName: editForm.representativeName?.trim() || null,
        designation: editForm.designation?.trim() || null,
        email: editForm.email?.trim() || null,
        phoneNumber: editForm.phoneNumber?.trim() || null,
        officeLocation: editForm.officeLocation?.trim() || null,
        description: editForm.description?.trim() || null,
        isConfigured: Boolean(editForm.isConfigured),
        isOfficialVerified: Boolean(editForm.isOfficialVerified),
      });

      setSuccessMessage(`Updated contact details for ${editForm.departmentName || editingContact.departmentName}.`);
      closeEditModal();
      await loadContacts();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Failed to save contact details.';
      setModalError(msg);
    } finally {
      setIsSaving(false);
    }
  };

  // Helper to validate phone format for tel: link
  const isValidPhone = (phone?: string | null): boolean => {
    if (!phone) return false;
    return /^[+]?[0-9\s\-().]{3,25}$/.test(phone.trim());
  };

  // Helper to sanitize phone for tel: URI
  const sanitizeTelHref = (phone: string): string => {
    return 'tel:' + phone.replace(/[^0-9+]/g, '');
  };

  // Helper to validate email for mailto: link
  const isValidEmail = (email?: string | null): boolean => {
    if (!email) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
  };

  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '1rem 0' }}>
      {/* Header Section */}
      <div style={{ marginBottom: '2.5rem', textAlign: 'center' }}>
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.75rem' }}>
          <span className="badge badge-coral">CAMPUS DIRECTORY</span>
          <span style={{ fontSize: '0.8125rem', fontWeight: 800, color: 'var(--text-secondary)' }}>
            OFFICIAL HOTLINES &amp; DESKS
          </span>
        </div>

        <h1
          style={{
            fontSize: 'clamp(2rem, 4vw, 2.75rem)',
            fontWeight: 900,
            letterSpacing: '-0.04em',
            marginBottom: '0.75rem',
            color: '#0F1115',
          }}
        >
          CONTACT CAMPUS RESPONSE TEAMS
        </h1>

        <p
          style={{
            maxWidth: '680px',
            margin: '0 auto',
            fontSize: '1.0625rem',
            color: 'var(--text-secondary)',
            lineHeight: 1.55,
            fontWeight: 600,
          }}
        >
          Direct contact information for all six campus safety departments. Students and faculty
          may reach out to authorized coordinators for medical, security, maintenance, fire, anti-ragging,
          and confidential ICC inquiries.
        </p>

        {isAdmin && (
          <div
            style={{
              marginTop: '1.25rem',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '0.5rem',
              backgroundColor: 'var(--yellow-card)',
              border: 'var(--border-black)',
              boxShadow: '2px 2px 0px #0F1115',
              padding: '0.4rem 0.85rem',
              borderRadius: 'var(--radius-pill)',
              fontSize: '0.8125rem',
              fontWeight: 800,
            }}
          >
            <span>👑 Administrator Mode Active: You can edit and update department contacts.</span>
          </div>
        )}
      </div>

      {/* Notifications */}
      {successMessage && (
        <div
          style={{
            backgroundColor: 'var(--lime-card)',
            border: 'var(--border-black)',
            boxShadow: 'var(--shadow-neo-sm)',
            padding: '0.875rem 1.25rem',
            borderRadius: 'var(--radius-sm)',
            fontWeight: 800,
            fontSize: '0.9375rem',
            marginBottom: '1.5rem',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <CheckCircle2 size={18} />
            <span>{successMessage}</span>
          </div>
          <button
            type="button"
            onClick={() => setSuccessMessage(null)}
            style={{ background: 'none', border: 'none', cursor: 'pointer', fontWeight: 800 }}
          >
            ✕
          </button>
        </div>
      )}

      {errorMessage && (
        <div
          style={{
            backgroundColor: 'var(--coral-light)',
            border: 'var(--border-black)',
            boxShadow: 'var(--shadow-neo-sm)',
            color: 'var(--coral)',
            padding: '0.875rem 1.25rem',
            borderRadius: 'var(--radius-sm)',
            fontWeight: 800,
            fontSize: '0.9375rem',
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

      {/* Loading Skeleton */}
      {isLoading && contacts.length === 0 && (
        <div style={{ textAlign: 'center', padding: '3rem 0', fontWeight: 700, color: 'var(--text-secondary)' }}>
          Loading official campus contacts...
        </div>
      )}

      {/* Responsive 6-Card Grid */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))',
          gap: '1.5rem',
        }}
      >
        {contacts.map((c) => {
          const hasValidEmail = Boolean(c.isConfigured && c.email && isValidEmail(c.email));
          const hasValidPhone = Boolean(c.isConfigured && c.phoneNumber && isValidPhone(c.phoneNumber));

          return (
            <div
              key={c.id}
              className="card"
              style={{
                backgroundColor: '#FFFFFF',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'space-between',
                padding: '1.75rem',
                gap: '1.25rem',
                position: 'relative',
              }}
            >
              {/* Card Header */}
              <div>
                <div
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'flex-start',
                    marginBottom: '1rem',
                  }}
                >
                  <div
                    style={{
                      width: '3.25rem',
                      height: '3.25rem',
                      borderRadius: 'var(--radius-md)',
                      backgroundColor: 'var(--bg-secondary)',
                      border: 'var(--border-black)',
                      boxShadow: '2px 2px 0px #0F1115',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    {getCategoryIcon(c.category)}
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.35rem' }}>
                    <span className={`badge ${getCategoryBadgeClass(c.category)}`}>
                      {c.category.replace('_', ' ').toUpperCase()}
                    </span>

                    {c.isOfficialVerified ? (
                      <span
                        className="badge badge-mint"
                        style={{ fontSize: '0.625rem', padding: '0.1rem 0.4rem' }}
                        title="Institutionally verified official department contact"
                      >
                        ✓ VERIFIED
                      </span>
                    ) : (
                      <span
                        className="badge badge-yellow"
                        style={{ fontSize: '0.625rem', padding: '0.1rem 0.4rem' }}
                        title="Standard evaluation contact; formal institutional confirmation pending"
                      >
                        DEMO CONTACT
                      </span>
                    )}
                  </div>
                </div>

                {/* Department Title & Designation */}
                <h3
                  style={{
                    fontSize: '1.375rem',
                    fontWeight: 900,
                    letterSpacing: '-0.02em',
                    marginBottom: '0.25rem',
                    color: '#0F1115',
                  }}
                >
                  {c.departmentName}
                </h3>

                {c.representativeName && (
                  <div
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.35rem',
                      fontSize: '0.875rem',
                      fontWeight: 700,
                      color: 'var(--cobalt)',
                      marginBottom: '0.5rem',
                    }}
                  >
                    <UserIcon size={14} />
                    <span>
                      {c.representativeName}
                      {c.designation ? ` • ${c.designation}` : ''}
                    </span>
                  </div>
                )}

                {/* Short Description */}
                {c.description && (
                  <p
                    style={{
                      fontSize: '0.875rem',
                      color: 'var(--text-secondary)',
                      lineHeight: 1.45,
                      marginBottom: '1rem',
                      fontWeight: 500,
                    }}
                  >
                    {c.description}
                  </p>
                )}

                {/* Campus Office Location */}
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.5rem',
                    fontSize: '0.8125rem',
                    color: '#0F1115',
                    fontWeight: 700,
                    backgroundColor: 'var(--bg-secondary)',
                    padding: '0.5rem 0.75rem',
                    borderRadius: 'var(--radius-sm)',
                    border: '1px solid var(--border-black)',
                    marginBottom: '1.25rem',
                  }}
                >
                  <MapPin size={15} color="var(--cobalt)" />
                  <span>
                    Office:{' '}
                    {c.officeLocation ? (
                      <strong>{c.officeLocation}</strong>
                    ) : (
                      <em style={{ color: 'var(--text-secondary)' }}>Location not specified</em>
                    )}
                  </span>
                </div>
              </div>

              {/* Contact Actions Area */}
              <div
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '0.625rem',
                  borderTop: 'var(--border-black)',
                  paddingTop: '1.25rem',
                }}
              >
                {/* Official Phone */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <Phone size={15} color="#0F1115" />
                    <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase' }}>
                      Phone:
                    </span>
                  </div>

                  {hasValidPhone ? (
                    <a
                      href={sanitizeTelHref(c.phoneNumber!)}
                      className="btn btn-secondary btn-sm"
                      style={{
                        textDecoration: 'none',
                        fontFamily: 'var(--font-mono)',
                        fontWeight: 800,
                        fontSize: '0.8125rem',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '0.35rem',
                      }}
                      title={`Call ${c.departmentName}`}
                    >
                      <span>{c.phoneNumber}</span>
                      <ExternalLink size={12} />
                    </a>
                  ) : (
                    <span
                      style={{
                        backgroundColor: 'var(--coral-light)',
                        border: '1px solid var(--coral-border)',
                        color: 'var(--coral)',
                        padding: '0.2rem 0.5rem',
                        borderRadius: 'var(--radius-xs)',
                        fontSize: '0.75rem',
                        fontWeight: 700,
                      }}
                    >
                      Contact details not configured
                    </span>
                  )}
                </div>

                {/* Official Email */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <Mail size={15} color="#0F1115" />
                    <span style={{ fontSize: '0.75rem', fontWeight: 800, textTransform: 'uppercase' }}>
                      Email:
                    </span>
                  </div>

                  {hasValidEmail ? (
                    <a
                      href={`mailto:${c.email}`}
                      className="btn btn-secondary btn-sm"
                      style={{
                        textDecoration: 'none',
                        fontWeight: 700,
                        fontSize: '0.8125rem',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '0.35rem',
                      }}
                      title={`Email ${c.departmentName}`}
                    >
                      <span>{c.email}</span>
                      <ExternalLink size={12} />
                    </a>
                  ) : (
                    <span
                      style={{
                        backgroundColor: 'var(--coral-light)',
                        border: '1px solid var(--coral-border)',
                        color: 'var(--coral)',
                        padding: '0.2rem 0.5rem',
                        borderRadius: 'var(--radius-xs)',
                        fontSize: '0.75rem',
                        fontWeight: 700,
                      }}
                    >
                      Contact details not configured
                    </span>
                  )}
                </div>

                {/* Admin Edit Trigger */}
                {isAdmin && (
                  <button
                    type="button"
                    onClick={() => openEditModal(c)}
                    className="btn btn-black btn-sm"
                    style={{
                      marginTop: '0.5rem',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '0.35rem',
                    }}
                  >
                    <Edit3 size={14} />
                    <span>Edit Department Contact</span>
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {/* Admin Edit Modal */}
      {editingContact && (
        <div
          className="modal-overlay"
          onClick={closeEditModal}
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(15, 17, 21, 0.65)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1000,
            padding: '1rem',
          }}
        >
          <div
            className="modal-dialog"
            onClick={(e) => e.stopPropagation()}
            style={{
              backgroundColor: '#FFFFFF',
              border: 'var(--border-black-thick)',
              boxShadow: 'var(--shadow-neo-lg)',
              borderRadius: 'var(--radius-md)',
              width: '100%',
              maxWidth: '560px',
              maxHeight: '90vh',
              overflowY: 'auto',
            }}
          >
            {/* Modal Header */}
            <div
              style={{
                padding: '1.25rem 1.5rem',
                borderBottom: 'var(--border-black)',
                backgroundColor: 'var(--cobalt-card)',
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
              }}
            >
              <div>
                <span className="badge badge-cobalt">ADMINISTRATOR SETTINGS</span>
                <h3 style={{ fontSize: '1.25rem', fontWeight: 900, marginTop: '0.25rem' }}>
                  Edit {editingContact.departmentName}
                </h3>
              </div>
              <button
                type="button"
                onClick={closeEditModal}
                style={{
                  background: 'none',
                  border: 'none',
                  fontSize: '1.25rem',
                  fontWeight: 900,
                  cursor: 'pointer',
                }}
              >
                <X size={20} />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleSaveContact} style={{ padding: '1.5rem' }}>
              {modalError && (
                <div
                  style={{
                    backgroundColor: 'var(--coral-light)',
                    border: '1px solid var(--coral-border)',
                    color: 'var(--coral)',
                    padding: '0.75rem',
                    borderRadius: 'var(--radius-sm)',
                    fontSize: '0.875rem',
                    fontWeight: 700,
                    marginBottom: '1rem',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '0.5rem',
                  }}
                >
                  <AlertCircle size={16} />
                  <span>{modalError}</span>
                </div>
              )}

              <div className="form-group" style={{ marginBottom: '1rem' }}>
                <label className="form-label">Department Title</label>
                <input
                  type="text"
                  className="form-input"
                  value={editForm.departmentName || ''}
                  onChange={(e) => setEditForm({ ...editForm, departmentName: e.target.value })}
                  required
                />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem', marginBottom: '1rem' }}>
                <div className="form-group">
                  <label className="form-label">Representative Name</label>
                  <input
                    type="text"
                    className="form-input"
                    placeholder="e.g. Dr. Priya Varma"
                    value={editForm.representativeName || ''}
                    onChange={(e) => setEditForm({ ...editForm, representativeName: e.target.value })}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">Designation</label>
                  <input
                    type="text"
                    className="form-input"
                    placeholder="e.g. Chief Medical Officer"
                    value={editForm.designation || ''}
                    onChange={(e) => setEditForm({ ...editForm, designation: e.target.value })}
                  />
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem', marginBottom: '1rem' }}>
                <div className="form-group">
                  <label className="form-label">Official Department Phone</label>
                  <input
                    type="text"
                    className="form-input"
                    placeholder="+91 98765 00000"
                    value={editForm.phoneNumber || ''}
                    onChange={(e) => setEditForm({ ...editForm, phoneNumber: e.target.value })}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">Official Department Email</label>
                  <input
                    type="email"
                    className="form-input"
                    placeholder="dept@campussos.edu"
                    value={editForm.email || ''}
                    onChange={(e) => setEditForm({ ...editForm, email: e.target.value })}
                  />
                </div>
              </div>

              <div className="form-group" style={{ marginBottom: '1rem' }}>
                <label className="form-label">Office / Campus Location</label>
                <input
                  type="text"
                  className="form-input"
                  placeholder="e.g. Health Center Room 102"
                  value={editForm.officeLocation || ''}
                  onChange={(e) => setEditForm({ ...editForm, officeLocation: e.target.value })}
                />
              </div>

              <div className="form-group" style={{ marginBottom: '1.25rem' }}>
                <label className="form-label">Department Description</label>
                <textarea
                  className="form-input"
                  rows={2}
                  value={editForm.description || ''}
                  onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
                />
              </div>

              {/* Verification & Configuration Flags */}
              <div
                style={{
                  backgroundColor: 'var(--bg-secondary)',
                  border: 'var(--border-black)',
                  padding: '0.875rem',
                  borderRadius: 'var(--radius-sm)',
                  marginBottom: '1.5rem',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '0.75rem',
                }}
              >
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.625rem', cursor: 'pointer', fontWeight: 700, fontSize: '0.875rem' }}>
                  <input
                    type="checkbox"
                    checked={Boolean(editForm.isConfigured)}
                    onChange={(e) => setEditForm({ ...editForm, isConfigured: e.target.checked })}
                    style={{ width: '1.125rem', height: '1.125rem', accentColor: 'var(--cobalt)' }}
                  />
                  <span>Mark as Configured (Displays phone &amp; email publicly)</span>
                </label>

                <label style={{ display: 'flex', alignItems: 'center', gap: '0.625rem', cursor: 'pointer', fontWeight: 700, fontSize: '0.875rem' }}>
                  <input
                    type="checkbox"
                    checked={Boolean(editForm.isOfficialVerified)}
                    onChange={(e) => setEditForm({ ...editForm, isOfficialVerified: e.target.checked })}
                    style={{ width: '1.125rem', height: '1.125rem', accentColor: 'var(--mint-text)' }}
                  />
                  <span>Officially Verified by University Administration</span>
                </label>
              </div>

              {/* Action Buttons */}
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={closeEditModal}
                  disabled={isSaving}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={isSaving}
                >
                  {isSaving ? 'Saving Changes...' : 'Save Department Details'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

