import { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { MonthPicker } from '@/components/ui/month-picker';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Eye, Pencil, Trash2, Plus, Upload, Download, Truck, Search, CheckCircle2, AlertTriangle, X } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination from '@/components/Pagination';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/components/ui/confirm-dialog';

// Colours mapped to the design-handoff semantic palette (info/warning/success/
// danger) so each state is distinct: scheduled=blue, active=amber, done=green,
// cancelled=red; paid=green, partial=amber, unpaid=red.
const STATUS_VARIANT = {
    yet_to_start: 'info',
    on_going: 'warning',
    completed: 'success',
    cancelled: 'destructive',
};

const PAYMENT_VARIANT = {
    paye: 'success',
    impaye: 'destructive',
    partiellement_paye: 'warning',
};

function BookingIndex({ bookings, statuses, paymentStatuses, paymentMethods = [], filters = {} }) {
    const t = useTranslation();
    const confirmDialog = useConfirm();
    const { auth, flash } = usePage().props;
    const can = (p) => auth.permissions.includes(p);

    // Rows the server skipped on a partial Excel import (parity with the old
    // Blade modal). Flashed by BookingController@importExcel, shared through
    // HandleInertiaRequests; present only on the render right after an import.
    const importSkipped = flash?.import_skipped ?? null;

    // Server-side filters (paginated list — filter on the server to cover all
    // pages). search is free-text; month is a YYYY-MM picker. Both are sent
    // together so changing one preserves the other.
    const [search, setSearch] = useState(filters.search ?? '');
    const [month, setMonth] = useState(filters.month ?? '');
    const [selected, setSelected] = useState([]);
    const [loadingAll, setLoadingAll] = useState(false);
    // Bulk "mark as paid" — opens a dialog to pick the payment method (and date)
    // before recording a payment + facture per selected booking.
    const [markPaidOpen, setMarkPaidOpen] = useState(false);
    const [payMethod, setPayMethod] = useState(paymentMethods?.[0]?.value ?? '');
    const isFirst = useRef(true);
    // Tracks the in-flight "select all matching" request so a filter change can
    // cancel it — otherwise a late response would repopulate the selection with
    // ids from the *previous* filter, over the new result set.
    const matchingReq = useRef(null);
    useEffect(() => {
        if (isFirst.current) {
            isFirst.current = false;
            return;
        }
        // Filter changed: drop any in-flight all-matching fetch from the old filter.
        matchingReq.current?.abort();
        const timer = setTimeout(() => {
            const params = {};
            if (search) params.search = search;
            if (month) params.month = month;
            // Drop the selection: the rows it referred to may be filtered out of
            // the new result set, and the bulk actions act on `selected` by id —
            // keeping it would let "Mark as Paid"/"Delete" hit off-screen rows.
            setSelected([]);
            router.get(
                route('booking.index'),
                params,
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);
        return () => clearTimeout(timer);
    }, [search, month]);

    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState(null);
    const importFileRef = useRef(null);

    function toggleAll(e) {
        setSelected(e.target.checked ? bookings.data.map((b) => b.id) : []);
    }

    function toggleOne(id) {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    // Pre-Inertia parity: the old client-side DataTable held every row in the
    // DOM, so "select all" reached the whole filtered list, not just one page.
    // Here the list is server-paginated, so fetch the ids for the active
    // search/month filter and select them all. The selection feeds the same
    // bulk endpoints, which re-check delete/edit permission server-side.
    async function selectAllMatching() {
        matchingReq.current?.abort();
        const controller = new AbortController();
        matchingReq.current = controller;
        setLoadingAll(true);
        try {
            const { data } = await axios.get(route('booking.matching-ids'), {
                params: {
                    ...(search ? { search } : {}),
                    ...(month ? { month } : {}),
                },
                signal: controller.signal,
            });
            setSelected(data.ids ?? []);
        } catch (err) {
            // A filter change aborts this request on purpose — ignore that; only
            // surface real failures (network error, non-2xx, malformed body).
            if (!axios.isCancel(err)) toast.error(t('Could not select all matching bookings.'));
        } finally {
            if (matchingReq.current === controller) matchingReq.current = null;
            setLoadingAll(false);
        }
    }

    // Every row on the current page is selected. Drives the header checkbox and
    // gates the "select all matching" prompt (shown only once the page is full
    // and more matching rows exist beyond it).
    const allOnPageSelected =
        bookings.data.length > 0 && bookings.data.every((b) => selected.includes(b.id));
    const canSelectAllMatching = allOnPageSelected && selected.length < bookings.total;

    async function remove(id) {
        if (await confirmDialog({ title: t('Delete this booking?') })) {
            router.delete(route('booking.destroy', id));
        }
    }

    async function bulkDelete() {
        if (!selected.length) return;
        if (!await confirmDialog({ title: `${t('Delete')} ${selected.length} ${t('selected booking(s)?')}` })) return;
        router.post(route('booking.bulk-destroy'), { ids: selected });
    }

    function submitMarkPaid(e) {
        e.preventDefault();
        if (!selected.length || !payMethod) return;
        router.post(route('booking.bulk-mark-paid'), {
            ids: selected,
            payment_method: payMethod,
        }, {
            onSuccess: () => { setSelected([]); setMarkPaidOpen(false); },
        });
    }

    function submitImport(e) {
        e.preventDefault();
        if (!importFile) return;
        router.post(route('booking.import'), { file: importFile }, {
            // Close only on a clean import. When the server skipped rows, the
            // redirect carries flash.import_skipped and the effect below reopens
            // the dialog so the user sees exactly which rows failed and why.
            onSuccess: (page) => {
                // Clear both the state and the (uncontrolled) file input element,
                // so a partial import that keeps the dialog open doesn't show a
                // stale filename whose re-submit would silently no-op.
                setImportFile(null);
                if (importFileRef.current) importFileRef.current.value = '';
                if (!page.props.flash?.import_skipped?.length) setImportOpen(false);
            },
        });
    }

    // Reopen the import dialog whenever the server reports skipped rows, so the
    // per-row error report is shown (matches the old Blade reopen_import_modal).
    useEffect(() => {
        if (importSkipped?.length) setImportOpen(true);
    }, [importSkipped]);

    const statusLabel = (s) => statuses?.find((x) => x.value === s)?.label ?? s;
    const payLabel = (s) => paymentStatuses?.find((x) => x.value === s)?.label ?? s;

    // Column count drives the empty-state colSpan; both the leading checkbox and
    // the trailing action column are permission-gated, so it can't be hardcoded.
    const hasActions = can('edit booking') || can('delete booking') || can('show booking');
    const colCount = 6 + (can('delete booking') ? 1 : 0) + (hasActions ? 1 : 0);

    return (
        <div className="space-y-6 p-6">
            <h1 className="text-3xl font-bold tracking-tight">{t('Bookings')}</h1>

            {/* Filters (search + month) on the left, actions on the right. The
                whole bar wraps when space runs short — so the action buttons
                that appear once rows are selected push to a second line rather
                than squeezing the search/filter controls. */}
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search bookings…')}
                            className="pl-8"
                        />
                    </div>
                    <div className="flex items-center gap-1">
                        <MonthPicker
                            value={month}
                            onChange={setMonth}
                            aria-label={t('Filter by month…')}
                            title={t('Filter by month…')}
                            className="w-[11.5rem]"
                        />
                        {month && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t('Clear')}
                                title={t('Clear')}
                                onClick={() => setMonth('')}
                                className="h-10 w-10 shrink-0 text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
                {/* Page-level actions only — these stay put. Bulk actions for a
                    selection live in a contextual bar on the table (below), so
                    selecting rows never reflows this toolbar. */}
                <div className="flex flex-wrap items-center gap-2">
                    {can('create booking') && (
                        <>
                            <Button variant="outline" size="sm" asChild>
                                <a href={route('booking.template')} target="_blank">
                                    <Download className="mr-2 h-4 w-4" /> {t('Template')}
                                </a>
                            </Button>
                            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Upload className="mr-2 h-4 w-4" /> {t('Import Excel')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="flex max-h-[90vh] flex-col gap-0 p-0 sm:max-w-3xl">
                                    <DialogHeader className="border-b px-6 py-4 text-left">
                                        <DialogTitle>{t('Import Bookings from Excel')}</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={submitImport} className="flex min-h-0 flex-1 flex-col">
                                        <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-6 py-4">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="importFile">{t('Excel File')}</Label>
                                                <Input
                                                    id="importFile"
                                                    ref={importFileRef}
                                                    type="file"
                                                    accept=".xlsx,.xls,.csv"
                                                    required
                                                    onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                                                />
                                                <p className="text-sm text-muted-foreground">
                                                    {t('Upload an .xlsx or .xls file. Download the')}{' '}
                                                    <a href={route('booking.template')} target="_blank" className="font-medium text-primary underline underline-offset-2">
                                                        {t('template')}
                                                    </a>{' '}
                                                    {t('to see the required format.')}
                                                </p>
                                            </div>
                                            <div className="rounded-md border bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                                <strong className="font-semibold text-foreground">{t('Format attendu (10 colonnes):')}</strong>
                                                <p className="mt-1 font-mono leading-relaxed">
                                                    NOM &amp; PRENOM | DATE DEBUT | HEURE | LA MARQUE | IMMATRICULATION | DATE RETOUR | HEURE RETOUR | PERIODE | PRIX | METHOD
                                                </p>
                                            </div>
                                            {importSkipped?.length > 0 && (
                                                <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-amber-300 bg-amber-50">
                                                    <div className="flex items-center gap-2 border-b border-amber-200 bg-amber-100/60 px-3 py-2 text-amber-900">
                                                        <AlertTriangle className="h-4 w-4 shrink-0" />
                                                        <strong className="text-sm font-semibold">
                                                            {importSkipped.length} {t('ligne(s) non importée(s):')}
                                                        </strong>
                                                    </div>
                                                    <div className="min-h-0 flex-1 overflow-auto">
                                                        <Table className="text-xs">
                                                            <TableHeader className="sticky top-0 z-10 bg-amber-100">
                                                                <TableRow className="hover:bg-transparent">
                                                                    <TableHead className="h-auto px-2 py-1.5 text-amber-900">#{t('Ligne')}</TableHead>
                                                                    <TableHead className="h-auto px-2 py-1.5 text-amber-900">{t('NOM & PRENOM')}</TableHead>
                                                                    <TableHead className="h-auto px-2 py-1.5 text-amber-900">{t('IMMATRICULATION')}</TableHead>
                                                                    <TableHead className="h-auto px-2 py-1.5 text-amber-900">{t('DATE DEBUT')}</TableHead>
                                                                    <TableHead className="h-auto px-2 py-1.5 text-amber-900">{t('DATE RETOUR')}</TableHead>
                                                                    <TableHead className="h-auto w-1/2 min-w-[18rem] px-2 py-1.5 text-amber-900">{t('Erreur(s)')}</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody>
                                                                {importSkipped.map((s, i) => (
                                                                    <TableRow key={i} className="border-amber-200">
                                                                        <TableCell className="px-2 py-1.5 font-medium tabular-nums">{s.row}</TableCell>
                                                                        <TableCell className="px-2 py-1.5">{s.nom}</TableCell>
                                                                        <TableCell className="px-2 py-1.5 whitespace-nowrap">{s.plaque}</TableCell>
                                                                        <TableCell className="px-2 py-1.5 whitespace-nowrap tabular-nums">{s.debut}</TableCell>
                                                                        <TableCell className="px-2 py-1.5 whitespace-nowrap tabular-nums">{s.retour}</TableCell>
                                                                        <TableCell className="w-1/2 min-w-[18rem] px-2 py-1.5 text-red-600">
                                                                            {(s.errors ?? []).join(' | ')}
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ))}
                                                            </TableBody>
                                                        </Table>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex justify-end gap-2 border-t px-6 py-4">
                                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>
                                                {t('Cancel')}
                                            </Button>
                                            <Button type="submit">
                                                <Upload className="mr-2 h-4 w-4" /> {t('Import')}
                                            </Button>
                                        </div>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </>
                    )}
                    {can('manage vehicle') && (
                        <Button size="sm" asChild>
                            <Link href={route('booking.create')}>
                                <Plus className="mr-2 h-4 w-4" /> {t('Create Booking')}
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="rounded-xl border bg-card overflow-hidden">
                    {/* Contextual selection bar: appears only with a selection and
                        carries the bulk actions, so the top toolbar never moves.
                        Sits on the table it acts on, tinted to read as a mode. */}
                    {selected.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-primary/5 px-4 py-2.5 duration-200 animate-in fade-in slide-in-from-top-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium">
                                    {selected.length} {t('booking(s) selected')}
                                </span>
                                {canSelectAllMatching && (
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="h-8 px-1"
                                        onClick={selectAllMatching}
                                        disabled={loadingAll}
                                    >
                                        {t('Select all matching')} ({bookings.total})
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 px-2 text-muted-foreground hover:text-foreground"
                                    onClick={() => setSelected([])}
                                >
                                    {t('Deselect all')}
                                </Button>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {can('create booking payment') && (
                                    <Dialog
                                        open={markPaidOpen}
                                        onOpenChange={(o) => {
                                            // Reset to the default method each time the dialog opens,
                                            // so a prior unsubmitted choice can't carry over.
                                            if (o) {
                                                setPayMethod(paymentMethods?.[0]?.value ?? '');
                                            }
                                            setMarkPaidOpen(o);
                                        }}
                                    >
                                        <DialogTrigger asChild>
                                            <Button variant="success" size="sm">
                                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                                {t('Mark as Paid')}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>{t('Mark as Paid')}</DialogTitle>
                                            </DialogHeader>
                                            <form onSubmit={submitMarkPaid} className="space-y-4">
                                                <p className="text-sm text-muted-foreground">
                                                    {selected.length} {t('booking(s) selected')}. {t('Each unpaid booking will be recorded as paid for its remaining balance with the method below, dated to the reservation start date.')}
                                                </p>
                                                <div className="space-y-1">
                                                    <Label>{t('Method')}</Label>
                                                    <Select value={payMethod} onValueChange={setPayMethod}>
                                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            {paymentMethods.map((m) => (
                                                                <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="flex justify-end gap-2">
                                                    <Button type="button" variant="outline" onClick={() => setMarkPaidOpen(false)}>{t('Close')}</Button>
                                                    <Button type="submit" variant="success" disabled={!payMethod}>{t('Mark as Paid')}</Button>
                                                </div>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                )}
                                {can('delete booking') && (
                                    <Button variant="destructive" size="sm" onClick={bulkDelete}>
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        {t('Delete Selected')}
                                    </Button>
                                )}
                            </div>
                        </div>
                    )}
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {can('delete booking') && (
                                    <TableHead style={{ width: 32 }}>
                                        <Checkbox
                                            aria-label={t('Select all bookings')}
                                            onCheckedChange={(v) => toggleAll({ target: { checked: v === true } })}
                                            checked={allOnPageSelected}
                                        />
                                    </TableHead>
                                )}
                                <TableHead>{t('ID')}</TableHead>
                                <TableHead>{t('Driver')}</TableHead>
                                <TableHead>{t('Vehicle')}</TableHead>
                                <TableHead>{t('Duration')}</TableHead>
                                <TableHead>{t('Status')}</TableHead>
                                <TableHead>{t('Payment')}</TableHead>
                                {(can('edit booking') || can('delete booking') || can('show booking')) && (
                                    <TableHead className="text-right">{t('Action')}</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {bookings.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell colSpan={colCount} className="py-14 text-center">
                                        <div className="mx-auto flex max-w-sm flex-col items-center gap-2 text-muted-foreground">
                                            <div className="flex h-11 w-11 items-center justify-center rounded-full bg-muted">
                                                <Truck className="h-5 w-5" />
                                            </div>
                                            <p className="text-sm font-medium text-foreground">
                                                {search || month ? t('No bookings match your filters') : t('No bookings yet')}
                                            </p>
                                            <p className="text-sm">
                                                {search || month
                                                    ? t('Try a different search or month.')
                                                    : t('Create a booking or import them from Excel to get started.')}
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )}
                            {bookings.data.map((b) => (
                                <TableRow
                                    key={b.id}
                                    data-state={selected.includes(b.id) ? 'selected' : undefined}
                                >
                                    {can('delete booking') && (
                                        <TableCell>
                                            <Checkbox
                                                aria-label={`${t('Select booking')} ${b.booking_id}`}
                                                checked={selected.includes(b.id)}
                                                onCheckedChange={() => toggleOne(b.id)}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell className="font-mono text-sm font-medium">{b.booking_id}</TableCell>
                                    <TableCell className="font-medium">{b.driver_name}</TableCell>
                                    <TableCell className="text-muted-foreground">{b.vehicle_label}</TableCell>
                                    <TableCell className="whitespace-nowrap text-sm tabular-nums">
                                        <div className="font-medium">
                                            {b.start_date} <span className="font-normal text-muted-foreground">{b.start_time}</span>
                                        </div>
                                        <div className="text-muted-foreground">{b.end_date} {b.end_time}</div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={STATUS_VARIANT[b.status] ?? 'secondary'}>
                                            {t(statusLabel(b.status))}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={PAYMENT_VARIANT[b.payment_status] ?? 'secondary'} className="capitalize">
                                            {t(payLabel(b.payment_status))}
                                        </Badge>
                                    </TableCell>
                                    {(can('edit booking') || can('delete booking') || can('show booking')) && (
                                        <TableCell className="text-right space-x-1">
                                            {can('show booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('booking.show', b.encrypted_id)} aria-label={t('View')}>
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('edit booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('booking.edit', b.encrypted_id)} aria-label={t('Edit')}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('delete booking') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => remove(b.id)}
                                                    aria-label={t('Delete')}
                                                    className="text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination paginator={bookings} />
                </div>
        </div>
    );
}

BookingIndex.layout = (page) => (
    <AdminLayout breadcrumbs={[{ label: 'Bookings' }]}>{page}</AdminLayout>
);
export default BookingIndex;
