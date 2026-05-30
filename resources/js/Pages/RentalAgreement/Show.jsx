import { usePage } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Printer } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';

const STATUS_VARIANT = {
    draft: 'secondary',
    pending: 'default',
    confirmed: 'outline',
    active: 'outline',
    completed: 'secondary',
    cancelled: 'destructive',
};

function Field({ label, value }) {
    return (
        <div>
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">{label}</p>
            <p className="mt-0.5">{value || '—'}</p>
        </div>
    );
}

function RentalAgreementShow({ agreement, settings, terms }) {
    return (
        <div className="space-y-6 p-6" id="agreement-print">

            <div className="flex justify-end print:hidden">
                <Button size="sm" variant="outline" onClick={() => window.print()}>
                    <Printer className="mr-2 h-4 w-4" /> Print
                </Button>
            </div>

            <Card>
                <CardContent className="pt-6 space-y-6">
                    {/* Header */}
                    <div className="flex flex-col md:flex-row md:justify-between gap-4">
                        <div className="flex items-start gap-4">
                            {settings?.company_logo && (
                                <img
                                    src={`/storage/upload/logo/${settings.company_logo}`}
                                    alt="logo"
                                    className="h-16 w-auto object-contain"
                                />
                            )}
                            <ul className="space-y-1 text-sm">
                                <li>{settings?.company_name}</li>
                                <li>{settings?.company_phone}</li>
                                <li>{settings?.company_email}</li>
                            </ul>
                        </div>
                        <ul className="space-y-1 text-sm text-right">
                            <li>IF: {settings?.if}</li>
                            <li>RC: {settings?.rc}</li>
                            <li>Patente: {settings?.patente}</li>
                            <li>ICE: {settings?.ice}</li>
                        </ul>
                    </div>

                    <Separator />

                    {/* Agreement details */}
                    <div>
                        <h5 className="font-semibold text-primary mb-3">Agreement</h5>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <Field label="Agreement ID" value={agreement.agreement_id} />
                            <Field label="Agreement Date" value={agreement.date} />
                            <Field label="Rental Start Date" value={agreement.rental_start_date} />
                            <Field label="Rental End Date" value={agreement.rental_end_date} />
                            <Field label="Rental Duration" value={`${agreement.rental_duration} Days`} />
                            <div>
                                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Status</p>
                                <Badge className="mt-0.5" variant={STATUS_VARIANT[agreement.status] ?? 'secondary'}>
                                    {agreement.status_label}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Driver 1 */}
                    <div>
                        <h5 className="font-semibold text-primary mb-3">Driver</h5>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <Field label="Name" value={agreement.driver1?.name} />
                            <Field label="License Number" value={agreement.driver1?.license_number} />
                            <Field label="Phone Number" value={agreement.driver1?.phone_number} />
                            <Field label="Address" value={agreement.driver1?.address} />
                            <Field label="Birth Date" value={agreement.driver1?.birth_date} />
                            <Field label="ID National" value={agreement.driver1?.reference} />
                        </div>
                    </div>

                    {/* Driver 2 (optional) */}
                    {agreement.driver2 && (
                        <>
                            <Separator />
                            <div>
                                <h5 className="font-semibold text-primary mb-3">Driver 2</h5>
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                    <Field label="Name" value={agreement.driver2?.name} />
                                    <Field label="License Number" value={agreement.driver2?.license_number} />
                                    <Field label="Phone Number" value={agreement.driver2?.phone_number} />
                                    <Field label="Address" value={agreement.driver2?.address} />
                                    <Field label="Birth Date" value={agreement.driver2?.birth_date} />
                                    <Field label="ID National" value={agreement.driver2?.reference} />
                                </div>
                            </div>
                        </>
                    )}

                    <Separator />

                    {/* Vehicle */}
                    <div>
                        <h5 className="font-semibold text-primary mb-3">Vehicle</h5>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <Field label="Vehicle" value={agreement.vehicle_name} />
                            <Field label="Model" value={agreement.vehicle_model} />
                            <Field label="License Plate" value={agreement.vehicle_plate} />
                        </div>
                    </div>

                    <Separator />

                    {/* Signatures */}
                    <div>
                        <h5 className="font-semibold mb-3">Signatures</h5>
                        <div className="grid grid-cols-3 gap-6">
                            <div>
                                <p className="text-sm font-medium mb-2">Signature</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium mb-2">Signature Client 1</p>
                                {agreement.driver1_signature
                                    ? <img src={agreement.driver1_signature} alt="Driver 1 signature" className="max-w-[150px] max-h-[80px] border-none block" />
                                    : <div className="border-b border-black w-40 h-8" />
                                }
                            </div>
                            <div>
                                <p className="text-sm font-medium mb-2">Signature Client 2</p>
                                {agreement.driver2_signature
                                    ? <img src={agreement.driver2_signature} alt="Driver 2 signature" className="max-w-[150px] max-h-[80px] border-none block" />
                                    : <div className="border-b border-black w-40 h-8" />
                                }
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Terms & Conditions */}
                    <div>
                        <h5 className="font-semibold text-primary mb-3">Terms &amp; Conditions</h5>
                        <div
                            className="text-sm prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{ __html: terms }}
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

RentalAgreementShow.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'Rental Agreements', href: route('rental-agreement.index') },
        { label: 'Details' },
    ]}>{page}</AdminLayout>
);
export default RentalAgreementShow;
