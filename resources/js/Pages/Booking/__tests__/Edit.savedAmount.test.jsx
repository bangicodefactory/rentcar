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
            data: { considerDays: 16, totalRate: String(daily * 16), addonAmount: params.addons?.length ? 200 : 0, placeAmount: 0, daily_price: 500, duration: `16 * ${daily} = ${daily * 16} Dh` },
        });
    });
});

const amountOf = (container) => container.querySelector('input[name="amount"]').value;
const price = () => screen.getByLabelText('Price per day');

describe('Booking/Edit — the saved amount survives opening the page', () => {
    it('keeps an imported booking amount and fills its real per-day price (amount ÷ days)', async () => {
        const { container } = renderEdit(importedBooking);

        // 8800 over the 16 charged days = 550/day, in the form only.
        await waitFor(() => expect(price().value).toBe('550'));
        expect(amountOf(container)).toBe('8800');
    });

    it('an addon on an imported booking prices at its real rate (8800 + 200)', async () => {
        const { container } = renderEdit(importedBooking);
        await waitFor(() => expect(price().value).toBe('550'));

        fireEvent.click(screen.getByRole('checkbox'));

        await waitFor(() => expect(amountOf(container)).toBe('9000'));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(1);
        expect(price().value).toBe('550');
    });

    it('a booking with neither price nor amount falls back to nothing on load', async () => {
        renderEdit({ ...importedBooking, amount: 0 });
        await waitFor(() => expect(axios.get).toHaveBeenCalled());

        expect(rateCalls()).toHaveLength(0);
        expect(price().value).toBe('0.00');
    });
});

describe('Booking/Edit — price breakdown on load', () => {
    const saved = {
        ...importedBooking,
        daily_price_final: '150.00',
        amount: 2400,
        // A tampered hidden field: saved details must never be rendered as HTML.
        details: JSON.stringify({ totalRate: '2400', duration: '<img src="x" data-xss="1">' }),
    };

    it('shows the server breakdown when it matches the saved amount, never the saved details HTML', async () => {
        const { container } = renderEdit(saved);

        await waitFor(() => expect(screen.getByText('16 * 150 = 2400 Dh')).toBeTruthy());
        expect(container.querySelector('img[data-xss]')).toBeNull();
        expect(amountOf(container)).toBe('2400');
    });

    it('hides the breakdown when it contradicts the saved amount, and keeps the amount', async () => {
        const { container } = renderEdit({ ...saved, amount: 9999 });
        await waitFor(() => expect(rateCalls().length).toBe(1));

        await new Promise((r) => setTimeout(r, 50));
        expect(screen.queryByText('16 * 150 = 2400 Dh')).toBeNull();
        expect(amountOf(container)).toBe('9999');
        expect(price().value).toBe('150.00');
    });

    it('shows no breakdown for an imported booking on load', async () => {
        renderEdit(importedBooking);
        await waitFor(() => expect(price().value).toBe('550'));

        expect(screen.queryByText('Duration')).toBeNull();
    });
});

describe('Booking/Edit — discount adjusts the current amount', () => {
    it('subtracts from a saved amount that the breakdown does not explain (9999 − 50), and clearing restores it', async () => {
        const { container } = renderEdit({ ...importedBooking, daily_price_final: '150.00', amount: 9999 });
        await waitFor(() => expect(rateCalls().length).toBe(1));

        fireEvent.change(screen.getByLabelText('Discount'), { target: { value: '50' } });
        await waitFor(() => expect(amountOf(container)).toBe('9949'));

        fireEvent.change(screen.getByLabelText('Discount'), { target: { value: '' } });
        await waitFor(() => expect(amountOf(container)).toBe('9999'));
    });

    it('a discount on an imported booking gives 8800 − 500 without re-pricing', async () => {
        const { container } = renderEdit(importedBooking);
        await waitFor(() => expect(price().value).toBe('550'));
        const callsBefore = rateCalls().length;

        fireEvent.change(screen.getByLabelText('Discount'), { target: { value: '500' } });

        await waitFor(() => expect(amountOf(container)).toBe('8300'));
        expect(rateCalls()).toHaveLength(callsBefore);
        expect(price().value).toBe('550');
    });
});
