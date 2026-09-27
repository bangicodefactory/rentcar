import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { usePage } from '@inertiajs/react';
import TvaRenumber from '@/Pages/Tva/Renumber';

// Sent invoices keep their number during a renumber: the preview flags them,
// and any conflict (a sent number that would break date order / leave a gap)
// blocks the apply button.

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
    Link: ({ href, children, ...rest }) => <a href={href} {...rest}>{children}</a>,
    router: { get: vi.fn(), post: vi.fn() },
}));
vi.mock('axios', () => ({ default: { get: vi.fn(() => new Promise(() => {})) } }));

globalThis.route = (name) => `/${name}`;

function renderPage(preview) {
    usePage.mockReturnValue({ props: { auth: { permissions: [] }, translations: {}, flash: {}, errors: {} } });
    return render(<TvaRenumber preview={preview} selectedYear={2026} years={[2026]} />);
}

const records = [
    { id: 1, old_number: '1', new_number: '1', date: '01/01/2026', sent: true },
    { id: 2, old_number: '9', new_number: '2', date: '02/01/2026', sent: false },
];

describe('Tva/Renumber — sent invoices', () => {
    it('flags sent rows as fixed and allows applying when there are changes and no conflicts', () => {
        renderPage({ year: 2026, count: 2, changes: 1, conflicts: [], records });

        expect(screen.getAllByText('Sent — fixed')).toHaveLength(1);
        expect(screen.getByRole('button', { name: /Apply Renumbering/ }).disabled).toBe(false);
    });

    it('lists conflicts and blocks applying', () => {
        renderPage({ year: 2026, count: 2, changes: 1, conflicts: ['Sent invoice #1 would break date order.'], records });

        expect(screen.getByText('Sent invoice #1 would break date order.')).toBeTruthy();
        expect(screen.getByRole('button', { name: /Apply Renumbering/ }).disabled).toBe(true);
    });

    it('has nothing to apply when no number would change', () => {
        renderPage({ year: 2026, count: 2, changes: 0, conflicts: [], records: records.map((r) => ({ ...r, new_number: r.old_number })) });

        expect(screen.getByRole('button', { name: /Apply Renumbering/ }).disabled).toBe(true);
    });
});
