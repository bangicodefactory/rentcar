import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Eye, Pencil, Trash2, Plus, Upload, Download, Truck } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';

const STATUS_VARIANT = {
    yet_to_start: 'default',
    on_going: 'secondary',
    completed: 'outline',
    cancelled: 'destructive',
};

const PAYMENT_VARIANT = {
    paye: 'outline',
    impaye: 'destructive',
    partiellement_paye: 'secondary',
};

function BookingIndex({ bookings, statuses, paymentStatuses }) {
    const { auth } = usePage().props;
    const can = (p) => auth.permissions.includes(p);

    const [selected, setSelected] = useState([]);
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState(null);

    function toggleAll(e) {
        setSelected(e.target.checked ? bookings.map((b) => b.id) : []);
    }

    function toggleOne(id) {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function remove(id) {
        if (window.confirm('Delete this booking?')) {
            router.delete(route('booking.destroy', id));
        }
    }

    function bulkDelete() {
        if (!selected.length) return;
        if (!window.confirm(`Delete ${selected.length} selected booking(s)?`)) return;
        router.post(route('booking.bulk-destroy'), { ids: selected });
    }

    function submitImport(e) {
        e.preventDefault();
        if (!importFile) return;
        router.post(route('booking.import'), { file: importFile }, {
            onSuccess: () => setImportOpen(false),
        });
    }

    const statusLabel = (s) => statuses?.find((x) => x.value === s)?.label ?? s;
    const payLabel = (s) => paymentStatuses?.find((x) => x.value === s)?.label ?? s;

    return (
        <div className="space-y-6 p-6">
            <div className="flex flex-wrap items-center gap-2 justify-between">
                <h1 className="text-2xl font-semibold">Bookings</h1>
                <div className="flex flex-wrap gap-2">
                    {selected.length > 0 && can('delete booking') && (
                        <Button variant="destructive" size="sm" onClick={bulkDelete}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete Selected ({selected.length})
                        </Button>
                    )}
                    {can('create booking') && (
                        <>
                            <Button variant="outline" size="sm" asChild>
                                <a href={route('booking.template')} target="_blank">
                                    <Download className="mr-2 h-4 w-4" /> Template
                                </a>
                            </Button>
                            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Upload className="mr-2 h-4 w-4" /> Import Excel
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Import Bookings from Excel</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={submitImport} className="space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            Upload an .xlsx or .xls file. Download the{' '}
                                            <a href={route('booking.template')} target="_blank" className="underline">
                                                template
                                            </a>{' '}
                                            to see the required format.
                                        </p>
                                        <div className="space-y-1">
                                            <Label htmlFor="importFile">Excel File</Label>
                                            <Input
                                                id="importFile"
                                                type="file"
                                                accept=".xlsx,.xls,.csv"
                                                required
                                                onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                                            />
                                        </div>
                                        <div className="text-xs text-muted-foreground bg-muted p-3 rounded">
                                            <strong>Format attendu (10 colonnes):</strong><br />
                                            NOM &amp; PRENOM | DATE DEBUT | HEURE | LA MARQUE | IMMATRICULATION | DATE RETOUR | HEURE RETOUR | PERIODE | PRIX | METHOD
                                        </div>
                                        <div className="flex justify-end gap-2">
                                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>
                                                Cancel
                                            </Button>
                                            <Button type="submit">
                                                <Upload className="mr-2 h-4 w-4" /> Import
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
                                <Plus className="mr-2 h-4 w-4" /> Create Booking
                            </Link>
                        </Button>
                    )}
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Truck className="h-5 w-5" /> All Bookings
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {can('delete booking') && (
                                    <TableHead style={{ width: 32 }}>
                                        <input
                                            type="checkbox"
                                            onChange={toggleAll}
                                            checked={selected.length === bookings.length && bookings.length > 0}
                                        />
                                    </TableHead>
                                )}
                                <TableHead>ID</TableHead>
                                <TableHead>Driver</TableHead>
                                <TableHead>Vehicle</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Payment</TableHead>
                                {(can('edit booking') || can('delete booking') || can('show booking')) && (
                                    <TableHead className="text-right">Action</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {bookings.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-center text-muted-foreground py-8">
                                        No bookings yet
                                    </TableCell>
                                </TableRow>
                            )}
                            {bookings.map((b) => (
                                <TableRow key={b.id}>
                                    {can('delete booking') && (
                                        <TableCell>
                                            <input
                                                type="checkbox"
                                                checked={selected.includes(b.id)}
                                                onChange={() => toggleOne(b.id)}
                                            />
                                        </TableCell>
                                    )}
                                    <TableCell className="font-mono text-sm">{b.booking_id}</TableCell>
                                    <TableCell>{b.driver_name}</TableCell>
                                    <TableCell>{b.vehicle_label}</TableCell>
                                    <TableCell className="text-sm">
                                        <div>{b.start_date} {b.start_time}</div>
                                        <div className="text-muted-foreground">{b.end_date} {b.end_time}</div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={STATUS_VARIANT[b.status] ?? 'secondary'}>
                                            {statusLabel(b.status)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={PAYMENT_VARIANT[b.payment_status] ?? 'secondary'}>
                                            {payLabel(b.payment_status)}
                                        </Badge>
                                    </TableCell>
                                    {(can('edit booking') || can('delete booking') || can('show booking')) && (
                                        <TableCell className="text-right space-x-1">
                                            {can('show booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('booking.show', b.encrypted_id)} aria-label="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('edit booking') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('booking.edit', b.encrypted_id)} aria-label="Edit">
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('delete booking') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => remove(b.id)}
                                                    aria-label="Delete"
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
                </CardContent>
            </Card>
        </div>
    );
}

BookingIndex.layout = (page) => (
    <AdminLayout breadcrumbs={[{ label: 'Bookings' }]}>{page}</AdminLayout>
);
export default BookingIndex;
