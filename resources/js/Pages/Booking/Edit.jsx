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
import { Checkbox } from '@/components/ui/checkbox';
import AdminLayout from '@/Layouts/AdminLayout';
import axios from 'axios';

// Convert "YYYY/MM/DD HH:MM" or "YYYY-MM-DD HH:MM" to "YYYY-MM-DDTHH:MM"
function toDatetimeLocal(val) {
    if (!val) return '';
    return val.replace(/\//g, '-').replace(' ', 'T').slice(0, 16);
}

function formatDt(val) {
    return val ? val.replace('T', ' ') : '';
}

function BookingEdit({ booking, vehicles: initialVehicles, drivers, statuses, places, addons }) {
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
    const [priceBreakdown, setPriceBreakdown] = useState(null);

    const startDt = watch('start_date_time');
    const endDt = watch('end_date_time');
    const vehicleId = watch('vehicle');
    const pickupId = watch('pickup_address');
    const dropoffId = watch('drop_off_address');
    const discount = watch('discount');

    const apiWriting = useRef(false);

    function recalculate() {
        if (!vehicleId || !startDt || !endDt) return;
        axios.get(route('vehicle.rate.calculation'), {
            params: {
                vahicle_id: vehicleId,
                start_date_time: formatDt(startDt),
                end_date_time: formatDt(endDt),
                addons: selectedAddons,
                pickup_place: getValues('pickup_address'),
                drop_off_place: getValues('drop_off_address'),
                daily_price: getValues('daily_price'),
            },
        }).then((r) => {
            const res = typeof r.data === 'string' ? JSON.parse(r.data) : r.data;
            const total = (parseFloat(res.totalRate) || 0) + (parseFloat(res.addonAmount) || 0) + (parseFloat(res.placeAmount) || 0);
            const disc = parseFloat(getValues('discount')) || 0;
            const finalTotal = total - disc;
            apiWriting.current = true;
            setValue('amount', finalTotal);
            if (res.daily_price) setValue('daily_price', res.daily_price);
            setValue('details', JSON.stringify(res));
            apiWriting.current = false;
            setPriceBreakdown({ ...res, finalTotal, discountAmount: disc });
        }).catch(() => {});
    }

    // Recalculate when vehicle/dates/addons/places change — NOT daily_price (API sets that)
    useEffect(() => {
        if (apiWriting.current) return;
        recalculate();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [vehicleId, startDt, endDt, selectedAddons, pickupId, dropoffId]);

    useEffect(() => {
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
                                <Label htmlFor="start_date_time">Start Date & Time</Label>
                                <Input id="start_date_time" type="datetime-local" {...register('start_date_time', { required: true })} />
                                {serverErrors?.start_date_time && <p className="text-sm text-destructive">{serverErrors.start_date_time}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="end_date_time">End Date & Time</Label>
                                <Input id="end_date_time" type="datetime-local" {...register('end_date_time', { required: true })} />
                                {serverErrors?.end_date_time && <p className="text-sm text-destructive">{serverErrors.end_date_time}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label>Vehicle</Label>
                                <Select defaultValue={String(booking.vehicle ?? '')} onValueChange={(v) => setValue('vehicle', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select Vehicle" /></SelectTrigger>
                                    <SelectContent>
                                        {initialVehicles.map((v) => (
                                            <SelectItem key={v.id} value={String(v.id)}>{v.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Driver</Label>
                                <Select defaultValue={String(booking.driver ?? '')} onValueChange={(v) => setValue('driver', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select Driver" /></SelectTrigger>
                                    <SelectContent>
                                        {drivers.map((d) => (
                                            <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Pickup Address</Label>
                                <Select defaultValue={String(booking.pickup_address ?? '')} onValueChange={(v) => setValue('pickup_address', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select Pickup Address" /></SelectTrigger>
                                    <SelectContent>
                                        {places.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Drop Off Address</Label>
                                <Select defaultValue={String(booking.drop_off_address ?? '')} onValueChange={(v) => setValue('drop_off_address', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select Drop Off Address" /></SelectTrigger>
                                    <SelectContent>
                                        {places.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Status</Label>
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
                                <Label htmlFor="discount">Discount</Label>
                                <Input id="discount" type="number" step="any" min="0" placeholder="Enter discount" {...register('discount')} />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="daily_price">Price per day</Label>
                                <Input id="daily_price" type="number" step="any" min="0" {...register('daily_price')} onBlur={() => recalculate()} />
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea id="notes" placeholder="Enter notes" rows={2} {...register('notes')} />
                            </div>

                            {addons.length > 0 && (
                                <div className="space-y-2 md:col-span-2 lg:col-span-3">
                                    <Label>Addons</Label>
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
                                                <td className="pr-8 py-1 text-muted-foreground">Duration</td>
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
                                                <td className="pr-8 py-1 text-muted-foreground font-medium">Discount</td>
                                                <td className="font-medium">{priceBreakdown.discountAmount} Dh</td>
                                            </tr>
                                            <tr>
                                                <td className="pr-8 py-1 font-semibold">Total Amount</td>
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
                    <Button type="submit" disabled={isSubmitting}>Update</Button>
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
