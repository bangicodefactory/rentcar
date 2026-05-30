import { Link, router, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { Eye, Pencil, Trash2, Plus, FileText } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';

const STATUS_VARIANT = {
    draft: 'secondary',
    pending: 'default',
    confirmed: 'outline',
    active: 'outline',
    completed: 'secondary',
    cancelled: 'destructive',
};

function RentalAgreementIndex({ agreements, statuses }) {
    const { auth } = usePage().props;
    const can = (p) => auth.permissions.includes(p);

    const statusLabel = (s) => statuses?.find((x) => x.value === s)?.label ?? s;

    function remove(id) {
        if (window.confirm('Delete this rental agreement?')) {
            router.delete(route('rental-agreement.destroy', id));
        }
    }

    return (
        <div className="space-y-6 p-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Rental Agreements</h1>
                {can('manage rental agreement') && (
                    <Button size="sm" asChild>
                        <Link href={route('rental-agreement.create')}>
                            <Plus className="mr-2 h-4 w-4" /> Create Agreement
                        </Link>
                    </Button>
                )}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" /> All Agreements
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>ID</TableHead>
                                <TableHead>Driver</TableHead>
                                <TableHead>Vehicle</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Start</TableHead>
                                <TableHead>End</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>Status</TableHead>
                                {(can('edit rental agreement') || can('delete rental agreement') || can('show rental agreement')) && (
                                    <TableHead className="text-right">Action</TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {agreements.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={9} className="text-center text-muted-foreground py-8">
                                        No rental agreements yet
                                    </TableCell>
                                </TableRow>
                            )}
                            {agreements.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="font-mono text-sm">{a.agreement_id}</TableCell>
                                    <TableCell>{a.driver_name}</TableCell>
                                    <TableCell>{a.vehicle_label}</TableCell>
                                    <TableCell>{a.date}</TableCell>
                                    <TableCell>{a.rental_start_date}</TableCell>
                                    <TableCell>{a.rental_end_date}</TableCell>
                                    <TableCell>{a.rental_duration} Days</TableCell>
                                    <TableCell>
                                        <Badge variant={STATUS_VARIANT[a.status] ?? 'secondary'}>
                                            {statusLabel(a.status)}
                                        </Badge>
                                    </TableCell>
                                    {(can('edit rental agreement') || can('delete rental agreement') || can('show rental agreement')) && (
                                        <TableCell className="text-right space-x-1">
                                            {can('show rental agreement') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('rental-agreement.show', a.encrypted_id)} aria-label="View">
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('edit rental agreement') && (
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={route('rental-agreement.edit', a.id)} aria-label="Edit">
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            )}
                                            {can('delete rental agreement') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => remove(a.id)}
                                                    className="text-destructive hover:text-destructive"
                                                    aria-label="Delete"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    );
}

RentalAgreementIndex.layout = (page) => (
    <AdminLayout breadcrumbs={[{ label: 'Rental Agreements' }]}>{page}</AdminLayout>
);
export default RentalAgreementIndex;
