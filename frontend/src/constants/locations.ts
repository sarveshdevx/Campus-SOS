/**
 * Approved campus locations for CampusSOS.
 * Exactly 25 locations: 16 general areas + 9 buildings/hostels requiring room numbers.
 */

export const GENERAL_CAMPUS_LOCATIONS = [
  'OAT',
  'Football Ground',
  'Basketball Court',
  'Vehicle Parking',
  'Gate 1',
  'Gate 2',
  'Gate 3',
  'Central Library',
  'Idea Lab',
  'Gyan Mandir Auditorium',
  'Admin Cell',
  'TP Cell',
  'Bus Stop',
  'D Garden',
  'Volleyball Court',
  'Sports Complex',
] as const;

export const BUILDING_LOCATIONS = [
  'CS Block',
  'Civil Block',
  'Mechanical Block',
  'Electrical Block',
  'Noran Girls Hostel',
  'Nirvana Boys Hostel',
  'AC Hostel',
  'KBH Hostel',
  'RKS Hostel',
] as const;

export type GeneralLocation = typeof GENERAL_CAMPUS_LOCATIONS[number];
export type BuildingLocation = typeof BUILDING_LOCATIONS[number];
export type CampusLocation = GeneralLocation | BuildingLocation;

export const ALL_CAMPUS_LOCATIONS: CampusLocation[] = [
  ...GENERAL_CAMPUS_LOCATIONS,
  ...BUILDING_LOCATIONS,
];

/**
 * Checks if a given location name is a building that requires a room number.
 */
export function isBuildingLocation(location: string): boolean {
  return (BUILDING_LOCATIONS as readonly string[]).includes(location.trim());
}

/**
 * Validates room number input.
 * Allows letters, numbers, hyphens, and spaces.
 */
export function validateRoomNumber(room: string): { valid: boolean; error?: string } {
  const trimmed = room.trim();
  if (!trimmed) {
    return { valid: false, error: 'Room number is required for building locations.' };
  }
  const regex = /^[a-zA-Z0-9\s-]+$/;
  if (!regex.test(trimmed)) {
    return { valid: false, error: 'Room number may only contain letters, numbers, spaces, and hyphens.' };
  }
  return { valid: true };
}

/**
 * Formats a location and room number into the standard composite string.
 * Uses em-dash: "Building Name — Room <roomNumber>"
 */
export function formatFullLocation(location: string, roomNumber?: string | null): string {
  const trimmedLoc = location.trim();
  if (isBuildingLocation(trimmedLoc) && roomNumber && roomNumber.trim()) {
    return `${trimmedLoc} — Room ${roomNumber.trim()}`;
  }
  return trimmedLoc;
}

