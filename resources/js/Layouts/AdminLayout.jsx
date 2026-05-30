import { useState, useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
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
import {
    LayoutDashboard, Users, Car, CalendarCheck, ReceiptText,
    BellRing, FileText, Settings, ChevronLeft, Menu, LogOut,
    UserCircle, Shield, CreditCard, Wrench,
} from 'lucide-react';

// ─────────────────────────────────────────────────────────────────────────────
// Nav definition
// ─────────────────────────────────────────────────────────────────────────────

const NAV_SUPER_ADMIN = [
    { section: 'Home', items: [
        { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    ]},
    { section: 'Users', items: [
        { label: 'Users', route: 'users.index', icon: Users, permission: 'manage user' },
    ]},
    { section: 'Subscriptions', feature: 'subscriptions', items: [
        { label: 'Subscriptions', route: 'subscriptions.index', icon: CreditCard, feature: 'subscriptions' },
    ]},
];

const NAV_OWNER = [
    { section: 'Home', items: [
        { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
    ]},
    { section: 'Staff', items: [
        { label: 'Roles',   route: 'role.index',    icon: Shield,     permission: 'manage role' },
        { label: 'Users',   route: 'users.index',   icon: Users,      permission: 'manage user' },
    ]},
    { section: 'Business', items: [
        { label: 'Drivers',   route: 'driver.index',    icon: UserCircle, permission: 'manage driver' },
        { label: 'Vehicles',  route: 'vehicle.index',   icon: Car,        permission: 'manage vehicle' },
        { label: 'Bookings',  route: 'booking.index',   icon: CalendarCheck, permission: 'manage booking' },
        { label: 'Expenses',  route: 'expense.index',   icon: ReceiptText,   permission: 'manage expense' },
        { label: 'Reminders', route: 'reminder.index',  icon: BellRing,      permission: 'manage reminder' },
        { label: 'Inspections', route: 'inspection.index', icon: Wrench,     permission: 'manage inspection' },
        { label: 'Agreements', route: 'rental-agreement.index', icon: FileText, permission: 'manage rental agreement' },
    ]},
    { section: 'Finance', items: [
        { label: 'Credits',  route: 'credit.index',  icon: CreditCard, permission: 'manage driver' },
    ]},
    { section: 'System', items: [
        { label: 'Settings', route: 'setting.general', icon: Settings, permission: 'manage setting' },
    ]},
];

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function useNavSections() {
    const { auth, client } = usePage().props;
    const isSuperAdmin = auth.user?.type === 'super admin';
    const can = (p) => !p || auth.permissions.includes(p);
    const feat = (f) => !f || client?.features?.[f];

    const sections = isSuperAdmin ? NAV_SUPER_ADMIN : NAV_OWNER;

    return sections
        .filter((s) => feat(s.feature ?? null))
        .map((s) => ({
            ...s,
            items: s.items.filter((i) => can(i.permission ?? null) && feat(i.feature ?? null)),
        }))
        .filter((s) => s.items.length > 0);
}

function initials(name) {
    if (!name) return '?';
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

// ─────────────────────────────────────────────────────────────────────────────
// NavItem
// ─────────────────────────────────────────────────────────────────────────────

function NavItem({ item, collapsed }) {
    const { url } = usePage();
    const isActive = url.startsWith(route(item.route));
    const Icon = item.icon;

    const link = (
        <Link
            href={route(item.route)}
            className={cn(
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                'hover:bg-accent hover:text-accent-foreground',
                isActive && 'bg-accent text-accent-foreground font-medium',
                collapsed && 'justify-center px-2',
            )}
        >
            <Icon className="h-4 w-4 shrink-0" />
            {!collapsed && <span>{item.label}</span>}
        </Link>
    );

    if (!collapsed) return link;

    return (
        <Tooltip>
            <TooltipTrigger asChild>{link}</TooltipTrigger>
            <TooltipContent side="right">{item.label}</TooltipContent>
        </Tooltip>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// SidebarContent (shared between desktop and mobile Sheet)
// ─────────────────────────────────────────────────────────────────────────────

function SidebarContent({ collapsed = false }) {
    const { branding } = usePage().props;
    const sections = useNavSections();

    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className={cn('flex h-14 items-center border-b px-4', collapsed && 'justify-center px-2')}>
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
                            <p className="mb-1 px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                {section.section}
                            </p>
                        )}
                        <div className="space-y-0.5">
                            {section.items.map((item) => (
                                <NavItem key={item.route} item={item} collapsed={collapsed} />
                            ))}
                        </div>
                        {!collapsed && <Separator className="mt-3" />}
                    </div>
                ))}
            </nav>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// UserMenu
// ─────────────────────────────────────────────────────────────────────────────

function UserMenu() {
    const { auth } = usePage().props;
    const user = auth.user;
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
                    <Link href={route('setting.account')}>Profile</Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={route('setting.general')}>Settings</Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={logout} className="text-destructive focus:text-destructive">
                    <LogOut className="mr-2 h-4 w-4" />
                    Log out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Breadcrumbs
// ─────────────────────────────────────────────────────────────────────────────

function Breadcrumbs({ items }) {
    if (!items?.length) return null;

    return (
        <nav aria-label="breadcrumb" className="flex items-center gap-1 text-sm text-muted-foreground">
            {items.map((crumb, i) => (
                <span key={i} className="flex items-center gap-1">
                    {i > 0 && <span className="select-none">/</span>}
                    {crumb.href
                        ? <Link href={crumb.href} className="hover:text-foreground transition-colors">{crumb.label}</Link>
                        : <span className="text-foreground font-medium">{crumb.label}</span>
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
                        'hidden lg:flex flex-col border-r bg-card transition-all duration-300',
                        collapsed ? 'w-16' : 'w-60',
                    )}
                >
                    <SidebarContent collapsed={collapsed} />

                    {/* Collapse toggle */}
                    <div className="border-t p-2 flex justify-end">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setCollapsed((c) => !c)}
                            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
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
                            <SheetContent side="left" className="w-60 p-0">
                                <SidebarContent />
                            </SheetContent>
                        </Sheet>

                        {/* Breadcrumbs */}
                        <div className="flex-1">
                            <Breadcrumbs items={breadcrumbs} />
                        </div>

                        {/* User menu */}
                        <UserMenu />
                    </header>

                    {/* Page content */}
                    <main className="flex-1 overflow-y-auto">
                        {children}
                    </main>
                </div>
            </div>

            {/* Flash toasts + Sonner container */}
            <FlashToaster />
            <Toaster richColors position="top-right" />
        </TooltipProvider>
    );
}
