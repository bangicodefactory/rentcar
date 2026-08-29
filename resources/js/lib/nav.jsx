import { usePage } from '@inertiajs/react';
import {
    LayoutDashboard, Users, Car, CalendarCheck, ReceiptText,
    BellRing, FileText, Settings, Shield, CreditCard, Wrench, Receipt,
    Calendar, ClipboardList, PenLine, Layers, SlidersHorizontal, Bell, UserCircle, Inbox,
    TriangleAlert,
} from 'lucide-react';

// ─────────────────────────────────────────────────────────────────────────────
// Nav definition — single source of truth for the sidebar (used by AppSidebar).
// Mirrors the Blade menu and its @can guards. Each item may carry a `permission`
// (string | string[]) and optional `children`.
// ─────────────────────────────────────────────────────────────────────────────

const SETTINGS_CHILDREN = [
    { label: 'Account Setting',   route: 'setting.account',          permission: 'manage account settings' },
    { label: 'Password Setting',  route: 'setting.password',         permission: 'manage password settings' },
    { label: 'General Setting',   route: 'setting.general',          permission: 'manage general settings' },
    { label: 'Company Setting',   route: 'setting.company',          permission: 'manage company settings' },
    { label: 'Branding & Theme',  route: 'setting.branding',         permission: 'manage general settings' },
    { label: 'Email Setting',     route: 'setting.smtp',             permission: 'manage email settings' },
    { label: 'Site SEO Setting',  route: 'setting.site.seo',         permission: 'manage seo settings' },
    { label: 'ReCaptcha Setting', route: 'setting.google.recaptcha', permission: 'manage google recaptcha settings' },
];

const NAV_SUPER_ADMIN = [
    { section: 'Home', items: [
        { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    ]},
    { section: 'Users', items: [
        { label: 'Users', route: 'users.index', icon: Users, permission: 'manage user' },
    ]},
    { section: 'System', items: [
        // Super admin bypasses Gate checks in Blade — no permission guard on children
        { label: 'Settings', icon: Settings, children: SETTINGS_CHILDREN.map(({ permission: _, ...c }) => c) },
    ]},
];

const NAV_OWNER = [
    { section: 'Home', items: [
        { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    ]},
    { section: 'Staff', items: [
        { label: 'Roles', route: 'role.index',  icon: Shield, permission: 'manage role' },
        { label: 'Users', route: 'users.index', icon: Users,  permission: 'manage user' },
    ]},
    { section: 'Business', items: [
        { label: 'Drivers',          route: 'driver.index',           icon: UserCircle,    permission: 'manage driver' },
        { label: 'Vehicles',         route: 'vehicle.index',          icon: Car,           permission: 'manage vehicle' },
        { label: 'Bookings',         route: 'booking.index',          icon: CalendarCheck, permission: 'manage booking' },
        { label: 'Booking Requests', route: 'booking_requests.index', icon: ClipboardList, permission: 'manage booking', badge: 'Upcoming' },
        { label: 'Planning',         route: 'planning',               icon: Calendar,      permission: 'manage planning' },
        { label: 'Expenses',         route: 'expense.index',          icon: ReceiptText,   permission: 'manage expense' },
        { label: 'Traffic Violations', route: 'traffic-violation.index', icon: TriangleAlert, permission: 'manage traffic violation', feature: 'traffic_violations' },
        { label: 'Reminders',        route: 'reminder.index',         icon: BellRing,      permission: 'manage reminder' },
        { label: 'Inspections',      route: 'inspection.index',       icon: Wrench,        permission: 'manage inspection' },
        { label: 'Agreements',       route: 'rental-agreement.index', icon: FileText,      permission: 'manage rental agreement' },
        { label: 'Signature',        route: 'signature.index',        icon: PenLine,       permission: 'manage reminder' },
    ]},
    { section: 'Finance', items: [
        { label: 'Credits', route: 'credit.index', icon: CreditCard, permission: 'manage driver' },
        { label: 'TVA', icon: Receipt, permission: ['manage tva', 'manage tva report'], children: [
            { label: 'TVA Management', route: 'tva.index',  permission: 'manage tva' },
            { label: 'TVA Report',     route: 'tva.report', permission: 'manage tva report' },
        ]},
    ]},
    { section: 'System Setup', items: [
        { label: 'Types', icon: Layers, permission: ['manage vehicle type', 'manage inspection type', 'manage expense type'], children: [
            { label: 'Vehicle Type',    route: 'vehicle-type.index',    permission: 'manage vehicle type' },
            { label: 'Inspection Type', route: 'inspection-type.index', permission: 'manage inspection type' },
            { label: 'Expense Type',    route: 'expense-type.index',    permission: 'manage expense type' },
        ]},
        { label: 'Booking Setup', icon: SlidersHorizontal, permission: ['manage options', 'manage addon', 'manage place'], children: [
            { label: 'Options', route: 'option.index', permission: 'manage options' },
            { label: 'Addon',   route: 'addon.index',  permission: 'manage addon' },
            { label: 'Places',  route: 'place.index',  permission: 'manage place' },
        ]},
        { label: 'Email Notification', route: 'notification.index', icon: Bell, permission: 'manage notification' },
    ]},
    { section: 'System', items: [
        { label: 'Settings', icon: Settings, children: SETTINGS_CHILDREN },
    ]},
];

// Build the permission/feature-filtered nav for the current user.
export function useNavSections() {
    const { auth, client } = usePage().props;
    const isSuperAdmin = auth.user?.type === 'super admin';
    const can = (p) => !p || (Array.isArray(p) ? p.some((x) => auth.permissions.includes(x)) : auth.permissions.includes(p));
    const feat = (f) => !f || client?.features?.[f];

    const sections = isSuperAdmin ? NAV_SUPER_ADMIN : NAV_OWNER;

    return sections
        .filter((s) => feat(s.feature ?? null))
        .map((s) => ({
            ...s,
            items: s.items
                .filter((i) => can(i.permission ?? null) && feat(i.feature ?? null))
                .map((i) => i.children
                    ? { ...i, children: i.children.filter((c) => can(c.permission ?? null)) }
                    : i
                )
                .filter((i) => !i.children || i.children.length > 0),
        }))
        .filter((s) => s.items.length > 0);
}

export function initials(name) {
    if (!name) return '?';
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}
