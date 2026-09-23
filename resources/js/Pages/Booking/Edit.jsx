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
import axios from 'axios';
// Shared formatter emits the "Y/m/d H:i" the backend parses with
// Carbon::createFromFormat — the old local helper left dashes, which Carbon
// rejected (JAVASCRIPT-4), so the rate/availability calls here silently failed.
import { formatDt } from '@/lib/datetime';

// Convert "YYYY/MM/DD HH:MM" or "YYYY-MM-DD HH:MM" to "YYYY-MM-DDTHH:MM"
function toDatetimeLocal(val) {
    if (!val) return '';
    return val.replace(/\//g, '-').replace(' ', 'T').slice(0, 16);
}

function BookingEdit({ booking, vehicles: initialVehicles, drivers, statuses, places, addons }) {
    const t = useTranslation();
    const { errors: serverErrors } = usePage().props;

    const existingAddons = booking.addon ? booking.addon.split(',').map((x) => x.trim()) : [];

    const { register, handleSubmit, watch, setValue, getValues, formState: { isSubmitting } } = useForm({
        defaultValues: {
            start_date_time: toDatetimeLocal(booking.start_date_time),
            end_date_time: toDatetimeLocal(booking.end_date_time),
            vehicle: String(booking.vehicle ?? ''),
            driver: String(booking.driver ?? ''),
            pickup_address: String(booking.pickup_address ?? ''),
            drop_off_address: String(booking.drop_off_address ?? ''),
            discount: booking.discount ?? '',
            status: booking.status ?? '',
            notes: booking.notes ?? '',
            daily_price: booking.daily_price_final ?? '',
            amount: booking.amount ?? '',
            details: typeof booking.details === 'string' ? booking.details : JSON.stringify(booking.details ?? {}),
        },
    });

    const [selectedAddons, setSelectedAddons] = useState(existingAddons);
    // Show the breakdown saved with the booking on load (Edit no longer
    // re-prices on open). Imported bookings have none, so nothing is shown
    // until a real change re-prices them.
    const [priceBreakdown, setPriceBreakdown] = useState(() => {
        let saved = booking.details;
        if (typeof saved === 'string') {
            try { saved = JSON.parse(saved); } catch { saved = null; }
        }
        if (!saved || !saved.duration) return null;
        return {
            ...saved,
            finalTotal: parseFloat(booking.amount) || 0,
            discountAmount: parseFloat(booking.discount) || 0,
        };
    });
    // Vehicle dropdown options. Seeded with the server's initial available list
    // (computed for the saved dates) and refreshed whenever the dates change.
    const [availableVehicles, setAvailableVehicles] = useState(initialVehicles);

    const startDt = watch('start_date_time');
    const endDt = watch('end_date_time');
    const vehicleId = watch('vehicle');
    const pickupId = watch('pickup_address');
    const dropoffId = watch('drop_off_address');
    const discount = watch('discount');

    const apiWriting = useRef(false);
    // Unlike create, edit loads a SAVED per-day price/amount. Skip the first
    // run of every re-pricing effect (vehicle, dates, addons/places) so opening
    // a booking never overwrites them — only recompute on a real change.
    const isFirstVehicleEffect = useRef(true);
    const isFirstDatesEffect = useRef(true);
    const isFirstExtrasEffect = useRef(true);
    const isFirstDiscountEffect = useRef(true);
    const rateRequestSeq = useRef(0);
    // True while a stock-rate lookup (car switch) hasn't come back yet.
    const vehicleRatePending = useRef(false);

    // dayChange mirrors create: false = recompute from the vehicle's stock rate
    // and auto-fill the per-day price (vehicle change, or dates with no price
    // yet); true = keep the per-day price in the field — the negotiated one —
    // for every day (date/price/addon/place edit).
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
            const total = (parseFloat(res.totalRate) || 0) + (parseFloat(res.addonAmount) || 0) + (parseFloat(res.placeAmount) || 0);
            const disc = parseFloat(getValues('discount')) || 0;
            const finalTotal = total - disc;
            apiWriting.current = true;
            setValue('amount', finalTotal);
            // Auto-fill the per-day price from the vehicle's rate only when it's
            // NOT a manual edit, so a typed override is preserved (create parity).
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

    // A per-day price is already set (saved/negotiated or typed) and belongs to
    // the current car. While a car switch's stock-rate lookup is in flight the
    // field still holds the OLD car's price, so don't reuse it. Imported
    // bookings store 0, which falls back to the vehicle's stock rate.
    const hasDailyPrice = () => !vehicleRatePending.current && parseFloat(getValues('daily_price')) > 0;

    // Preserve the saved price/amount on initial load; recompute only when the
    // user actually changes the vehicle or dates afterwards.

    // Vehicle change → a different car, so recompute from its stock rate and
    // auto-fill the per-day price.
    useEffect(() => {
        if (isFirstVehicleEffect.current) { isFirstVehicleEffect.current = false; return; }
        if (apiWriting.current) return;
        recalculate(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [vehicleId]);

    // Date change (e.g. extending a rental) → keep the negotiated per-day price
    // for every day, extra days included, instead of the vehicle's stock rate.
    useEffect(() => {
        if (isFirstDatesEffect.current) { isFirstDatesEffect.current = false; return; }
        if (apiWriting.current) return;
        recalculate(hasDailyPrice());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [startDt, endDt]);

    // Addons / pickup / drop-off change → recompute but PRESERVE the per-day
    // price in the field. Skipped on load like the effects above: imported
    // bookings store a 0 price next to their real amount, and re-pricing on
    // open showed (and on save stored) an amount of 0. With no price set (or a
    // car switch still pending) fall back to the car's stock rate, not 0/day.
    useEffect(() => {
        if (isFirstExtrasEffect.current) { isFirstExtrasEffect.current = false; return; }
        if (apiWriting.current) return;
        recalculate(hasDailyPrice());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedAddons, pickupId, dropoffId]);

    // Refresh the available-vehicle list whenever the dates change, mirroring the
    // create page. `booking_id` is passed so THIS booking isn't counted as a
    // conflict with itself. The request is aborted when the dates change again,
    // so a slow earlier response can't overwrite a newer one with a stale list.
    useEffect(() => {
        if (!startDt || !endDt) return;
        const controller = new AbortController();
        axios.get(route('available.vehicle'), {
            params: {
                start_date_time: formatDt(startDt),
                end_date_time: formatDt(endDt),
                booking_id: booking.id,
            },
            signal: controller.signal,
        }).then((r) => {
            const data = typeof r.data === 'string' ? JSON.parse(r.data) : r.data;
            const list = Object.entries(data).map(([id, label]) => ({ id: String(id), label }));
            // Keep the *currently-selected* vehicle in the list even if the new
            // window makes it conflict, so the picker never blanks out and
            // silently submits a hidden id. Use the live form value (the user may
            // have changed it) and recover its label from the previous/initial
            // lists. `prev` from the functional updater avoids a stale closure.
            const currentId = String(getValues('vehicle') ?? '');
            setAvailableVehicles((prev) => {
                if (!currentId || list.some((v) => v.id === currentId)) return list;
                const known = [...prev, ...initialVehicles].find((v) => String(v.id) === currentId);
                return known ? [{ id: currentId, label: known.label }, ...list] : list;
            });
        }).catch(() => {});
        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [startDt, endDt]);

    useEffect(() => {
        // Not on load: the saved amount stands until the discount changes.
        if (isFirstDiscountEffect.current) { isFirstDiscountEffect.current = false; return; }
        if (!priceBreakdown) return;
        const total = (parseFloat(priceBreakdown.totalRate) || 0)
            + (parseFloat(priceBreakdown.addonAmount) || 0)
            + (parseFloat(priceBreakdown.placeAmount) || 0);
        const disc = parseFloat(discount) || 0;
        setValue('amount', total - disc);
        setPriceBreakdown((prev) => prev ? { ...prev, finalTotal: total - disc, discountAmount: disc } : null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [discount]);

    function toggleAddon(id) {
        setSelectedAddons((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function onSubmit(data) {
        router.put(route('booking.update', booking.id), {
            ...data,
            start_date_time: formatDt(data.start_date_time),
            end_date_time: formatDt(data.end_date_time),
            addon: selectedAddons,
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
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Driver')}</Label>
                                <Select defaultValue={String(booking.driver ?? '')} onValueChange={(v) => setValue('driver', v)}>
                                    <SelectTrigger><SelectValue placeholder={t('Select Driver')} /></SelectTrigger>
                                    <SelectContent>
                                        {drivers.map((d) => (
                                            <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>{t('Pickup Address')}</Label>
                                <Select defaultValue={String(booking.pickup_address ?? '')} onValueChange={(v) => setValue('pickup_address', v)}>
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
                                <Select defaultValue={String(booking.drop_off_address ?? '')} onValueChange={(v) => setValue('drop_off_address', v)}>
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
                                <Select defaultValue={booking.status} onValueChange={(v) => setValue('status', v)}>
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
                                <Input id="discount" type="number" step="any" min="0" placeholder={t('Enter discount')} {...register('discount')} />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="daily_price">{t('Price per day')}</Label>
                                <Input id="daily_price" type="number" step="any" min="0" {...dailyPriceField} onChange={onDailyPriceTyped} onBlur={() => recalculate(!vehicleRatePending.current)} />
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
                    <Button type="submit" disabled={isSubmitting}>{t('Update')}</Button>
                </div>
            </div>
        </form>
    );
}

BookingEdit.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'Bookings', href: route('booking.index') },
        { label: 'Edit' },
    ]}>{page}</AdminLayout>
);
export default BookingEdit;
