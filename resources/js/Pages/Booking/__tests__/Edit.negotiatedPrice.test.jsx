import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import BookingEdit from '@/Pages/Booking/Edit';

// A negotiated per-day price (e.g. 150 instead of the vehicle's 200) must
// survive an extension: moving the end date re-prices every day at the saved
// price (daychange=1), not the vehicle's stock rate. Changing the vehicle still
// resets to that vehicle's rate, and a booking with no saved price (imports
// store 0) falls back to the stock rate.

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
    router: { put: vi.fn() },
}));

globalThis.route = (name) => `/${name}`;

const vehicles = [
    { id: 1, label: 'Car A - 1-A-1' },
    { id: 2, label: 'Car B - 2-B-2' },
];

function makeBooking(overrides = {}) {
    return {
        id: 42,
        vehicle: 1,
        driver: '',
        start_date_time: '2026/10/01 09:00',
        end_date_time: '2026/10/08 09:00',
        pickup_address: '',
        drop_off_address: '',
        addon: '',
        discount: '',
        status: 'yet_to_start',
        notes: '',
        daily_price_final: 150,
        amount: 1050,
        details: {},
        ...overrides,
    };
}

function renderEdit(booking) {
    return render(
        <BookingEdit
            booking={booking}
            vehicles={vehicles}
            drivers={[]}
            statuses={[]}
            places={[]}
            addons={[]}
        />,
    );
}

const rateCalls = () => axios.get.mock.calls.filter((c) => c[0] === '/vehicle.rate.calculation');

beforeEach(() => {
    vi.clearAllMocks();
    usePage.mockReturnValue({ props: { translations: {}, errors: {} } });
    axios.get.mockImplementation((url) => {
        if (url === '/vehicle.rate.calculation') {
            return Promise.resolve({
                data: { considerDays: 10, totalRate: '2000', addonAmount: 0, placeAmount: 0, daily_price: 200 },
            });
        }
        return Promise.resolve({ data: { 1: 'Car A - 1-A-1', 2: 'Car B - 2-B-2' } });
    });
});

describe('Booking/Edit — negotiated price per day on date change', () => {
    it('keeps the saved negotiated price when the end date is extended', async () => {
        renderEdit(makeBooking());

        fireEvent.change(screen.getByLabelText('End Date & Time'), {
            target: { value: '2026-10-11T09:00' },
        });

        await waitFor(() => expect(rateCalls().length).toBeGreaterThan(0));
        const params = rateCalls().at(-1)[1].params;
        expect(params.daychange).toBe(1);
        expect(String(params.daily_price)).toBe('150');
        expect(params.end_date_time).toBe('2026/10/11 09:00');

        // The stock rate (200) in the response must not overwrite the field.
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('150'));
    });

    it('falls back to the vehicle rate when the booking has no saved price', async () => {
        renderEdit(makeBooking({ daily_price_final: 0 }));

        fireEvent.change(screen.getByLabelText('End Date & Time'), {
            target: { value: '2026-10-11T09:00' },
        });

        await waitFor(() => expect(rateCalls().length).toBeGreaterThan(0));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('200'));
    });

    it('resets to the new vehicle rate when the vehicle is changed', async () => {
        renderEdit(makeBooking());

        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car B - 2-B-2'));

        await waitFor(() => expect(rateCalls().length).toBeGreaterThan(0));
        const params = rateCalls().at(-1)[1].params;
        expect(String(params.vahicle_id)).toBe('2');
        expect(params.daychange).toBe(0);
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('200'));
    });

    it('never prices at the vehicle rate just by opening the booking', async () => {
        renderEdit(makeBooking());

        await waitFor(() => expect(axios.get).toHaveBeenCalled());
        expect(rateCalls().every((c) => c[1].params.daychange === 1)).toBe(true);
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('150'));
    });
});
