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
import { Switch } from '@/components/ui/switch';
import AdminLayout from '@/Layouts/AdminLayout';

function RentalAgreementCreate({ vehicles, drivers, statuses, defaultTerms }) {
    const { errors: serverErrors } = usePage().props;
    const { register, handleSubmit, setValue, watch, formState: { isSubmitting } } = useForm({
        defaultValues: {
            driver: '',
            driver2: '',
            vehicle: '',
            rental_start_date: '',
            rental_start_time: '',
            rental_end_date: '',
            rental_end_time: '',
            rental_duration: '',
            status: statuses?.[0]?.value ?? '',
            terms_condition: defaultTerms ?? '',
            description: '',
            create_booking: false,
        },
    });

    function onSubmit(data) {
        router.post(route('rental-agreement.store'), {
            ...data,
            driver2: data.driver2 === 'none' ? '' : data.driver2,
            create_booking: data.create_booking ? 1 : 0,
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
                                <Select onValueChange={(v) => setValue('driver', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select Driver" /></SelectTrigger>
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
                                <Select defaultValue="none" onValueChange={(v) => setValue('driver2', v)}>
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
                                <Select onValueChange={(v) => setValue('vehicle', v)}>
                                    <SelectTrigger><SelectValue placeholder="Select Vehicle" /></SelectTrigger>
                                    <SelectContent>
                                        {vehicles.map((v) => (
                                            <SelectItem key={v.id} value={String(v.id)}>{v.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {serverErrors?.vehicle && <p className="text-sm text-destructive">{serverErrors.vehicle}</p>}
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
                                <Input id="rental_duration" type="number" placeholder="Enter rental duration" {...register('rental_duration', { required: true })} />
                            </div>

                            <div className="space-y-1">
                                <Label>Status</Label>
                                <Select defaultValue={statuses?.[0]?.value} onValueChange={(v) => setValue('status', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {statuses?.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-3 pt-4">
                                <Switch
                                    id="create_booking"
                                    onCheckedChange={(v) => setValue('create_booking', v)}
                                />
                                <Label htmlFor="create_booking" className="cursor-pointer">Also create a Booking</Label>
                            </div>

                            <div className="space-y-1 md:col-span-2">
                                <Label htmlFor="terms_condition">Terms &amp; Conditions</Label>
                                <Textarea id="terms_condition" rows={6} {...register('terms_condition')} />
                            </div>

                            <div className="space-y-1 md:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea id="description" rows={4} placeholder="Enter description" {...register('description')} />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={isSubmitting}>Create</Button>
                </div>
            </div>
        </form>
    );
}

RentalAgreementCreate.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'Rental Agreements', href: route('rental-agreement.index') },
        { label: 'Create' },
    ]}>{page}</AdminLayout>
);
export default RentalAgreementCreate;
