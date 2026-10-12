import React, { useRef, useState } from 'react';
import { Camera, Upload, X, RefreshCw, AlertCircle } from 'lucide-react';

export interface UploadedImageItem {
  data: string; // base64 / data URL
  name: string;
  size: number;
}

interface ImageUploadAreaProps {
  maxImages?: number;
  images: UploadedImageItem[];
  onChange: (images: UploadedImageItem[]) => void;
  disabled?: boolean;
}

export const ImageUploadArea: React.FC<ImageUploadAreaProps> = ({
  maxImages = 2,
  images,
  onChange,
  disabled = false,
}) => {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);
  const replaceInputRef = useRef<HTMLInputElement>(null);
  const [replaceIndex, setReplaceIndex] = useState<number | null>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  };

  const processFile = (file: File): Promise<UploadedImageItem> => {
    return new Promise((resolve, reject) => {
      // Validate file type
      if (!file.type.startsWith('image/')) {
        reject(new Error(`"${file.name}" is not an image file.`));
        return;
      }

      // Max size: 10MB
      if (file.size > 10 * 1024 * 1024) {
        reject(new Error(`"${file.name}" exceeds 10MB maximum limit (${formatFileSize(file.size)}).`));
        return;
      }

      const reader = new FileReader();
      reader.onload = (e) => {
        const result = e.target?.result as string;
        if (result) {
          resolve({
            data: result,
            name: file.name,
            size: file.size,
          });
        } else {
          reject(new Error('Failed to read image file.'));
        }
      };
      reader.onerror = () => reject(new Error('Error reading image file.'));
      reader.readAsDataURL(file);
    });
  };

  const handleFilesSelected = async (e: React.ChangeEvent<HTMLInputElement>) => {
    setUploadError(null);
    const files = e.target.files;
    if (!files || files.length === 0) return;

    const availableSlots = maxImages - images.length;
    if (availableSlots <= 0) {
      setUploadError(`Maximum of ${maxImages} images allowed.`);
      if (fileInputRef.current) fileInputRef.current.value = '';
      if (cameraInputRef.current) cameraInputRef.current.value = '';
      return;
    }

    const filesToProcess = Array.from(files).slice(0, availableSlots);
    const newItems: UploadedImageItem[] = [];

    for (const f of filesToProcess) {
      try {
        const item = await processFile(f);
        newItems.push(item);
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Failed to load photo.';
        setUploadError(msg);
      }
    }

    if (newItems.length > 0) {
      onChange([...images, ...newItems]);
    }

    if (fileInputRef.current) fileInputRef.current.value = '';
    if (cameraInputRef.current) cameraInputRef.current.value = '';
  };

  const handleReplaceFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    setUploadError(null);
    const files = e.target.files;
    if (!files || files.length === 0 || replaceIndex === null) return;

    try {
      const item = await processFile(files[0]);
      const updated = [...images];
      updated[replaceIndex] = item;
      onChange(updated);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to replace photo.';
      setUploadError(msg);
    } finally {
      setReplaceIndex(null);
      if (replaceInputRef.current) replaceInputRef.current.value = '';
    }
  };

  const handleRemove = (index: number) => {
    setUploadError(null);
    const updated = images.filter((_, idx) => idx !== index);
    onChange(updated);
  };

  const triggerReplace = (index: number) => {
    if (disabled) return;
    setReplaceIndex(index);
    replaceInputRef.current?.click();
  };

  const isMaxReached = images.length >= maxImages;

  return (
    <div className="incident-photos-section" style={{ marginTop: '0.25rem', marginBottom: '1.25rem' }}>
      {/* Hidden inputs */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        multiple
        style={{ display: 'none' }}
        onChange={handleFilesSelected}
        disabled={disabled || isMaxReached}
      />
      <input
        ref={cameraInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        style={{ display: 'none' }}
        onChange={handleFilesSelected}
        disabled={disabled || isMaxReached}
      />
      <input
        ref={replaceInputRef}
        type="file"
        accept="image/*"
        style={{ display: 'none' }}
        onChange={handleReplaceFile}
        disabled={disabled}
      />

      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.375rem' }}>
        <label className="form-label" style={{ display: 'flex', alignItems: 'center', gap: '0.375rem', margin: 0 }}>
          <Camera size={16} color="var(--cobalt)" />
          <span>Incident Photos (Optional)</span>
        </label>
        <span style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--text-secondary)' }}>
          {images.length} of {maxImages} attached
        </span>
      </div>

      <p style={{ fontSize: '0.75rem', color: 'var(--text-secondary)', marginBottom: '0.625rem', lineHeight: 1.4 }}>
        Attach up to 2 photos showing the scene, hazard, or damage. Visual evidence helps dispatchers and responders prepare immediately.
      </p>

      {/* Error alert */}
      {uploadError && (
        <div
          style={{
            backgroundColor: 'var(--coral-light)',
            border: '1px solid var(--coral-border)',
            color: 'var(--coral)',
            padding: '0.5rem 0.75rem',
            borderRadius: 'var(--radius-sm)',
            fontSize: '0.75rem',
            fontWeight: 700,
            display: 'flex',
            alignItems: 'center',
            gap: '0.375rem',
            marginBottom: '0.625rem',
          }}
        >
          <AlertCircle size={14} />
          <span>{uploadError}</span>
        </div>
      )}

      {/* Upload Buttons */}
      {!isMaxReached && (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))',
            gap: '0.5rem',
            marginBottom: images.length > 0 ? '0.75rem' : '0',
          }}
        >
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={() => fileInputRef.current?.click()}
            disabled={disabled}
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '0.375rem',
              padding: '0.5rem 0.75rem',
              fontSize: '0.8125rem',
              fontWeight: 700,
            }}
          >
            <Upload size={15} />
            <span>Upload Photo</span>
          </button>

          <button
            type="button"
            className="btn btn-secondary btn-sm"
            onClick={() => cameraInputRef.current?.click()}
            disabled={disabled}
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '0.375rem',
              padding: '0.5rem 0.75rem',
              fontSize: '0.8125rem',
              fontWeight: 700,
            }}
          >
            <Camera size={15} />
            <span>Take Photo</span>
          </button>
        </div>
      )}

      {/* Previews Grid */}
      {images.length > 0 && (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))',
            gap: '0.75rem',
            marginTop: '0.5rem',
          }}
        >
          {images.map((img, idx) => (
            <div
              key={idx}
              style={{
                position: 'relative',
                border: '2px solid #0F1115',
                borderRadius: 'var(--radius-sm)',
                overflow: 'hidden',
                backgroundColor: '#FFFFFF',
                boxShadow: 'var(--shadow-neo-sm)',
                display: 'flex',
                flexDirection: 'column',
              }}
            >
              {/* Thumbnail Container */}
              <div
                style={{
                  position: 'relative',
                  width: '100%',
                  height: '95px',
                  backgroundColor: 'var(--bg-secondary)',
                  overflow: 'hidden',
                  cursor: 'pointer',
                }}
                onClick={() => triggerReplace(idx)}
                title="Click to replace image"
              >
                <img
                  src={img.data}
                  alt={img.name}
                  style={{
                    width: '100%',
                    height: '100%',
                    objectFit: 'cover',
                    display: 'block',
                  }}
                />
                <span
                  style={{
                    position: 'absolute',
                    top: '4px',
                    left: '4px',
                    backgroundColor: 'rgba(15, 17, 21, 0.85)',
                    color: '#FFFFFF',
                    fontSize: '0.625rem',
                    fontWeight: 800,
                    padding: '2px 5px',
                    borderRadius: 'var(--radius-xs)',
                  }}
                >
                  Photo {idx + 1}
                </span>

                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleRemove(idx);
                  }}
                  disabled={disabled}
                  style={{
                    position: 'absolute',
                    top: '4px',
                    right: '4px',
                    width: '22px',
                    height: '22px',
                    backgroundColor: 'var(--coral)',
                    color: '#FFFFFF',
                    border: '1px solid #0F1115',
                    borderRadius: 'var(--radius-full)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                    boxShadow: '0 1px 3px rgba(0,0,0,0.3)',
                  }}
                  title="Remove photo"
                  aria-label={`Remove photo ${idx + 1}`}
                >
                  <X size={13} strokeWidth={3} />
                </button>
              </div>

              {/* Meta bar */}
              <div
                style={{
                  padding: '0.375rem 0.5rem',
                  fontSize: '0.6875rem',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  backgroundColor: '#FFFFFF',
                  borderTop: '1px solid var(--bg-subtle)',
                }}
              >
                <span
                  style={{
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                    maxWidth: '85px',
                    fontWeight: 600,
                  }}
                  title={img.name}
                >
                  {img.name}
                </span>
                <button
                  type="button"
                  onClick={() => triggerReplace(idx)}
                  disabled={disabled}
                  style={{
                    background: 'none',
                    border: 'none',
                    color: 'var(--cobalt)',
                    fontSize: '0.6875rem',
                    fontWeight: 700,
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '2px',
                    padding: 0,
                  }}
                  title="Replace photo"
                >
                  <RefreshCw size={10} />
                  <span>Replace</span>
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
