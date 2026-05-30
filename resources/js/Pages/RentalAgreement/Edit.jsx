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
import AdminLayout from '@/Layouts/AdminLayout';

function splitDateTime(datetime) {
    if (!datetime) return { date: '', time: '' };
    const [date = '', time = ''] = datetime.split(' ');
    return { date, time: time.slice(0, 5) };
}

function RentalAgreementEdit({ agreement, vehicles, drivers, statuses }) {
    const { errors: serverErrors } = usePage().props;

    const start = splitDateTime(agreement.rental_start_date);
    const end = splitDateTime(agreement.rental_end_date);

    const { register, handleSubmit, setValue, formState: { isSubmitting } } = useForm({
        defaultValues: {
            driver: String(agreement.driver ?? ''),
            driver2: agreement.driver2 ? String(agreement.driver2) : 'none',
            vehicle: String(agreement.vehicle ?? ''),
            rental_start_date: start.date,
            rental_start_time: start.time,
            rental_end_date: end.date,
            rental_end_time: end.time,
            rental_duration: agreement.rental_duration ?? '',
            status: agreement.status ?? '',
            terms_condition: agreement.terms_condition ?? '',
            description: agreement.description ?? '',
        },
    });

    function onSubmit(data) {
        router.put(route('rental-agreement.update', agreement.id), {
            ...data,
            driver2: data.driver2 === 'none' ? '' : data.driver2,
        });
    }

    return (
        <form onSubmit={handleSubmit(onSubmit)}>
            <div className="space-y-6 p-6">
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <div className="space-y-1">
                                <Label>Driver</Label>
                                <Select defaultValue={String(agreement.driver ?? '')} onValueChange={(v) => setValue('driver', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {drivers.map((d) => (
                                            <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {serverErrors?.driver && <p className="text-sm text-destructive">{serverErrors.driver}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label>Driver 2 (optional)</Label>
                                <Select defaultValue={agreement.driver2 ? String(agreement.driver2) : 'none'} onValueChange={(v) => setValue('driver2', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">— None —</SelectItem>
                                        {drivers.map((d) => (
                                            <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Vehicle</Label>
                                <Select defaultValue={String(agreement.vehicle ?? '')} onValueChange={(v) => setValue('vehicle', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {vehicles.map((v) => (
                                            <SelectItem key={v.id} value={String(v.id)}>{v.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label>Rental Start Date &amp; Time</Label>
                                <div className="flex gap-2">
                                    <Input type="date" {...register('rental_start_date', { required: true })} />
                                    <Input type="time" {...register('rental_start_time', { required: true })} />
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Label>Rental End Date &amp; Time</Label>
                                <div className="flex gap-2">
                                    <Input type="date" {...register('rental_end_date', { required: true })} />
                                    <Input type="time" {...register('rental_end_time', { required: true })} />
                                </div>
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="rental_duration">Rental Duration (Days)</Label>
                                <Input id="rental_duration" type="number" {...register('rental_duration', { required: true })} />
                            </div>

                            <div className="space-y-1">
                                <Label>Status</Label>
                                <Select defaultValue={agreement.status} onValueChange={(v) => setValue('status', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {statuses?.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1 md:col-span-2">
                                <Label htmlFor="terms_condition">Terms &amp; Conditions</Label>
                                <Textarea id="terms_condition" rows={6} {...register('terms_condition')} />
                            </div>

                            <div className="space-y-1 md:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea id="description" rows={4} {...register('description')} />
                            </div>
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

RentalAgreementEdit.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'Rental Agreements', href: route('rental-agreement.index') },
        { label: 'Edit' },
    ]}>{page}</AdminLayout>
);
export default RentalAgreementEdit;
