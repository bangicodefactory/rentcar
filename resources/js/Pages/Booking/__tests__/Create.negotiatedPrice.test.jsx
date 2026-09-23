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

describe('Booking/Create — vehicle change then date change before the rate returns', () => {
    it('prices the new car at its own rate and keeps price and total consistent', async () => {
        const stock = { 1: 200, 2: 300 };
        let releaseVehicleReply;
        axios.get.mockImplementation((url, { params } = {}) => {
            if (url !== '/vehicle.rate.calculation') {
                return Promise.resolve({ data: { 1: 'Car A - 1-A-1', 2: 'Car B - 2-B-2' } });
            }
            const daily = params.daychange ? Number(params.daily_price) : stock[params.vahicle_id];
            const data = { considerDays: 10, totalRate: String(daily * 10), addonAmount: 0, placeAmount: 0, daily_price: stock[params.vahicle_id] };
            if (String(params.vahicle_id) === '2' && !releaseVehicleReply) {
                return new Promise((resolve) => { releaseVehicleReply = () => resolve({ data }); });
            }
            return Promise.resolve({ data });
        });

        const { container } = render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car B - 2-B-2'));
        await waitFor(() => expect(releaseVehicleReply).toBeDefined());

        fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-11T09:00' } });
        await waitFor(() => expect(rateCalls().at(-1)[1].params.end_date_time).toBe('2026/10/11 09:00'));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);

        releaseVehicleReply();

        const amount = () => container.querySelector('input[name="amount"]').value;
        await waitFor(() => expect(screen.getByLabelText('Price per day').value).toBe('300'));
        await waitFor(() => expect(amount()).toBe('3000'));
    });
});

// Follow-up review of PR #227. `stock` is each car's set rate; the first rate
// request for `holdId` is held back until release().
function mockRates(stock, holdId) {
    const held = { release: undefined };
    axios.get.mockImplementation((url, { params } = {}) => {
        if (url !== '/vehicle.rate.calculation') {
            return Promise.resolve({ data: { 1: 'Car A - 1-A-1', 2: 'Car B - 2-B-2', 3: 'Car C - 3-C-3' } });
        }
        const daily = params.daychange ? Number(params.daily_price) : stock[params.vahicle_id];
        const data = { considerDays: 10, totalRate: String(daily * 10), addonAmount: 0, placeAmount: 0, daily_price: stock[params.vahicle_id] };
        if (String(params.vahicle_id) === String(holdId) && !held.release) {
            return new Promise((resolve) => { held.release = () => resolve({ data }); });
        }
        return Promise.resolve({ data });
    });
    return held;
}

describe('Booking/Create — price field during a car switch', () => {
    const amountOf = (container) => container.querySelector('input[name="amount"]').value;
    const price = () => screen.getByLabelText('Price per day');

    it('leaving the price field untouched while the car rate is pending uses the new car rate', async () => {
        const held = mockRates({ 1: 200, 2: 300 }, 2);
        const { container } = render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car B - 2-B-2'));
        await waitFor(() => expect(held.release).toBeDefined());
        const callsBefore = rateCalls().length;

        fireEvent.focus(price());
        fireEvent.blur(price());
        await waitFor(() => expect(rateCalls().length).toBeGreaterThan(callsBefore));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);

        held.release();
        await waitFor(() => expect(price().value).toBe('300'));
        await waitFor(() => expect(amountOf(container)).toBe('3000'));
    });

    it('a price typed during a car switch survives a later date change', async () => {
        const held = mockRates({ 1: 200, 2: 300 }, 2);
        const { container } = render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car B - 2-B-2'));
        await waitFor(() => expect(held.release).toBeDefined());

        fireEvent.change(price(), { target: { value: '150' } });
        fireEvent.blur(price());
        fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-11T09:00' } });
        await waitFor(() => expect(rateCalls().at(-1)[1].params.end_date_time).toBe('2026/10/11 09:00'));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(1);
        expect(String(rateCalls().at(-1)[1].params.daily_price)).toBe('150');

        held.release();
        await waitFor(() => expect(amountOf(container)).toBe('1500'));
        expect(price().value).toBe('150');
    });

    it('switching to a car with no set rate clears the previous car price', async () => {
        mockRates({ 1: 200, 3: 0 });
        render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car C - 3-C-3'));
        await waitFor(() => expect(price().value).toBe('0'));

        fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-11T09:00' } });
        await waitFor(() => expect(rateCalls().at(-1)[1].params.end_date_time).toBe('2026/10/11 09:00'));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);
    });
});

// Third review of PR #227. Rate mock where the day count follows the end date
// (Oct 11 → 10 days, else 7) and `hold(params)` / `fail(params)` pick a request
// to hold back until release() or to reject.
function mockRatesBy(stock, { hold = () => false, fail = () => false } = {}) {
    const held = { release: undefined };
    axios.get.mockImplementation((url, { params } = {}) => {
        if (url !== '/vehicle.rate.calculation') {
            return Promise.resolve({ data: { 1: 'Car A - 1-A-1', 2: 'Car B - 2-B-2' } });
        }
        if (fail(params)) return Promise.reject(new Error('network'));
        const days = String(params.end_date_time).startsWith('2026/10/11') ? 10 : 7;
        const daily = params.daychange ? Number(params.daily_price) : stock[params.vahicle_id];
        const data = { considerDays: days, totalRate: String(daily * days), addonAmount: 0, placeAmount: 0, daily_price: stock[params.vahicle_id] };
        if (!held.release && hold(params)) {
            return new Promise((resolve) => { held.release = () => resolve({ data }); });
        }
        return Promise.resolve({ data });
    });
    return held;
}

describe('Booking/Create — typing a price and failed car lookups', () => {
    const amountOf = (container) => container.querySelector('input[name="amount"]').value;
    const price = () => screen.getByLabelText('Price per day');

    it('typing a price does not drop an in-flight date recalculation', async () => {
        const held = mockRatesBy({ 1: 200 }, { hold: (p) => String(p.end_date_time).startsWith('2026/10/11') });
        const { container } = render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();
        await waitFor(() => expect(amountOf(container)).toBe('1400'));

        fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-11T09:00' } });
        await waitFor(() => expect(held.release).toBeDefined());

        // Typed, then submitted with Enter: no blur, so no re-price follows.
        fireEvent.change(price(), { target: { value: '175' } });
        held.release();

        // The date reply (10 × 200, sent before typing) still lands.
        await waitFor(() => expect(amountOf(container)).toBe('2000'));
    });

    it('a failed car-switch lookup keeps the next date change on the new car rate', async () => {
        mockRatesBy({ 1: 200, 2: 300 }, { fail: (p) => String(p.vahicle_id) === '2' && !p.daychange && !String(p.end_date_time).startsWith('2026/10/11') });
        render(<BookingCreate vehicles={[]} drivers={[]} statuses={[]} places={[]} addons={[]} />);
        await pickDatesAndVehicle();

        fireEvent.click(screen.getByRole('button', { name: 'Vehicle' }));
        fireEvent.click(screen.getByText('Car B - 2-B-2'));
        await waitFor(() => expect(String(rateCalls().at(-1)[1].params.vahicle_id)).toBe('2'));

        fireEvent.change(screen.getByLabelText('End Date & Time'), { target: { value: '2026-10-11T09:00' } });
        await waitFor(() => expect(rateCalls().at(-1)[1].params.end_date_time).toBe('2026/10/11 09:00'));
        expect(rateCalls().at(-1)[1].params.daychange).toBe(0);
        await waitFor(() => expect(price().value).toBe('300'));
    });
});
