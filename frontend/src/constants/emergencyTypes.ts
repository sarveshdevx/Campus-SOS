/**
 * Predefined Emergency Types for CampusSOS Emergency SOS Modal
 * Includes 11 predefined incident types plus 'Custom' option.
 */

export const EMERGENCY_TYPES = [
  'Fire / Smoke',
  'Vehicle Collision',
  'Medical Emergency',
  'Physical Assault / Fight',
  'Harassment / Threat',
  'Suspicious Activity',
  'Theft / Robbery',
  'Electrical Hazard',
  'Gas Leak',
  'Injury / Accident',
  'Other Emergency',
  'Custom',
] as const;

export type EmergencyType = typeof EMERGENCY_TYPES[number];

export const isCustomEmergencyType = (type: string): boolean => {
  return type === 'Custom';
};

