import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Eye, Pencil, Trash2, Download, RefreshCw, Receipt } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';

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

function TvaIndex({ tvas, filters }) {
    const { auth } = usePage().props;
    const can = (p) => auth.permissions.includes(p);

    const [selected, setSelected] = useState([]);

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
    const [genNumber, setGenNumber] = useState('');

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
        setSelected(e.target.checked ? tvas.map((t) => t.id) : []);
    }

    function toggleOne(id) {
        setSelected((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
    }

    function bulkDownload() {
        if (!selected.length) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('tva.bulk.download');
        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        form.appendChild(csrf);
        selected.forEach((id) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'invoice_ids[]'; inp.value = id;
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function generateTva(e) {
        e.preventDefault();
        router.post(route('tva.generate'), {
            month: `${genYear}-${genMonth}`,
            tva_number: genNumber,
        });
    }

    function remove(id) {
        if (window.confirm('Delete this TVA invoice?')) {
            router.delete(route('tva.destroy', id));
        }
    }

    return (
        <div className="space-y-6 p-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold flex items-center gap-2">
                    <Receipt className="h-6 w-6" /> TVA
                </h1>
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('tva.renumber.index')}>
                        <RefreshCw className="mr-2 h-4 w-4" /> Renumber Invoices
                    </Link>
                </Button>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="pt-4">
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                        <div className="space-y-1">
                            <Label>From Date</Label>
                            <Input type="date" value={f.from_date} onChange={(e) => setF({ ...f, from_date: e.target.value })} />
                        </div>
                        <div className="space-y-1">
                            <Label>To Date</Label>
                            <Input type="date" value={f.to_date} onChange={(e) => setF({ ...f, to_date: e.target.value })} />
                        </div>
                        <div className="space-y-1">
                            <Label>Driver Name</Label>
                            <Input placeholder="Search by driver" value={f.driver_name} onChange={(e) => setF({ ...f, driver_name: e.target.value })} />
                        </div>
                        <div className="space-y-1">
                            <Label>Day</Label>
                            <Input type="date" value={f.filter_day} onChange={(e) => setF({ ...f, filter_day: e.target.value })} />
                        </div>
                        <div className="space-y-1">
                            <Label>Month</Label>
                            <Select value={f.filter_month || 'all'} onValueChange={(v) => setF({ ...f, filter_month: v === 'all' ? '' : v })}>
                                <SelectTrigger><SelectValue placeholder="Select Month" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    {MONTHS.map((m) => (
                                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Year</Label>
                            <Select value={f.filter_year || 'all'} onValueChange={(v) => setF({ ...f, filter_year: v === 'all' ? '' : v })}>
                                <SelectTrigger><SelectValue placeholder="Select Year" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    {YEARS.map((y) => (
                                        <SelectItem key={y} value={String(y)}>{y}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="flex gap-2 mt-3">
                        <Button size="sm" onClick={applyFilters}>Apply Filters</Button>
                        <Button size="sm" variant="outline" onClick={clearFilters}>Clear</Button>
                    </div>
                </CardContent>
            </Card>

            {/* Generate Monthly TVA */}
            <Card>
                <CardContent className="pt-4">
                    <form onSubmit={generateTva} className="flex flex-wrap gap-3 items-end">
                        <div className="space-y-1">
                            <Label>Année</Label>
                            <Select value={genYear} onValueChange={setGenYear}>
                                <SelectTrigger className="w-28"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((y) => <SelectItem key={y} value={String(y)}>{y}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Mois</Label>
                            <Select value={genMonth} onValueChange={setGenMonth}>
                                <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {MONTHS_FR.map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Numéro de TVA</Label>
                            <Input type="number" placeholder="Numéro de TVA" className="w-40"
                                value={genNumber} onChange={(e) => setGenNumber(e.target.value)} />
                        </div>
                        <Button type="submit">Générer TVA</Button>
                    </form>
                </CardContent>
            </Card>

            {/* Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                        <span>Invoices ({tvas.length})</span>
                        {selected.length > 0 && (
                            <Button size="sm" variant="outline" onClick={bulkDownload}>
                                <Download className="mr-2 h-4 w-4" /> Download Selected ({selected.length})
                            </Button>
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
                                        checked={selected.length === tvas.length && tvas.length > 0}
                                    />
                                </TableHead>
                                <TableHead>Facture N°</TableHead>
                                <TableHead>Booking ID</TableHead>
                                <TableHead>Driver</TableHead>
                                <TableHead>Designation</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>TTC</TableHead>
                                {(can('show booking') || can('edit booking') || can('delete booking')) && (
                                    <TableHead className="text-right">Action</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tvas.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-center text-muted-foreground py-8">
                                        No invoices found
                                    </TableCell>
                                </TableRow>
                            )}
                            {tvas.map((t) => (
                                <TableRow key={t.id}>
                                    <TableCell>
                                        <input type="checkbox"
                                            checked={selected.includes(t.id)}
                                            onChange={() => toggleOne(t.id)}
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {t.facture_number ?? <span className="text-muted-foreground">N/A</span>}
                                    </TableCell>
                                    <TableCell className="font-mono text-sm">
                                        {t.booking_id_display ?? <span className="text-muted-foreground">N/A</span>}
                                    </TableCell>
                                    <TableCell>{t.driver_name ?? '—'}</TableCell>
                                    <TableCell>{t.designation || '—'}</TableCell>
                                    <TableCell>{t.facture_date}</TableCell>
                                    <TableCell className="font-medium">{t.montant_ttc} Dh</TableCell>
                                    {(can('show booking') || can('edit booking') || can('delete booking')) && (
                                        <TableCell className="text-right space-x-1">
                                            {can('show booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('tva.show', t.id)} aria-label="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('edit booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('tva.edit', t.id)} aria-label="Edit">
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('delete booking') && (
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
                </CardContent>
            </Card>
        </div>
    );
}

TvaIndex.layout = (page) => (
    <AdminLayout breadcrumbs={[{ label: 'TVA' }]}>{page}</AdminLayout>
);
export default TvaIndex;
