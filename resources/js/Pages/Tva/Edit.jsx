import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';
import { router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pencil } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';

const schema = z.object({
    facture_number: z.string().min(1, 'Required'),
    facture_date:   z.string().min(1, 'Required'),
    unit_price_ht:  z.coerce.number().min(0),
    total_ht:       z.coerce.number().min(0),
    tva:            z.coerce.number().min(0),
    montant_ttc:    z.coerce.number().min(0),
    place_label:      z.string().max(191).optional().or(z.literal('')),
    place_amount_ttc: z.coerce.number().min(0),
});

const TVA_RATE = 0.2;

function TvaEdit({ tva }) {
    const t = useTranslation();
    const { errors: serverErrors } = usePage().props;

    const {
        register,
        handleSubmit,
        watch,
        setValue,
        setError,
        formState: { errors, isSubmitting },
    } = useForm({
        resolver: zodResolver(schema),
        defaultValues: {
            facture_number: tva.facture_number ?? '',
            facture_date:   tva.facture_date   ?? '',
            unit_price_ht:  tva.unit_price_ht  ?? 0,
            total_ht:       tva.total_ht       ?? 0,
            tva:            tva.tva            ?? 0,
            montant_ttc:    tva.montant_ttc    ?? 0,
            place_label:      tva.place_label      ?? '',
            place_amount_ttc: tva.place_amount_ttc ?? 0,
        },
    });

    // Wire server-side validation errors into react-hook-form
    useEffect(() => {
        if (!serverErrors) return;
        Object.entries(serverErrors).forEach(([field, msg]) => {
            setError(field, { message: Array.isArray(msg) ? msg[0] : msg });
        });
    }, [serverErrors]);

    // Auto-calculate HT / TVA / TTC from (unit_price_ht x quantity) plus the
    // pickup / return charge, which is entered TTC because that is what the
    // invoice line prints. With no charge this is the original calculation.
    const unitPriceHt = watch('unit_price_ht');
    const placeAmount = watch('place_amount_ttc');
    useEffect(() => {
        const puht  = parseFloat(unitPriceHt) || 0;
        const qty   = parseFloat(tva.quantity) || 0;
        const place = parseFloat(placeAmount) || 0;
        const rentalTtc = puht * qty * (1 + TVA_RATE);
        const ttc = parseFloat((rentalTtc + place).toFixed(2));
        const ht  = parseFloat((ttc / (1 + TVA_RATE)).toFixed(2));
        const tvaAmt = parseFloat((ttc - ht).toFixed(2));
        setValue('total_ht',    ht,      { shouldValidate: false });
        setValue('tva',         tvaAmt,  { shouldValidate: false });
        setValue('montant_ttc', ttc,     { shouldValidate: false });
    }, [unitPriceHt, placeAmount, tva.quantity]);

    function onSubmit(data) {
        router.put(route('tva.update', tva.id), data);
    }

    return (
        <div className="p-6 max-w-3xl mx-auto">
            <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2 mb-6">
                <Pencil className="h-6 w-6" /> {t('Edit TVA')}
            </h1>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">{t('Invoice')} #{tva.facture_number}</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit(onSubmit)} className="grid grid-cols-1 gap-4 md:grid-cols-2">

                        {/* Booking — read-only display */}
                        <div className="space-y-1">
                            <Label>{t('Booking')}</Label>
                            <Input value={tva.booking_id ?? 'N/A'} readOnly className="bg-muted" />
                        </div>

                        {/* Vehicle / Designation — read-only */}
                        <div className="space-y-1">
                            <Label>{t('Vehicle')}</Label>
                            <Input value={tva.designation ?? ''} readOnly className="bg-muted" />
                        </div>

                        {/* Facture Number */}
                        <div className="space-y-1">
                            <Label htmlFor="facture_number">{t('Facture Number')}</Label>
                            <Input id="facture_number" {...register('facture_number')} />
                            {errors.facture_number && (
                                <p className="text-sm text-destructive">{errors.facture_number.message}</p>
                            )}
                        </div>

                        {/* Facture Date */}
                        <div className="space-y-1">
                            <Label htmlFor="facture_date">{t('Facture Date')}</Label>
                            <Input id="facture_date" type="date" {...register('facture_date')} />
                            {errors.facture_date && (
                                <p className="text-sm text-destructive">{errors.facture_date.message}</p>
                            )}
                        </div>

                        {/* Quantity — read-only */}
                        <div className="space-y-1">
                            <Label>{t('Quantity (Days)')}</Label>
                            <Input value={tva.quantity ?? ''} readOnly className="bg-muted" />
                        </div>

                        {/* Unit Price HT — triggers auto-calc */}
                        <div className="space-y-1">
                            <Label htmlFor="unit_price_ht">{t('Unit Price HT (P.U.H.T)')}</Label>
                            <Input
                                id="unit_price_ht"
                                type="number"
                                step="0.01"
                                {...register('unit_price_ht')}
                            />
                            {errors.unit_price_ht && (
                                <p className="text-sm text-destructive">{errors.unit_price_ht.message}</p>
                            )}
                        </div>

                        {/* Pickup / return location — printed as its own invoice line */}
                        <div className="space-y-1">
                            <Label htmlFor="place_label">{t('Pickup / Return Location')}</Label>
                            <Input id="place_label" {...register('place_label')} />
                            {errors.place_label && (
                                <p className="text-sm text-destructive">{errors.place_label.message}</p>
                            )}
                        </div>

                        {/* Location charge (TTC) — triggers auto-calc */}
                        <div className="space-y-1">
                            <Label htmlFor="place_amount_ttc">{t('Location Charge TTC')}</Label>
                            <Input
                                id="place_amount_ttc"
                                type="number"
                                step="0.01"
                                {...register('place_amount_ttc')}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('Added on top of the rental. Lower the unit price so the total still matches the payment.')}
                            </p>
                            {errors.place_amount_ttc && (
                                <p className="text-sm text-destructive">{errors.place_amount_ttc.message}</p>
                            )}
                        </div>

                        {/* Total HT — auto-calculated, read-only */}
                        <div className="space-y-1">
                            <Label htmlFor="total_ht">{t('Total HT')}</Label>
                            <Input
                                id="total_ht"
                                type="number"
                                step="0.01"
                                {...register('total_ht')}
                                readOnly
                                className="bg-muted"
                            />
                        </div>

                        {/* TVA — auto-calculated, read-only */}
                        <div className="space-y-1">
                            <Label htmlFor="tva">{t('TVA (20%)')}</Label>
                            <Input
                                id="tva"
                                type="number"
                                step="0.01"
                                {...register('tva')}
                                readOnly
                                className="bg-muted"
                            />
                        </div>

                        {/* Montant TTC — auto-calculated */}
                        <div className="space-y-1">
                            <Label htmlFor="montant_ttc">{t('Montant TTC')}</Label>
                            <Input
                                id="montant_ttc"
                                type="number"
                                step="0.01"
                                {...register('montant_ttc')}
                            />
                            {errors.montant_ttc && (
                                <p className="text-sm text-destructive">{errors.montant_ttc.message}</p>
                            )}
                        </div>

                        <div className="md:col-span-2 flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" onClick={() => router.get(route('tva.index'))}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={isSubmitting}>
                                {t('Update TVA')}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

TvaEdit.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'TVA', href: route('tva.index') },
        { label: 'Edit' },
    ]}>{page}</AdminLayout>
);
export default TvaEdit;
