import { bookingDateIsValid, bookingDuration, slotStates } from '../lib/booking.js';
import { json } from '../lib/http.js';
import { takenCells } from '../lib/store.js';

export async function GET(request) {
  const params = new URL(request.url).searchParams;
  const date = params.get('date') || '';
  const service = params.get('package') || '';
  if (date.length !== 10 || service.length > 80) {
    return json({ error: 'Válassz érvényes dátumot és szolgáltatást.' }, 400);
  }
  const duration = bookingDuration(service);
  if (duration < 1 || !bookingDateIsValid(date)) return json({ times: [], duration: 0 });

  try {
    const slots = slotStates(date, service, await takenCells(date));
    return json({
      times: slots.filter((slot) => slot.free).map((slot) => slot.time),
      booked: slots.filter((slot) => !slot.free).map((slot) => slot.time),
      duration,
    });
  } catch (error) {
    console.error('orsbuilds availability error:', error?.message);
    return json({ error: 'Az időpontok jelenleg nem tölthetők be. Próbáld újra később.' }, 503);
  }
}
