import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';
import { usePage, router } from '@inertiajs/react';
import TvaIndex from '@/Pages/Tva/Index';

// A "sent" invoice has been handed to a client: the list flags it, hides its
// edit/delete actions (the server refuses them too), and the bulk selection
// can mark / unmark invoices as sent.

vi.mock('@/components/ui/confirm-dialog', () => ({
    useConfirm: () => () => Promise.resolve(true),
    ConfirmProvider: ({ children }) => children,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
    router: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

globalThis.route = (name, param) => `/${name}/${param ?? ''}`;

const row = (id, number, isSent) => ({
    id, facture_number: number, booking_id_display: '#BOK-0000001', driver_name: 'CLIENT ' + id,
    designation: 'CAR', facture_date: 'Aug 4, 2026', montant_ttc: 1000, payment_method: 'Carte', is_sent: isSent,
});

function renderIndex() {
    usePage.mockReturnValue({
        props: { auth: { permissions: ['show booking', 'edit booking', 'delete booking'] }, translations: {}, flash: {} },
    });
    return render(
        <TvaIndex
            tvas={{ data: [row(1, '788', true), row(2, '789', false)], total: 2, last_page: 1 }}
            filters={{}}
            all_ids={[1, 2]}
        />,
    );
}

const rowOf = (number) => screen.getByText(number).closest('tr');

beforeEach(() => vi.clearAllMocks());

describe('Tva/Index — sent invoices', () => {
    it('flags sent invoices and hides their edit/delete actions', () => {
        renderIndex();

        const sent = rowOf('788');
        expect(within(sent).getByText('Sent')).toBeTruthy();
        // Solid red so it stands out.
        expect(within(sent).getByText('Sent').className).toContain('bg-red-600');
        expect(within(sent).queryByLabelText('Edit')).toBeNull();
        expect(within(sent).queryByLabelText('Delete')).toBeNull();

        const unsent = rowOf('789');
        expect(within(unsent).queryByText('Sent')).toBeNull();
        expect(within(unsent).getByLabelText('Edit')).toBeTruthy();
        expect(within(unsent).getByLabelText('Delete')).toBeTruthy();
    });

    it('marks the selected invoices as sent, and unmarks them', () => {
        renderIndex();

        fireEvent.click(within(rowOf('789')).getByRole('checkbox'));
        fireEvent.click(screen.getByText('Mark as sent (1)'));
        expect(router.post).toHaveBeenCalledWith('/tva.mark-sent/', { ids: [2] }, expect.any(Object));

        fireEvent.click(screen.getByText('Unmark sent'));
        expect(router.post).toHaveBeenLastCalledWith('/tva.unmark-sent/', { ids: [2] }, expect.any(Object));
    });
});
