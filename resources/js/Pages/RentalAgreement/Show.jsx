import { usePage } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Printer } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';

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
    const t = useTranslation();
    // Long terms (e.g. the directonderweg client, ~3.1k chars) overflow the
    // dedicated terms page onto a 3rd page; shorter ones (~1.9k chars) fit.
    // Flow long terms into two print columns so they fit one page → 2 pages
    // total, while short terms stay single-column (unchanged). Threshold sits
    // between the two clients' nl2br'd term lengths.
    const denseTerms = (terms?.length ?? 0) > 2400;
    // No print bottom-padding on the container: it would trail *after* the terms
    // block and, when the 2-column terms (directonderweg) reach near the page-2
    // bottom, the extra 14mm spills onto a blank 3rd page. The terms never reach
    // the sheet edge in the 2-page case, so the natural whitespace serves as the
    // bottom margin. (BAN-260 — don't re-add print:pb-* on #agreement-print.)
    return (
        <div className="space-y-6 p-6 print:px-[14mm] print:pt-[8mm]" id="agreement-print">

            <div className="flex justify-end print:hidden">
                <Button size="sm" variant="outline" onClick={() => window.print()}>
                    <Printer className="mr-2 h-4 w-4" /> {t('Print')}
                </Button>
            </div>

            <Card className="print:border-0 print:shadow-none">
                <CardContent className="pt-6 space-y-6 print:pt-2 print:space-y-2">
                    {/* Header — keep the company block (logo + text) and the
                        IF/RC/Patente/ICE block on the same row when printing.
                        (The print content width sits just under the `md` breakpoint,
                        so md:flex-row alone would stack them — force the row.) */}
                    <div className="flex flex-col md:flex-row md:justify-between gap-4 print:flex-row print:justify-between print:items-start">
                        <div className="flex items-start gap-4">
                            {settings?.company_logo && (
                                <img
                                    src={`/storage/upload/logo/${settings.company_logo}`}
                                    alt={t('logo')}
                                    className="h-16 w-auto object-contain print:h-12"
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
                        <h5 className="font-semibold text-primary mb-3 print:mb-1.5">{t('Agreement')}</h5>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <Field label={t('Agreement ID')} value={agreement.agreement_id} />
                            <Field label={t('Agreement Date')} value={agreement.date} />
                            <Field label={t('Rental Start Date')} value={agreement.rental_start_date} />
                            <Field label={t('Rental End Date')} value={agreement.rental_end_date} />
                            <Field label={t('Rental Duration')} value={`${agreement.rental_duration} ${t('Days')}`} />
                            <div>
                                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">{t('Status')}</p>
                                <Badge className="mt-0.5" variant={STATUS_VARIANT[agreement.status] ?? 'secondary'}>
                                    {agreement.status_label}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Driver 1 */}
                    <div>
                        <h5 className="font-semibold text-primary mb-3 print:mb-1.5">{t('Driver')}</h5>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <Field label={t('Name')} value={agreement.driver1?.name} />
                            <Field label={t('License Number')} value={agreement.driver1?.license_number} />
                            <Field label={t('Phone Number')} value={agreement.driver1?.phone_number} />
                            <Field label={t('Address')} value={agreement.driver1?.address} />
                            <Field label={t('Birth Date')} value={agreement.driver1?.birth_date} />
                            <Field label={t('ID National')} value={agreement.driver1?.reference} />
                        </div>
                    </div>

                    {/* Driver 2 (optional) */}
                    {agreement.driver2 && (
                        <>
                            <Separator />
                            <div>
                                <h5 className="font-semibold text-primary mb-3 print:mb-1.5">{t('Driver 2')}</h5>
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                    <Field label={t('Name')} value={agreement.driver2?.name} />
                                    <Field label={t('License Number')} value={agreement.driver2?.license_number} />
                                    <Field label={t('Phone Number')} value={agreement.driver2?.phone_number} />
                                    <Field label={t('Address')} value={agreement.driver2?.address} />
                                    <Field label={t('Birth Date')} value={agreement.driver2?.birth_date} />
                                    <Field label={t('ID National')} value={agreement.driver2?.reference} />
                                </div>
                            </div>
                        </>
                    )}

                    <Separator />

                    {/* Vehicle */}
                    <div>
                        <h5 className="font-semibold text-primary mb-3 print:mb-1.5">{t('Vehicle')}</h5>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <Field label={t('Vehicle')} value={agreement.vehicle_name} />
                            <Field label={t('Model')} value={agreement.vehicle_model} />
                            <Field label={t('License Plate')} value={agreement.vehicle_plate} />
                        </div>
                    </div>

                    <Separator />

                    {/* Signatures */}
                    <div>
                        <h5 className="font-semibold mb-3 print:mb-1.5">{t('Signatures')}</h5>
                        <div className="grid grid-cols-3 gap-6">
                            <div>
                                <p className="text-sm font-medium mb-2">{t('Signature')}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium mb-2">{t('Signature Client 1')}</p>
                                {agreement.driver1_signature
                                    ? <img src={agreement.driver1_signature} alt={t('Driver 1 signature')} loading="lazy" className="max-w-[150px] max-h-[80px] border-none block print:max-h-[56px]" />
                                    : <div className="border-b border-black w-40 h-8" />
                                }
                            </div>
                            <div>
                                <p className="text-sm font-medium mb-2">{t('Signature Client 2')}</p>
                                {agreement.driver2_signature
                                    ? <img src={agreement.driver2_signature} alt={t('Driver 2 signature')} loading="lazy" className="max-w-[150px] max-h-[80px] border-none block print:max-h-[56px]" />
                                    : <div className="border-b border-black w-40 h-8" />
                                }
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Terms & Conditions — start on its own page (page 2) and size
                        the type to comfortably fill that page (print:pt gives the
                        page-2 top margin, since @page margin is 0). Long terms flow
                        into two columns so they fit that single page (→ 2 pages
                        total); short terms stay single-column. */}
                    <div className="print:break-before-page print:pt-[14mm]">
                        <h5 className="font-semibold text-primary mb-3 print:mb-3">{t('Terms & Conditions')}</h5>
                        <div
                            className={cn(
                                'text-sm leading-relaxed max-w-none print:text-[13px] print:leading-[1.45]',
                                denseTerms && 'print:columns-2 print:[column-gap:1.5rem]',
                            )}
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
