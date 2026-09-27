import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Eye, Pencil, Trash2, Download, RefreshCw, Receipt, Send, Undo2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/components/ui/confirm-dialog';
import Pagination from '@/components/Pagination';
import axios from 'axios';

const MONTHS = [
    { value: '01', label: 'January' }, { value: '02', label: 'February' },
    { value: '03', label: 'March' }, { value: '04', label: 'April' },
    { value: '05', label: 'May' }, { value: '06', label: 'June' },
    { value: '07', label: 'July' }, { value: '08', label: 'August' },
    { value: '09', label: 'September' }, { value: '10', label: 'October' },
    { value: '11', label: 'November' }, { value: '12', label: 'December' },
];

const MONTHS_FR = [
    { value: '01', label: 'Janvier' }, { value: '02', label: 'Février' },
    { value: '03', label: 'Mars' }, { value: '04', label: 'Avril' },
    { value: '05', label: 'Mai' }, { value: '06', label: 'Juin' },
    { value: '07', label: 'Juillet' }, { value: '08', label: 'Août' },
    { value: '09', label: 'Septembre' }, { value: '10', label: 'Octobre' },
    { value: '11', label: 'Novembre' }, { value: '12', label: 'Décembre' },
];

const currentYear = new Date().getFullYear();
const YEARS = Array.from({ length: currentYear - 2019 }, (_, i) => currentYear - i);

function TvaIndex({ tvas, filters, all_ids = [] }) {
    const t = useTranslation();
    const tr = t; // rows below shadow `t`
    const confirmDialog = useConfirm();
    const { auth } = usePage().props;
    const can = (p) => auth.permissions.includes(p);

    // The Action column is permission-gated, so the empty-state colSpan can't be
    // hardcoded: 8 fixed columns (checkbox + 6 data + Method) plus the optional
    // Action column.
    const hasActions = can('show booking') || can('edit booking') || can('delete booking');
    const colCount = 8 + (hasActions ? 1 : 0);

    const [selected, setSelected] = useState([]);
    const [downloading, setDownloading] = useState(false);

    // Filter state — mirrors current URL params
    const [f, setF] = useState({
        from_date:    filters?.from_date    ?? '',
        to_date:      filters?.to_date      ?? '',
        driver_name:  filters?.driver_name  ?? '',
        filter_day:   filters?.filter_day   ?? '',
        filter_month: filters?.filter_month ?? '',
        filter_year:  filters?.filter_year  ?? '',
    });

    // Generate monthly TVA state
    const [genYear, setGenYear]   = useState(String(currentYear));
    const [genMonth, setGenMonth] = useState('01');

    function applyFilters() {
        router.get(route('tva.index'), Object.fromEntries(
            Object.entries(f).filter(([, v]) => v !== ''),
        ), { preserveState: true, replace: true });
    }

    function clearFilters() {
        setF({ from_date: '', to_date: '', driver_name: '', filter_day: '', filter_month: '', filter_year: '' });
        router.get(route('tva.index'), {}, { replace: true });
    }

    function toggleAll(e) {
        // Select-all spans every page that matches the current filters (all_ids),
        // so bulk download can cover the whole filtered set, not just this page.
        setSelected(e.target.checked ? all_ids : []);
    }

    function toggleOne(id) {
        setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    }

    // Download via axios so the request carries the always-fresh XSRF-TOKEN
    // cookie (X-XSRF-TOKEN header) — same mechanism as the rest of the app. The
    // previous hand-built <form> POST read a static <meta> CSRF token that goes
    // stale in the SPA (the <head> isn't re-rendered on Inertia navigations),
    // causing a 419 Page Expired (BAN-256).
    // "Sent" = handed to the client: the server locks the number (no edit,
    // delete, rebuild or renumber). Applies to the current selection.
    function setSent(sent) {
        if (!selected.length) return;
        router.post(route(sent ? 'tva.mark-sent' : 'tva.unmark-sent'), { ids: selected }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    }

    async function bulkDownload() {
        if (!selected.length || downloading) return;
        setDownloading(true);
        try {
            const res = await axios.post(
                route('tva.bulk.download'),
                { invoice_ids: selected },
                { responseType: 'blob' },
            );
            const disposition = res.headers['content-disposition'] ?? '';
            const match = /filename\*?=(?:UTF-8'')?["']?([^"';]+)/i.exec(disposition);
            const filename = match ? decodeURIComponent(match[1]) : 'invoices.zip';

            const url = window.URL.createObjectURL(new Blob([res.data]));
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (e) {
            window.alert(t('Could not generate the invoices. Please try again.'));
        } finally {
            setDownloading(false);
        }
    }

    function generateTva(e) {
        e.preventDefault();
        router.post(route('tva.generate'), {
            month: `${genYear}-${genMonth}`,
        });
    }

    async function remove(id) {
        if (await confirmDialog({ title: t('Delete this TVA invoice?') })) {
            router.delete(route('tva.destroy', id));
        }
    }

    return (
        <div className="space-y-6 p-6">
            <div className="flex items-center justify-between">
                <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                    <Receipt className="h-6 w-6" /> TVA
                </h1>
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('tva.renumber.index')}>
                        <RefreshCw className="mr-2 h-4 w-4" /> {t('Renumber Invoices')}
                    </Link>
                </Button>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="pt-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <div className="space-y-1">
                            <Label>{t('From Date')}</Label>
                            <DatePicker value={f.from_date} onChange={(v) => setF({ ...f, from_date: v })} />
                        </div>
                        <div className="space-y-1">
                            <Label>{t('To Date')}</Label>
                            <DatePicker value={f.to_date} onChange={(v) => setF({ ...f, to_date: v })} />
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Driver Name')}</Label>
                            <Input placeholder={t('Search by driver')} value={f.driver_name} onChange={(e) => setF({ ...f, driver_name: e.target.value })} />
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Day')}</Label>
                            <DatePicker value={f.filter_day} onChange={(v) => setF({ ...f, filter_day: v })} />
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Month')}</Label>
                            <Select value={f.filter_month || 'all'} onValueChange={(v) => setF({ ...f, filter_month: v === 'all' ? '' : v })}>
                                <SelectTrigger><SelectValue placeholder={t('Select Month')} /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All')}</SelectItem>
                                    {MONTHS.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Year')}</Label>
                            <Select value={f.filter_year || 'all'} onValueChange={(v) => setF({ ...f, filter_year: v === 'all' ? '' : v })}>
                                <SelectTrigger><SelectValue placeholder={t('Select Year')} /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All')}</SelectItem>
                                    {YEARS.map((y) => (
                                        <SelectItem key={y} value={String(y)}>{y}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="flex gap-2 mt-3">
                        <Button size="sm" onClick={applyFilters}>{t('Apply Filters')}</Button>
                        <Button size="sm" variant="outline" onClick={clearFilters}>{t('Clear')}</Button>
                    </div>
                </CardContent>
            </Card>

            {/* Generate Monthly TVA */}
            <Card>
                <CardContent className="pt-4">
                    <form onSubmit={generateTva} className="flex flex-wrap gap-3 items-end">
                        <div className="space-y-1">
                            <Label>{t('Année')}</Label>
                            <Select value={genYear} onValueChange={setGenYear}>
                                <SelectTrigger className="w-28"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((y) => <SelectItem key={y} value={String(y)}>{y}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>{t('Mois')}</Label>
                            <Select value={genMonth} onValueChange={setGenMonth}>
                                <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {MONTHS_FR.map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button type="submit">{t('Générer TVA')}</Button>
                    </form>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                        <span>{t('Invoices')} ({tvas.total})</span>
                        {selected.length > 0 && (
                            <span className="flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" onClick={() => setSent(true)}>
                                    <Send className="mr-2 h-4 w-4" />
                                    {`${t('Mark as sent')} (${selected.length})`}
                                </Button>
                                <Button size="sm" variant="outline" onClick={() => setSent(false)}>
                                    <Undo2 className="mr-2 h-4 w-4" />
                                    {t('Unmark sent')}
                                </Button>
                                <Button size="sm" variant="outline" onClick={bulkDownload} disabled={downloading}>
                                    <Download className="mr-2 h-4 w-4" />
                                    {downloading ? t('Preparing…') : `${t('Download Selected')} (${selected.length})`}
                                </Button>
                            </span>
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead style={{ width: 32 }}>
                                    <input type="checkbox"
                                        onChange={toggleAll}
                                        checked={selected.length === all_ids.length && all_ids.length > 0}
                                        ref={(el) => { if (el) el.indeterminate = selected.length > 0 && selected.length < all_ids.length; }}
                                    />
                                </TableHead>
                                <TableHead>{t('Facture N°')}</TableHead>
                                <TableHead>{t('Booking ID')}</TableHead>
                                <TableHead>{t('Driver')}</TableHead>
                                <TableHead>{t('Designation')}</TableHead>
                                <TableHead>{t('Date')}</TableHead>
                                <TableHead>{t('TTC')}</TableHead>
                                <TableHead>{t('Method')}</TableHead>
                                {hasActions && (
                                    <TableHead className="text-right">{t('Action')}</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tvas.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={colCount} className="text-center text-muted-foreground py-8">
                                        {t('No invoices found')}
                                    </TableCell>
                                </TableRow>
                            )}
                            {tvas.data.map((t) => (
                                <TableRow key={t.id}>
                                    <TableCell>
                                        <input type="checkbox"
                                            checked={selected.includes(t.id)}
                                            onChange={() => toggleOne(t.id)}
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {t.facture_number ?? <span className="text-muted-foreground">N/A</span>}
                                        {t.is_sent && (
                                            <Badge variant="secondary" className="ml-2 font-sans">{tr('Sent')}</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {t.booking_id_display ?? <span className="text-muted-foreground">N/A</span>}
                                    </TableCell>
                                    <TableCell>{t.driver_name ?? '—'}</TableCell>
                                    <TableCell>{t.designation || '—'}</TableCell>
                                    <TableCell>{t.facture_date}</TableCell>
                                    <TableCell className="font-medium">{t.montant_ttc} Dh</TableCell>
                                    <TableCell className="text-sm text-muted-foreground">{t.payment_method || '—'}</TableCell>
                                    {hasActions && (
                                        <TableCell className="text-right space-x-1">
                                            {can('show booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('tva.show', t.id)} aria-label="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('edit booking') && !t.is_sent && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('tva.edit', t.id)} aria-label="Edit">
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('delete booking') && !t.is_sent && (
                                                <Button variant="ghost" size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => remove(t.id)} aria-label="Delete">
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination paginator={tvas} />
                </CardContent>
            </Card>
        </div>
    );
}

TvaIndex.layout = (page) => (
    <AdminLayout breadcrumbs={[{ label: 'TVA' }]}>{page}</AdminLayout>
);
export default TvaIndex;
