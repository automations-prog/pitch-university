import acaRoleplay from '@/routes/aca-roleplay';
import mealsRoleplay from '@/routes/meals-roleplay';
import medicareRoleplay from '@/routes/medicare-roleplay';
import type { RoleplayVerticalId } from '@/lib/roleplay-data';

const ROUTES = {
    medicare: medicareRoleplay,
    aca: acaRoleplay,
    meals: mealsRoleplay,
} satisfies Record<RoleplayVerticalId, unknown>;

/**
 * Every vertical has the same roleplay routes under its own prefix
 * (`routes/web.php`); this picks the vertical's set.
 */
export function roleplayRoutes(vertical: RoleplayVerticalId) {
    return ROUTES[vertical];
}
