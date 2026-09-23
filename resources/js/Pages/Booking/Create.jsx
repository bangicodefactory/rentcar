import { useState, useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { useForm } from 'react-hook-form';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Checkbox } from '@/components/ui/checkbox';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { confirmBlacklist } from '@/lib/blacklist';
import { formatDt } from '@/lib/datetime';
import { BlacklistNotice } from '@/components/BlacklistNotice';
import axios from 'axios';

function BookingCreate({ vehicles: initialVehicles, drivers, statuses, places, addons }) {
    const t = useTranslation();
    const { errors: serverErrors } = usePage().props;
    const confirm = useConfirm();

    const { register, handleSubmit, watch, setValue, getValues, formState: { isSubmitting } } = useForm({
        defaultValues: {
            start_date_time: '',
            end_date_time: '',
            vehicle: '',
            driver: '',
            pickup_address: '',
            drop_off_address: '',
            discount: '',
            status: statuses?.[0]?.value ?? '',
            notes: '',
            daily_price: '',
            amount: '',
            details: '',
        },
    });

    const [availableVehicles, setAvailableVehicles] = useState(initialVehicles);
    const [priceBreakdown, setPriceBreakdown] = useState(null);
    const [selectedAddons, setSelectedAddons] = useState([]);

    // Watch only the fields that TRIGGER an API call (not fields the API writes back)
    const startDt = watch('start_date_time');
    const endDt = watch('end_date_time');
    const vehicleId = watch('vehicle');
    const pickupId = watch('pickup_address');
    const dropoffId = watch('drop_off_address');
    const discount = watch('discount');

    // Ref guard: prevents recalculate from re-firing when it sets daily_price
    const apiWriting = useRef(false);
    const rateRequestSeq = useRef(0);
    // True while a stock-rate lookup (car switch) hasn't come back yet.
    const vehicleRatePending = useRef(false);


    // dayChange mirrors booking/create.blade.php: false = recompute from the
    // vehicle's stock rate and auto-fill the per-day price (vehicle change, or
    // dates with no price yet); true = use the per-day price in the field and
    // keep it (date/price/addon/place edit).
    function recalculate(dayChange = false) {
        if (!vehicleId || !startDt || !endDt) return;
        // Only the newest request may write back; a slow earlier reply must not
        // overwrite it (e.g. a car switch landing after a later date change).
        const seq = ++rateRequestSeq.current;
        if (!dayChange) vehicleRatePending.current = true;
        axios.get(route('vehicle.rate.calculation'), {
            params: {
                vahicle_id: vehicleId,
                start_date_time: formatDt(startDt),
                end_date_time: formatDt(endDt),
                addons: selectedAddons,
                pickup_place: getValues('pickup_address'),
                drop_off_place: getValues('drop_off_address'),
                daily_price: getValues('daily_price'),
                daychange: dayChange ? 1 : 0,
            },
        }).then((r) => {
            if (seq !== rateRequestSeq.current) return;
            vehicleRatePending.current = false;
            const res = typeof r.data === 'string' ? JSON.parse(r.data) : r.data;
            const total = (parseFloat(res.totalRate) || 0)
                + (parseFloat(res.addonAmount) || 0)
                + (parseFloat(res.placeAmount) || 0);
            const disc = parseFloat(getValues('discount')) || 0;
            const finalTotal = total - disc;

            // Guard: don't let these setValue calls trigger the useEffect below
            apiWriting.current = true;
            setValue('amount', finalTotal);
            // Auto-fill the per-day price from the vehicle's rate only when it's
            // NOT a manual edit, so a typed override is preserved (Blade parity).
            // A car with no set rate fills 0, so the previous car's price never lingers.
            if (!dayChange && res.daily_price != null) setValue('daily_price', res.daily_price);
            setValue('details', JSON.stringify(res));
            apiWriting.current = false;

            setPriceBreakdown({ ...res, finalTotal, discountAmount: disc });
        }).catch(() => {
            // Leave a failed car-switch lookup pending: the field still holds the
            // previous car's price, so the next change re-fetches the stock rate.
        });
    }

    // Fetch available vehicles when date range is set
    useEffect(() => {
        if (!startDt || !endDt) return;
        axios.get(route('available.vehicle'), {
            params: { start_date_time: formatDt(startDt), end_date_time: formatDt(endDt) },
        }).then((r) => {
            const data = r.data;
            const parsed = typeof data === 'string' ? JSON.parse(data) : data;
            setAvailableVehicles(Object.entries(parsed).map(([id, label]) => ({ id, label })));
        }).catch(() => {});
    }, [startDt, endDt]);

    // Typing a price is an explicit choice: it wins over a car switch's
    // in-flight stock-rate lookup, whose reply is dropped (the blur that follows
    // re-prices with the typed value). Any other in-flight recalculation (e.g. a
    // date change) is left to land, since Enter can submit without a blur.
    const dailyPriceField = register('daily_price');
    function onDailyPriceTyped(e) {
        if (vehicleRatePending.current) {
            ++rateRequestSeq.current;
            vehicleRatePending.current = false;
        }
        return dailyPriceField.onChange(e);
    }

    // A per-day price is already in the field (auto-filled or typed/negotiated)
    // and belongs to the current car. While a car switch's stock-rate lookup is
    // in flight the field still holds the OLD car's price, so don't reuse it.
    const hasDailyPrice = () => !vehicleRatePending.current && parseFloat(getValues('daily_price')) > 0;

    // Vehicle change → recompute from the vehicle's stock rate and auto-fill
    // the per-day price (Blade: #vehicle handler, daychange != 1).
    useEffect(() => {
        if (apiWriting.current) return;
        recalculate(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [vehicleId]);

    // Date change → keep a per-day price already entered (a negotiated rate)
    // for every day; only auto-fill the stock rate when none is set yet.
    useEffect(() => {
        if (apiWriting.current) return;
        recalculate(hasDailyPrice());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [startDt, endDt]);

    // Addons / pickup / drop-off change → recompute but PRESERVE a manually
    // entered per-day price (Blade: .addon / #pickup,#drop handlers, daychange = 1),
    // unless a car switch's rate is still pending.
    useEffect(() => {
        if (apiWriting.current) return;
        recalculate(!vehicleRatePending.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedAddons, pickupId, dropoffId]);

    // Update total locally when discount changes (no API needed)
    useEffect(() => {
        if (!priceBreakdown) return;
        const total = (parseFloat(priceBreakdown.totalRate) || 0)
            + (parseFloat(priceBreakdown.addonAmount) || 0)
            + (parseFloat(priceBreakdown.placeAmount) || 0);
        const disc = parseFloat(discount) || 0;
        const finalTotal = total - disc;
        setValue('amount', finalTotal);
        setPriceBreakdown((prev) => prev ? { ...prev, finalTotal, discountAmount: disc } : null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [discount]);

    function toggleAddon(id) {
        setSelectedAddons((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    async function onSubmit(data) {
        // Blacklist warning (BAN-252): let the owner decide; the server enforces.
        const { proceed, acknowledge } = await confirmBlacklist(drivers, [data.driver], confirm, t);
        if (!proceed) return; // declined → keep the form, post nothing
        router.post(route('booking.store'), {
            ...data,
            start_date_time: formatDt(data.start_date_time),
            end_date_time: formatDt(data.end_date_time),
            addon: selectedAddons,
            acknowledge_blacklist: acknowledge ? 1 : 0,
        });
    }

    return (
        <form onSubmit={handleSubmit(onSubmit)}>
            <input type="hidden" {...register('amount')} />
            <input type="hidden" {...register('details')} />

            <div className="space-y-6 p-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

                            <div className="space-y-1">
                                <Label htmlFor="start_date_time">{t('Start Date & Time')}</Label>
                                <Input id="start_date_time" type="datetime-local" {...register('start_date_time', { required: true })} />
                                {serverErrors?.start_date_time && <p className="text-sm text-destructive">{serverErrors.start_date_time}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="end_date_time">{t('End Date & Time')}</Label>
                                <Input id="end_date_time" type="datetime-local" {...register('end_date_time', { required: true })} />
                                {serverErrors?.end_date_time && <p className="text-sm text-destructive">{serverErrors.end_date_time}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Vehicle')}</Label>
                                <SearchableSelect
                                    options={availableVehicles.map((v) => ({ value: String(v.id), label: v.label }))}
                                    value={vehicleId}
                                    onChange={(v) => setValue('vehicle', v)}
                                    placeholder={t('Select Vehicle')}
                                    searchPlaceholder={t('Search vehicle…')}
                                    ariaLabel={t('Vehicle')}
                                />
                                {serverErrors?.vehicle && <p className="text-sm text-destructive">{serverErrors.vehicle}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Driver')}</Label>
                                <SearchableSelect
                                    options={drivers.map((d) => ({ value: String(d.id), label: d.name }))}
                                    value={watch('driver')}
                                    onChange={(v) => setValue('driver', v)}
                                    placeholder={t('Select Driver')}
                                    searchPlaceholder={t('Search driver…')}
                                    ariaLabel={t('Driver')}
                                />
                                {serverErrors?.driver && <p className="text-sm text-destructive">{serverErrors.driver}</p>}
                                <BlacklistNotice drivers={drivers} selectedIds={[watch('driver')]} />
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Pickup Address')}</Label>
                                <Select onValueChange={(v) => setValue('pickup_address', v)}>
                                    <SelectTrigger><SelectValue placeholder={t('Select Pickup Address')} /></SelectTrigger>
                                    <SelectContent>
                                        {places.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Drop Off Address')}</Label>
                                <Select onValueChange={(v) => setValue('drop_off_address', v)}>
                                    <SelectTrigger><SelectValue placeholder={t('Select Drop Off Address')} /></SelectTrigger>
                                    <SelectContent>
                                        {places.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Status')}</Label>
                                <Select defaultValue={statuses?.[0]?.value} onValueChange={(v) => setValue('status', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {statuses?.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="discount">{t('Discount')}</Label>
                                <Input id="discount" type="number" step="any" min="0" placeholder={t('Enter discount')} {...register('discount')} onBlur={() => recalculate(!vehicleRatePending.current)} />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="daily_price">{t('Price per day')}</Label>
                                <Input
                                    id="daily_price"
                                    type="number"
                                    step="any"
                                    min="0"
                                    {...dailyPriceField}
                                    onChange={onDailyPriceTyped}
                                    onBlur={() => recalculate(!vehicleRatePending.current)}
                                />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="notes">{t('Notes')}</Label>
                                <Textarea id="notes" placeholder={t('Enter notes')} rows={2} {...register('notes')} />
                            </div>

                            {addons.length > 0 && (
                                <div className="space-y-2 md:col-span-2 lg:col-span-3">
                                    <Label>{t('Addons')}</Label>
                                    <div className="flex flex-wrap gap-4">
                                        {addons.map((a) => (
                                            <div key={a.id} className="flex items-center gap-2">
                                                <Checkbox
                                                    id={`addon-${a.id}`}
                                                    checked={selectedAddons.includes(String(a.id))}
                                                    onCheckedChange={() => toggleAddon(String(a.id))}
                                                />
                                                <Label htmlFor={`addon-${a.id}`} className="font-normal cursor-pointer">{a.name}</Label>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Price Breakdown — multiple <tbody> siblings is valid HTML5 */}
                            {priceBreakdown && (
                                <div className="md:col-span-2 lg:col-span-3">
                                    <table className="w-auto text-sm border-collapse">
                                        <tbody>
                                            <tr>
                                                <td className="pr-8 py-1 text-muted-foreground">{t('Duration')}</td>
                                                <td dangerouslySetInnerHTML={{ __html: priceBreakdown.duration }} />
                                            </tr>
                                        </tbody>
                                        {priceBreakdown.specificAddonCalculation && (
                                            <tbody dangerouslySetInnerHTML={{ __html: priceBreakdown.specificAddonCalculation }} />
                                        )}
                                        {priceBreakdown.pickup_place && (
                                            <tbody dangerouslySetInnerHTML={{ __html: priceBreakdown.pickup_place }} />
                                        )}
                                        {priceBreakdown.drop_place && (
                                            <tbody dangerouslySetInnerHTML={{ __html: priceBreakdown.drop_place }} />
                                        )}
                                        <tbody>
                                            <tr>
                                                <td className="pr-8 py-1 text-muted-foreground font-medium">{t('Discount')}</td>
                                                <td className="font-medium">{priceBreakdown.discountAmount} Dh</td>
                                            </tr>
                                            <tr>
                                                <td className="pr-8 py-1 font-semibold">{t('Total Amount')}</td>
                                                <td className="font-semibold">{priceBreakdown.finalTotal} Dh</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={isSubmitting}>{t('Create')}</Button>
                </div>
            </div>
        </form>
    );
}

BookingCreate.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'Bookings', href: route('booking.index') },
        { label: 'Create' },
    ]}>{page}</AdminLayout>
);
export default BookingCreate;
