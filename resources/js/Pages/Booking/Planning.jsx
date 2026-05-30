import { useEffect, useRef } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import AdminLayout from '@/Layouts/AdminLayout';

function BookingPlanning({ bookingData, vehicleData }) {
    const calRef = useRef(null);
    const calInstanceRef = useRef(null);

    useEffect(() => {
        function initCalendar() {
            if (!window.FullCalendar || !calRef.current) return;
            if (calInstanceRef.current) return;

            calInstanceRef.current = new window.FullCalendar.Calendar(calRef.current, {
                now: new Date(),
                editable: false,
                aspectRatio: 1.8,
                scrollTime: '00:00',
                headerToolbar: {
                    left: 'today prev,next',
                    center: 'title',
                    right: 'resourceTimelineDay,resourceTimelineWeek,resourceTimelineMonth,resourceTimelineYear',
                },
                initialView: 'resourceTimelineMonth',
                views: {
                    resourceTimelineMonth: {
                        slotDuration: '02:00',
                        slotLabelInterval: { days: 1 },
                        slotMinWidth: 20,
                        eventMaxStack: 1,
                    },
                },
                navLinks: true,
                resourceAreaWidth: '25%',
                resourceAreaHeaderContent: 'Vehicles',
                resources: vehicleData,
                events: bookingData,
                eventOverlap: true,
                moreLinkClick: 'popover',
                eventClick: function (info) {
                    const url = info.event.url || info.event.extendedProps?.url;
                    if (url) {
                        info.jsEvent.preventDefault();
                        window.location.href = url;
                    }
                },
                eventDidMount: function (info) {
                    info.el.style.backgroundColor = 'rgba(33, 150, 243, 0.35)';
                    info.el.style.borderColor = 'rgba(33, 150, 243, 0.85)';
                    info.el.style.color = '#fff';
                },
            });

            calInstanceRef.current.render();
        }

        if (window.FullCalendar) {
            initCalendar();
        } else {
            const script = document.createElement('script');
            script.src = '/js/index.global.js';
            script.onload = initCalendar;
            document.head.appendChild(script);
        }

        return () => {
            if (calInstanceRef.current) {
                calInstanceRef.current.destroy();
                calInstanceRef.current = null;
            }
        };
    }, [bookingData, vehicleData]);

    return (
        <div className="p-6">
            <Card>
                <CardContent className="pt-6">
                    <div ref={calRef} />
                </CardContent>
            </Card>
        </div>
    );
}

BookingPlanning.layout = (page) => (
    <AdminLayout breadcrumbs={[
        { label: 'Bookings', href: route('booking.index') },
        { label: 'Planning' },
    ]}>{page}</AdminLayout>
);
export default BookingPlanning;
