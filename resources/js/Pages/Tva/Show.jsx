import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Eye, Pencil } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

function DetailRow({ label, value }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">{label}</p>
            <p className="text-sm font-medium">{value ?? '—'}</p>
        </div>
    );
}

function TvaShow({ tva }) {
    const t = useTranslation();
    const fmt = (n) => n !== null && n !== undefined ? Number(n).toFixed(2) : '—';

    return (
        <div className="p-6 max-w-3xl mx-auto">
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                    <Eye className="h-6 w-6" /> {t('TVA Details')}
                </h1>
                <Button variant="outline" size="sm" asChild>
                    <Link href={route('tva.edit', tva.id)}>
                        <Pencil className="mr-2 h-4 w-4" /> {t('Edit')}
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">{t('Invoice')} #{tva.facture_number}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-6">
                        <DetailRow label={t('Facture Number')} value={tva.facture_number} />
                        <DetailRow label={t('Facture Date')}   value={tva.facture_date} />
                        <DetailRow label={t('Client Name')}    value={tva.client_name} />
                        <DetailRow label={t('Duration (Quantity)')} value={tva.quantity} />
                        <DetailRow label={t('Unit Price HT')}  value={fmt(tva.unit_price_ht)} />
                        <DetailRow label={t('Total HT')}       value={fmt(tva.total_ht)} />
                        <DetailRow label={t('TVA (Tax)')}      value={fmt(tva.tva)} />
                        <DetailRow label={t('Montant TTC')}    value={fmt(tva.montant_ttc)} />
                        <DetailRow label={t('Vehicle')}        value={tva.designation} />
                        <DetailRow label={t('Pickup / Return Location')} value={tva.place_label || '-'} />
                        <DetailRow label={t('Location Charge TTC')}      value={fmt(tva.place_amount_ttc ?? 0)} />
                        <DetailRow label={t('ICE')}            value={tva.payment_method} />
                    </div>
                </CardContent>
            </Card>

            <div className="mt-4">
                <Button variant="outline" asChild>
                    <Link href={route('tva.index')}>{t('Back to TVA List')}</Link>
                </Button>
            </div>
        </div>
    );
}

TvaShow.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'TVA', href: route('tva.index') },
        { label: 'Details' },
    ]}>{page}</AdminLayout>
);
export default TvaShow;
