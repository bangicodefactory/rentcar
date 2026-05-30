import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { RefreshCw, AlertTriangle } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import axios from 'axios';

const currentYear = new Date().getFullYear();

function TvaRenumber({ preview: initialPreview, selectedYear: initialYear, years }) {
    const [year, setYear] = useState(String(initialYear));
    const [preview, setPreview] = useState(initialPreview);
    const [loading, setLoading] = useState(false);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [confirmText, setConfirmText] = useState('');
    const [applying, setApplying] = useState(false);

    const allYears = years?.length
        ? years.map(String)
        : Array.from({ length: currentYear - 2019 }, (_, i) => String(currentYear - i));

    useEffect(() => {
        if (String(year) === String(initialYear)) return;
        setLoading(true);
        axios.get(route('tva.renumber.preview'), { params: { year } })
            .then((r) => setPreview(r.data))
            .catch(() => setPreview({ count: 0, records: [] }))
            .finally(() => setLoading(false));
    }, [year]);

    function openDialog() {
        setConfirmText('');
        setDialogOpen(true);
    }

    function applyRenumber() {
        if (confirmText !== 'RENUMBER') return;
        setApplying(true);
        router.post(route('tva.renumber.apply'), { year }, {
            onFinish: () => { setApplying(false); setDialogOpen(false); },
        });
    }

    const count = preview?.count ?? 0;
    const records = preview?.records ?? [];

    return (
        <div className="p-6 space-y-6">
            <h1 className="text-2xl font-semibold flex items-center gap-2">
                <RefreshCw className="h-6 w-6" /> Renumber TVA Invoices
            </h1>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                {/* Controls */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Renumber Invoices</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Resequence invoice numbers for the selected year, ordered by invoice date then ID.
                            Soft-deleted invoices are skipped.
                        </p>

                        <div className="space-y-1">
                            <Label>Year</Label>
                            <Select value={year} onValueChange={setYear}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {allYears.map((y) => (
                                        <SelectItem key={y} value={y}>{y}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">Invoices:</span>
                            <Badge variant="default" className="text-base px-3 py-1">{count}</Badge>
                        </div>

                        <Button
                            className="w-full"
                            disabled={count === 0 || loading}
                            onClick={openDialog}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" /> Apply Renumbering
                        </Button>
                    </CardContent>
                </Card>

                {/* Preview table */}
                <Card className="md:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center justify-between">
                            <span>Preview — {year}</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                Ordered by invoice date, then ID
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="max-h-[60vh] overflow-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>Invoice Date</TableHead>
                                        <TableHead>Current Number</TableHead>
                                        <TableHead>New Number</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">
                                                Loading…
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {!loading && records.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">
                                                No invoices found for {year}.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {!loading && records.map((rec, idx) => (
                                        <TableRow key={idx}>
                                            <TableCell>{idx + 1}</TableCell>
                                            <TableCell>{rec.date ?? '—'}</TableCell>
                                            <TableCell>
                                                <Badge variant="secondary" className="font-mono text-sm font-bold">
                                                    {rec.old_number ?? '—'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline" className="font-mono text-sm font-bold text-green-700 border-green-400">
                                                    {rec.new_number}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Confirm Dialog — requires typing RENUMBER */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-destructive" />
                            Confirm Renumbering
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <p className="text-sm">
                            You are about to renumber{' '}
                            <strong>{count}</strong> invoice(s) for year{' '}
                            <strong>{year}</strong>.
                        </p>
                        <div className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                            <AlertTriangle className="inline h-4 w-4 mr-1" />
                            This will overwrite existing facture numbers and <strong>cannot be undone</strong>.
                        </div>
                        <div className="space-y-1">
                            <Label>
                                Type <span className="font-mono font-bold">RENUMBER</span> to confirm
                            </Label>
                            <Input
                                value={confirmText}
                                onChange={(e) => setConfirmText(e.target.value)}
                                placeholder="RENUMBER"
                                className={confirmText === 'RENUMBER' ? 'border-green-500' : ''}
                                autoFocus
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
                        <Button
                            variant="destructive"
                            disabled={confirmText !== 'RENUMBER' || applying}
                            onClick={applyRenumber}
                        >
                            {applying ? 'Applying…' : 'Confirm & Apply'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

TvaRenumber.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'TVA', href: route('tva.index') },
        { label: 'Renumber' },
    ]}>{page}</AdminLayout>
);
export default TvaRenumber;
