/**
 * Work out which kind of event a notification is (deposit_submitted,
 * booking_created, ...), for both stored notifications (type inside `data`)
 * and realtime ones (Laravel overwrites `type` with the PHP class name, so the
 * real type is sent as `notification_type`).
 */
export function notificationPayload(notification) {
    return notification?.data?.data || notification?.data || {};
}

export function notificationKind(notification) {
    const d = notificationPayload(notification);
    const declared = d.notification_type || d.type;

    if (declared && !String(declared).includes('\\')) {
        return declared;
    }

    // Fallback: infer from the ids present in the payload.
    if (d.modify_request_id) return 'modify_request_submitted';
    if (d.booking_request_id) return 'booking_request_submitted';
    if (d.deposit_id) return 'deposit_submitted';
    if (d.booking_id) return 'booking_created';
    if (d.user_id && d.email) return 'new_user';

    return null;
}

/** Admin notifications that belong to a sidebar section, and the routes that open it. */
export const ADMIN_NOTIFICATION_SECTIONS = {
    booking_created: ['AdminCustomerBookings', 'AdminCustomerBookingsLayout', 'AdminBookingsDetails'],
    booking_request_submitted: ['BookingRequests'],
    modify_request_submitted: ['ModifyRequests'],
    deposit_submitted: ['TopUpRequest', 'DepositDetails'],
    new_user: ['Customers', 'CustomerDetails'],
};
