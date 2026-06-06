import { useState, useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { ConfirmProvider } from '@/components/ui/confirm-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { useTranslation } from '@/hooks/useTranslation';
import {
    LayoutDashboard, Users, Car, CalendarCheck, ReceiptText,
    BellRing, FileText, Settings, ChevronLeft, ChevronDown, Menu, LogOut,
    UserCircle, Shield, CreditCard, Wrench, Receipt, Tags,
    Calendar, ClipboardList, PenLine, Layers, SlidersHorizontal, Bell,
    Languages, Check,
} from 'lucide-react';

// ─────────────────────────────────────────────────────────────────────────────
// Nav definition
// ─────────────────────────────────────────────────────────────────────────────

// Settings sub-items — each gated by its own granular permission
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
        { label: 'Booking Requests', route: 'booking_requests.index', icon: ClipboardList, permission: 'manage booking' },
        { label: 'Planning',         route: 'planning',               icon: Calendar,      permission: 'manage planning' },
        { label: 'Expenses',         route: 'expense.index',          icon: ReceiptText,   permission: 'manage expense' },
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

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function useNavSections() {
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

function initials(name) {
    if (!name) return '?';
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

// ─────────────────────────────────────────────────────────────────────────────
// NavItem  (leaf) + NavCollapsible (parent with children)
// ─────────────────────────────────────────────────────────────────────────────

function NavLeaf({ item, collapsed }) {
    const { url } = usePage();
    const t = useTranslation();
    const isActive = url.startsWith(route(item.route));
    const Icon = item.icon;

    const link = (
        <Link
            href={route(item.route)}
            className={cn(
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                isActive && 'bg-sidebar-accent text-sidebar-accent-foreground font-medium',
                collapsed && 'justify-center px-2',
            )}
        >
            {Icon && <Icon className="h-4 w-4 shrink-0" />}
            {!collapsed && <span>{t(item.label)}</span>}
        </Link>
    );

    if (!collapsed) return link;

    return (
        <Tooltip>
            <TooltipTrigger asChild>{link}</TooltipTrigger>
            <TooltipContent side="right">{t(item.label)}</TooltipContent>
        </Tooltip>
    );
}

function NavCollapsible({ item, collapsed }) {
    const { url } = usePage();
    const t = useTranslation();
    const Icon = item.icon;
    const isAnyChildActive = item.children.some((c) => url.startsWith(route(c.route)));
    const [open, setOpen] = useState(isAnyChildActive);

    // Collapsed sidebar: show icon + tooltip with all children as links
    if (collapsed) {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        className={cn(
                            'flex cursor-pointer items-center justify-center rounded-md px-2 py-2 text-sm transition-colors',
                            'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            isAnyChildActive && 'bg-sidebar-accent text-sidebar-accent-foreground font-medium',
                        )}
                    >
                        <Icon className="h-4 w-4 shrink-0" />
                    </span>
                </TooltipTrigger>
                <TooltipContent side="right" className="p-2 min-w-[160px]">
                    <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t(item.label)}</p>
                    {item.children.map((c) => (
                        <Link key={c.route} href={route(c.route)} className="block py-0.5 text-sm hover:underline">
                            {t(c.label)}
                        </Link>
                    ))}
                </TooltipContent>
            </Tooltip>
        );
    }

    return (
        <div>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={cn(
                    'w-full flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                    'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                    isAnyChildActive && 'text-sidebar-accent-foreground font-medium',
                )}
            >
                <Icon className="h-4 w-4 shrink-0" />
                <span className="flex-1 text-left">{t(item.label)}</span>
                <ChevronDown className={cn('h-3 w-3 transition-transform duration-200', open && 'rotate-180')} />
            </button>
            {open && (
                <div className="ml-4 mt-0.5 space-y-0.5 border-l border-sidebar-border pl-3">
                    {item.children.map((child) => {
                        const isActive = url.startsWith(route(child.route));
                        return (
                            <Link
                                key={child.route}
                                href={route(child.route)}
                                className={cn(
                                    'block rounded-md px-3 py-1.5 text-sm transition-colors',
                                    'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                    isActive && 'bg-sidebar-accent text-sidebar-accent-foreground font-medium',
                                )}
                            >
                                {t(child.label)}
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function NavItem({ item, collapsed }) {
    if (item.children) return <NavCollapsible item={item} collapsed={collapsed} />;
    return <NavLeaf item={item} collapsed={collapsed} />;
}

// ─────────────────────────────────────────────────────────────────────────────
// SidebarContent (shared between desktop and mobile Sheet)
// ─────────────────────────────────────────────────────────────────────────────

function SidebarContent({ collapsed = false }) {
    const { branding } = usePage().props;
    const t = useTranslation();
    const sections = useNavSections();

    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className={cn('flex h-14 items-center border-b border-sidebar-border px-4', collapsed && 'justify-center px-2')}>
                <Link href={route('dashboard')} className="flex items-center gap-2">
                    <img
                        src={branding?.logoUrl}
                        alt={branding?.appName ?? 'Logo'}
                        className="h-8 w-auto object-contain"
                    />
                    {!collapsed && (
                        <span className="font-semibold text-sm truncate">
                            {branding?.appName}
                        </span>
                    )}
                </Link>
            </div>

            {/* Nav */}
            <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-4">
                {sections.map((section) => (
                    <div key={section.section}>
                        {!collapsed && (
                            <p className="mb-1 px-3 text-xs font-semibold text-sidebar-foreground/55 uppercase tracking-wider">
                                {t(section.section)}
                            </p>
                        )}
                        <div className="space-y-0.5">
                            {section.items.map((item) => (
                                <NavItem key={item.route ?? item.label} item={item} collapsed={collapsed} />
                            ))}
                        </div>
                        {!collapsed && <Separator className="mt-3 bg-sidebar-border" />}
                    </div>
                ))}
            </nav>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// UserMenu
// ─────────────────────────────────────────────────────────────────────────────

// Locales accepted by the SetLocale middleware ($supportedLanguages).
const LOCALES = [
    { code: 'en', label: 'English' },
    { code: 'fr', label: 'Français' },
    { code: 'ar', label: 'العربية' },
];

function LanguageSwitcher() {
    const { auth } = usePage().props;
    const current = auth?.user?.lang || 'fr';

    function change(code) {
        if (code === current) return;
        // GET /language/{lang} persists user.lang + session and redirects back.
        router.get(route('language.change', code), {}, { preserveScroll: true });
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Change language">
                    <Languages className="h-5 w-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                {LOCALES.map((l) => (
                    <DropdownMenuItem
                        key={l.code}
                        onClick={() => change(l.code)}
                        className={cn('cursor-pointer', current === l.code && 'font-semibold')}
                    >
                        <span className="flex-1">{l.label}</span>
                        {current === l.code && <Check className="ml-2 h-4 w-4" />}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function UserMenu() {
    const { auth } = usePage().props;
    const t = useTranslation();
    const user = auth.user;
    const canManageSettings = auth.user?.type === 'super admin' || [
        'manage general settings', 'manage account settings', 'manage password settings',
        'manage company settings', 'manage email settings', 'manage payment settings',
        'manage seo settings', 'manage google recaptcha settings',
    ].some((p) => auth.permissions.includes(p));
    const profileSrc = user?.profile
        ? `/storage/upload/profile/${user.profile}`
        : null;

    function logout() {
        router.post(route('logout'));
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="relative h-8 w-8 rounded-full">
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={profileSrc} alt={user?.name} />
                        <AvatarFallback>{initials(user?.name)}</AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-56" align="end" forceMount>
                <DropdownMenuLabel className="font-normal">
                    <div className="flex flex-col space-y-1">
                        <p className="text-sm font-medium">{user?.name}</p>
                        <p className="text-xs text-muted-foreground">{user?.email}</p>
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('setting.account')}>{t('Profile')}</Link>
                </DropdownMenuItem>
                {canManageSettings && (
                    <DropdownMenuItem asChild>
                        <Link href={route('setting.general')}>{t('Settings')}</Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={logout} className="text-destructive focus:text-destructive">
                    <LogOut className="mr-2 h-4 w-4" />
                    {t('Log out')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Breadcrumbs
// ─────────────────────────────────────────────────────────────────────────────

function Breadcrumbs({ items }) {
    const t = useTranslation();
    if (!items?.length) return null;

    // Labels are passed in English; translate here (a real component, so the
    // hook is valid — never call useTranslation() inside a page's static
    // `.layout` function, which Inertia invokes outside React's render tree).
    return (
        <nav aria-label="breadcrumb" className="flex items-center gap-1 text-sm text-muted-foreground">
            {items.map((crumb, i) => (
                <span key={i} className="flex items-center gap-1">
                    {i > 0 && <span className="select-none">/</span>}
                    {crumb.href
                        ? <Link href={crumb.href} className="hover:text-foreground transition-colors">{t(crumb.label)}</Link>
                        : <span className="text-foreground font-medium">{t(crumb.label)}</span>
                    }
                </span>
            ))}
        </nav>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// FlashToaster — reads flash shared prop and fires Sonner toasts
// ─────────────────────────────────────────────────────────────────────────────

function FlashToaster() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error)   toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// AdminLayout
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Persistent admin shell for all ported admin pages.
 *
 * Usage (in a page component):
 *   import AdminLayout from '@/Layouts/AdminLayout';
 *   SomePage.layout = (page) => <AdminLayout breadcrumbs={[{label:'Cars',href:route('vehicle.index')},{label:'Edit'}]}>{page}</AdminLayout>;
 *   export default function SomePage() { ... }
 *
 * The `breadcrumbs` prop is an array of { label, href? } items.
 */
export default function AdminLayout({ children, breadcrumbs }) {
    const [collapsed, setCollapsed] = useState(false);

    return (
        <TooltipProvider>
            <div className="flex h-screen overflow-hidden bg-background">

                {/* ── Desktop sidebar ── */}
                <aside
                    className={cn(
                        'hidden lg:flex flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-[width] duration-300 ease-out',
                        collapsed ? 'w-16' : 'w-60',
                    )}
                >
                    <SidebarContent collapsed={collapsed} />

                    {/* Collapse toggle */}
                    <div className="border-t border-sidebar-border p-2 flex justify-end">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setCollapsed((c) => !c)}
                            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                            className="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                        >
                            <ChevronLeft className={cn('h-4 w-4 transition-transform', collapsed && 'rotate-180')} />
                        </Button>
                    </div>
                </aside>

                {/* ── Main area ── */}
                <div className="flex flex-1 flex-col overflow-hidden">

                    {/* TopBar */}
                    <header className="flex h-14 items-center gap-3 border-b bg-card px-4">

                        {/* Mobile hamburger → Sheet */}
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button variant="ghost" size="icon" className="lg:hidden" aria-label="Open menu">
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-60 p-0 bg-sidebar text-sidebar-foreground border-sidebar-border">
                                <SidebarContent />
                            </SheetContent>
                        </Sheet>

                        {/* Breadcrumbs */}
                        <div className="flex-1">
                            <Breadcrumbs items={breadcrumbs} />
                        </div>

                        {/* Language switcher */}
                        <LanguageSwitcher />

                        {/* User menu */}
                        <UserMenu />
                    </header>

                    {/* Page content */}
                    <main className="flex-1 overflow-y-auto">
                        <ConfirmProvider>
                            {children}
                        </ConfirmProvider>
                    </main>
                </div>
            </div>

            {/* Flash toasts + Sonner container */}
            <FlashToaster />
            <Toaster richColors position="top-right" />
        </TooltipProvider>
    );
}
