import acaRoleplay from '@/routes/aca-roleplay';
import medicareRoleplay from '@/routes/medicare-roleplay';
import type { RoleplayVerticalId } from '@/lib/roleplay-data';

/**
 * Every vertical has the same roleplay routes under its own prefix
 * (`routes/web.php`); this picks the vertical's set.
 */
export function roleplayRoutes(vertical: RoleplayVerticalId) {
    return vertical === 'aca' ? acaRoleplay : medicareRoleplay;
}
