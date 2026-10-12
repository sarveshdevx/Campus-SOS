import React, { useEffect } from 'react';
import { X, ExternalLink, Image as ImageIcon } from 'lucide-react';
import { IncidentAttachment } from '../../types/incident';

interface ImageLightboxModalProps {
  attachment: IncidentAttachment | null;
  onClose: () => void;
  title?: string;
  isOpen?: boolean;
}

export const ImageLightboxModal: React.FC<ImageLightboxModalProps> = ({
  attachment,
  onClose,
  title,
  isOpen,
}) => {
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [onClose]);

  if (isOpen === false || !attachment) return null;

  const rawUrl = attachment.url || attachment.filePath || attachment.file_path || '';
  // Ensure url is valid path
  const imageUrl = rawUrl.startsWith('http') || rawUrl.startsWith('data:')
    ? rawUrl
    : rawUrl.startsWith('/')
    ? rawUrl
    : `/${rawUrl}`;

  const fileName = attachment.fileName || attachment.file_name || 'Evidence Photograph';

  return (
    <div
      className="modal-overlay"
      style={{
        zIndex: 9999,
        backgroundColor: 'rgba(15, 17, 21, 0.85)',
        backdropFilter: 'blur(4px)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '1rem',
      }}
      onClick={onClose}
      role="dialog"
      aria-modal="true"
    >
      <div
        className="modal-dialog"
        style={{
          maxWidth: '850px',
          width: '100%',
          maxHeight: '92vh',
          backgroundColor: '#FFFFFF',
          borderRadius: 'var(--radius-md)',
          border: 'var(--border-black-thick)',
          boxShadow: 'var(--shadow-neo-lg)',
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div
          style={{
            padding: '1rem 1.25rem',
            backgroundColor: 'var(--cobalt-card)',
            borderBottom: 'var(--border-black)',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            gap: '1rem',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.625rem' }}>
            <ImageIcon size={20} color="var(--cobalt)" />
            <div>
              <h3 style={{ fontSize: '1.0625rem', fontWeight: 900, margin: 0, color: '#0F1115' }}>
                {title || 'Incident Visual Evidence'}
              </h3>
              <div style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', fontWeight: 600 }}>
                {fileName} {attachment.fileSize ? `• ${(attachment.fileSize / 1024).toFixed(1)} KB` : ''}
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <a
              href={imageUrl}
              target="_blank"
              rel="noreferrer"
              className="btn btn-secondary btn-sm"
              style={{ display: 'flex', alignItems: 'center', gap: '0.25rem', padding: '0.375rem 0.625rem' }}
              title="Open full image in new tab"
            >
              <ExternalLink size={14} />
              <span>Full View</span>
            </a>
            <button
              type="button"
              onClick={onClose}
              style={{
                width: '32px',
                height: '32px',
                backgroundColor: '#FFFFFF',
                border: 'var(--border-black)',
                borderRadius: 'var(--radius-sm)',
                boxShadow: 'var(--shadow-neo-sm)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
                fontWeight: 800,
              }}
              aria-label="Close image modal"
            >
              <X size={18} />
            </button>
          </div>
        </div>

        {/* Image Display */}
        <div
          style={{
            backgroundColor: '#0F1115',
            padding: '1rem',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            maxHeight: '75vh',
            overflow: 'auto',
          }}
        >
          <img
            src={imageUrl}
            alt={fileName}
            style={{
              maxWidth: '100%',
              maxHeight: '70vh',
              objectFit: 'contain',
              borderRadius: 'var(--radius-xs)',
              border: '1px solid rgba(255,255,255,0.1)',
            }}
          />
        </div>

        {/* Footer info */}
        <div
          style={{
            padding: '0.75rem 1.25rem',
            backgroundColor: 'var(--bg-secondary)',
            borderTop: '1px solid var(--bg-subtle)',
            fontSize: '0.75rem',
            color: 'var(--text-secondary)',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <span>
            {attachment.uploadedAt ? `Uploaded: ${attachment.uploadedAt}` : 'Attached by reporting student'}
          </span>
          <span style={{ fontWeight: 700, color: 'var(--mint)' }}>
            ✓ Verified Official Incident Record
          </span>
        </div>
      </div>
    </div>
  );
};
