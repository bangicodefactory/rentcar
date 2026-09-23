import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import BookingEdit from '@/Pages/Booking/Edit';

// Imported bookings store daily_price_final = 0 next to their real amount
// (e.g. 8800). Opening one in Edit used to re-price it on load with that 0 and
// show — and, on save, store — an amount of 0. Opening a booking must keep its
// saved amount; only a real change re-prices it, and a 0 price then falls back
// to the car's set rate instead of pricing every day at 0.

vi.mock('axios', () => ({
    default: { get: vi.fn(), post: vi.fn() },
}));

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
    router: { put: vi.fn() },
}));

globalThis.route = (name) => `/${name}`;

const importedBooking = {
    id: 2791,
    vehicle: 37,
    driver: '',
    start_date_time: '2026/07/31 22:30',
    end_date_time: '2026/08/16 22:30',
    pickup_address: '',
    drop_off_address: '',
    addon: '',
    discount: '',
    status: 'yet_to_start',
    notes: '',
    daily_price_final: '0.00',
    amount: 8800,
    details: {},
};

const rateCalls = () => axios.get.mock.calls.filter((c) => c[0] === '/vehicle.rate.calculation');

function renderEdit(booking) {
    return render(
        <BookingEdit
            booking={booking}
            vehicles={[{ id: 37, label: 'Seat Ibiza - 79780' }]}
            drivers={[]}
            statuses={[]}
            places={[]}
            addons={[{ id: 1, name: 'Baby seat' }]}
        />,
    );
}

beforeEach(() => {
    vi.clearAllMocks();
    usePage.mockReturnValue({ props: { translations: {}, errors: {} } });
    axios.get.mockImplementation((url, { params } = {}) => {
        if (url !== '/vehicle.rate.calculation') {
            return Promise.resolve({ data: { 37: 'Seat Ibiza - 79780' } });
        }
        const daily = params.daychange ? Number(params.daily_price) : 500;
        return Promise.resolve({
            data: { considerDays: 16, totalRate: String(daily * 16), addonAmount: params.addons?.length ? 200 : 0, placeAmount: 0, daily_price: 500 },
        });
    });
});

describe('Booking/Edit — the saved amount survives opening the page', () => {
    it('does not re-price an imported booking on load', async () => {
        const { container } = renderEdit(importedBooking);
        await waitFor(() => expect(axios.get).toHaveBeenCalled()); // available-vehicle refresh

        expect(rateCalls()).toHaveLength(0);
        expect(container.querySelector('input[name="amount"]').value).toBe('8800');
    });

    it('does not re-price a booking with a saved price on load either', async () => {
        const { container } = renderEdit({ ...importedBooking, daily_price_final: '150.00', amount: 2400 });
        await waitFor(() => expect(axios.get).toHaveBeenCalled());

        expect(rateCalls()).toHaveLength(0);
        expect(container.querySelector('input[name="amount"]').value).toBe('2400');
    });

    it('an addon change on a booking with no saved price uses the car rate, not 0', async () => {
        const { container } = renderEdit(importedBooking);
        await waitFor(() => expect(axios.get).toHaveBeenCalled());

        fireEvent.click(screen.getByRole('checkbox'));

        await waitFor(() => expect(rateCalls().length).toBe(1));
        expect(rateCalls()[0][1].params.daychange).toBe(0);
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('500'));
        expect(container.querySelector('input[name="amount"]').value).toBe('8200');
    });
});

describe('Booking/Edit — saved price breakdown on load', () => {
    const saved = {
        ...importedBooking,
        daily_price_final: '150.00',
        amount: 1050,
        details: JSON.stringify({ considerDays: 7, totalRate: '1050', addonAmount: 0, placeAmount: 0, duration: '7 * 150 = 1050 Dh' }),
    };

    it('shows the saved breakdown without re-pricing', async () => {
        const { container } = renderEdit(saved);
        await waitFor(() => expect(axios.get).toHaveBeenCalled());

        expect(screen.getByText('7 * 150 = 1050 Dh')).toBeTruthy();
        expect(rateCalls()).toHaveLength(0);
        expect(container.querySelector('input[name="amount"]').value).toBe('1050');
    });

    it('a discount typed after load still updates the amount from the saved breakdown', async () => {
        const { container } = renderEdit(saved);
        await waitFor(() => expect(axios.get).toHaveBeenCalled());

        fireEvent.change(screen.getByLabelText('Discount'), { target: { value: '50' } });

        await waitFor(() => expect(container.querySelector('input[name="amount"]').value).toBe('1000'));
    });

    it('shows no breakdown for an imported booking (nothing saved to show)', async () => {
        renderEdit(importedBooking);
        await waitFor(() => expect(axios.get).toHaveBeenCalled());

        expect(screen.queryByText('Duration')).toBeNull();
    });
});
