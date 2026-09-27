import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { SentBadge } from '@/components/SentBadge';
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
import { useTranslation } from '@/hooks/useTranslation';
import axios from 'axios';

const currentYear = new Date().getFullYear();

function TvaRenumber({ preview: initialPreview, selectedYear: initialYear, years }) {
    const t = useTranslation();
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
    // Sent invoices keep their number; `changes` is how many others move.
    const changes = preview?.changes ?? count;
    const conflicts = preview?.conflicts ?? [];

    return (
        <div className="p-6 space-y-6">
            <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                <RefreshCw className="h-6 w-6" /> {t('Renumber TVA Invoices')}
            </h1>

            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                {/* Controls */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Renumber Invoices')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {t('Resequence invoice numbers for the selected year, ordered by invoice date then ID. Soft-deleted invoices are skipped.')}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {t('Invoices marked as sent keep their number; the others fill the free numbers around them.')}
                        </p>

                        <div className="space-y-1">
                            <Label>{t('Year')}</Label>
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
                            <span className="text-sm text-muted-foreground">{t('Invoices:')}</span>
                            <Badge variant="default" className="text-base px-3 py-1">{count}</Badge>
                            <span className="text-sm text-muted-foreground">{t('Changes:')}</span>
                            <Badge variant="outline" className="text-base px-3 py-1">{changes}</Badge>
                        </div>

                        {conflicts.length > 0 && (
                            <div className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive space-y-1">
                                <p className="font-semibold flex items-center gap-1">
                                    <AlertTriangle className="h-4 w-4" /> {t('Renumbering is blocked:')}
                                </p>
                                <ul className="list-disc pl-5">
                                    {conflicts.map((c, i) => <li key={i}>{c}</li>)}
                                </ul>
                            </div>
                        )}

                        <Button
                            className="w-full"
                            disabled={changes === 0 || conflicts.length > 0 || loading}
                            onClick={openDialog}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" /> {t('Apply Renumbering')}
                        </Button>
                    </CardContent>
                </Card>

                {/* Preview table */}
                <Card className="md:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center justify-between">
                            <span>{t('Preview')} — {year}</span>
                            <span className="text-xs font-normal text-muted-foreground">
                                {t('Ordered by invoice date, then ID')}
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="max-h-[60vh] overflow-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">#</TableHead>
                                        <TableHead>{t('Invoice Date')}</TableHead>
                                        <TableHead>{t('Current Number')}</TableHead>
                                        <TableHead>{t('New Number')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">
                                                {t('Loading…')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {!loading && records.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">
                                                {t('No invoices found for')} {year}.
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
                                                {rec.sent && (
                                                    <SentBadge className="ml-2">{t('Sent — fixed')}</SentBadge>
                                                )}
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
                            {t('Confirm Renumbering')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <p className="text-sm">
                            {t('You are about to renumber')}{' '}
                            <strong>{changes}</strong> {t('invoice(s) for year')}{' '}
                            <strong>{year}</strong>.
                        </p>
                        <div className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                            <AlertTriangle className="inline h-4 w-4 mr-1" />
                            {t('This will overwrite existing facture numbers and')} <strong>{t('cannot be undone')}</strong>.
                        </div>
                        <div className="space-y-1">
                            <Label>
                                {t('Type')} <span className="font-mono font-bold">RENUMBER</span> {t('to confirm')}
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
                        <Button variant="outline" onClick={() => setDialogOpen(false)}>{t('Cancel')}</Button>
                        <Button
                            variant="destructive"
                            disabled={confirmText !== 'RENUMBER' || applying}
                            onClick={applyRenumber}
                        >
                            {applying ? t('Applying…') : t('Confirm & Apply')}
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
