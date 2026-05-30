import { useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import SignatureCanvas from 'react-signature-canvas';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { PenLine, Eraser, Save } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';

function SignatureCreate({ drivers }) {
    const { errors: serverErrors } = usePage().props;
    const sigCanvasRef = useRef(null);
    const [userId, setUserId] = useState('');
    const [isEmpty, setIsEmpty] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    function clear() {
        sigCanvasRef.current?.clear();
        setIsEmpty(true);
    }

    function handleEnd() {
        setIsEmpty(sigCanvasRef.current?.isEmpty() ?? true);
    }

    function submit(e) {
        e.preventDefault();
        if (!userId) return;
        if (sigCanvasRef.current?.isEmpty()) {
            alert('Please provide a signature before submitting.');
            return;
        }
        setSubmitting(true);
        const signatureData = sigCanvasRef.current.toDataURL('image/png');
        router.post(route('signature.store'), { user_id: userId, signature: signatureData }, {
            onFinish: () => setSubmitting(false),
        });
    }

    return (
        <div className="p-6 max-w-xl mx-auto space-y-6">
            <h1 className="text-2xl font-semibold flex items-center gap-2">
                <PenLine className="h-6 w-6" /> Create Signature
            </h1>

            <Card>
                <CardHeader>
                    <CardTitle>Signature Pad</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="space-y-5">

                        <div className="space-y-1">
                            <Label>Select Client</Label>
                            <Select onValueChange={setUserId} required>
                                <SelectTrigger><SelectValue placeholder="Select a driver/client" /></SelectTrigger>
                                <SelectContent>
                                    {drivers.map((d) => (
                                        <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {serverErrors?.user_id && (
                                <p className="text-sm text-destructive">{serverErrors.user_id}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label>Signature</Label>
                            <div className="border rounded-md overflow-hidden bg-white touch-none">
                                <SignatureCanvas
                                    ref={sigCanvasRef}
                                    penColor="black"
                                    backgroundColor="white"
                                    canvasProps={{
                                        className: 'w-full',
                                        style: { width: '100%', height: 200 },
                                    }}
                                    onEnd={handleEnd}
                                />
                            </div>
                            {serverErrors?.signature && (
                                <p className="text-sm text-destructive">{serverErrors.signature}</p>
                            )}
                        </div>

                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={clear}>
                                <Eraser className="mr-2 h-4 w-4" /> Clear
                            </Button>
                            <Button type="submit" disabled={submitting || isEmpty || !userId}>
                                <Save className="mr-2 h-4 w-4" /> Save Signature
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

SignatureCreate.layout = (page) => (
    <AdminLayout breadcrumbs={[{ label: 'Signatures', href: route('signature.index') }, { label: 'Create' }]}>{page}</AdminLayout>
);
export default SignatureCreate;
