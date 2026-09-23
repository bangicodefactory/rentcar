import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import BookingCreate from '@/Pages/Booking/Create';

// Once a per-day price has been typed (a negotiated rate), changing the dates
// must keep it (daychange=1) instead of resetting to the vehicle's stock rate.
// Picking a vehicle still auto-fills that vehicle's rate.

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
    router: { post: vi.fn() },
}));

vi.mock('@/components/ui/confirm-dialog', () => ({
    useConfirm: () => () => Promise.resolve(true),
    ConfirmProvider: ({ children }) => children,
}));

globalThis.route = (name) => `/${name}`;

const rateCalls = () => axios.get.mock.calls.filter((c) => c[0] === '/vehicle.rate.calculation');

beforeEach(() => {
    vi.clearAllMocks();
    usePage.mockReturnValue({ props: { translations: {}, errors: {} } });
    axios.get.mockImplementation((url) => {
        if (url === '/vehicle.rate.calculation') {
            return Promise.resolve({
                data: { considerDays: 7, totalRate: '1400', addonAmount: 0, placeAmount: 0, daily_price: 200 },
            });
        }
        return Promise.resolve({ data: { 1: 'Car A - 1-A-1', 2: 'Car B - 2-B-2' } });
    });
});

async function pickDatesAndVehicle(label = 'Car A - 1-A-1') {
    fireEvent.change(screen.getByLabelText('Start Date & Time'), { target: { value: '2026-10-01T09:00' } });
    fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-08T09:00' } });
    fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
    fireEvent.click(await screen.findByText(label));
    // Vehicle pick auto-fills the stock rate.
    await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('200'));
}

describe('Booking/Create — negotiated price per day on date change', () => {
    it('keeps a typed price per day when the end date changes', async () => {
        render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.change(screen.getByLabelText('Price per day'), { target: { value: '150' } });
        fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-11T09:00' } });

        await waitFor(() => {
            const last = rateCalls().at(-1)[1].params;
            expect(last.end_date_time).toBe('2026/10/11 09:00');
        });
        const params = rateCalls().at(-1)[1].params;
        expect(params.daychange).toBe(1);
        expect(String(params.daily_price)).toBe('150');
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('150'));
    });

    it('auto-fills the vehicle rate when no price has been entered yet', async () => {
        render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);
    });

    it('resets to the new vehicle rate when the vehicle is changed after a typed price', async () => {
        render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.change(screen.getByLabelText('Price per day'), { target: { value: '150' } });
        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car B - 2-B-2'));

        await waitFor(() => expect(String(rateCalls().at(-1)[1].params.vahicle_id)).toBe('2'));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('200'));
    });
});
