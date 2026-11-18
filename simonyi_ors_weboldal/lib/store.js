import { createHash } from 'node:crypto';
import { put, list, del, get } from '@vercel/blob';
import { bookingIsInFuture, cellsFor } from './booking.js';

const privateJson = { access: 'private', addRandomSuffix: false, contentType: 'application/json' };
const slotPath = (date, cell) => `bookings/slots/${date}/${cell.replace(':', '')}.json`;

const isAlreadyExists = (error) =>
  error?.name === 'BlobAlreadyExistsError' || /already exist/i.test(String(error?.message || ''));

/** A megadott napon már foglalt rácspontok ('HH:MM'). */
export async function takenCells(date) {
  const taken = new Set();
  let cursor;
  do {
    const page = await list({ prefix: `bookings/slots/${date}/`, cursor, limit: 100 });
    for (const blob of page.blobs) {
      const code = blob.pathname.split('/').pop().replace('.json', '');
      if (/^\d{4}$/.test(code)) taken.add(`${code.slice(0, 2)}:${code.slice(2)}`);
    }
    cursor = page.hasMore ? page.cursor : undefined;
  } while (cursor);
  return taken;
}

async function release(urls) {
  await Promise.allSettled(urls.map((url) => del(url)));
}

/**
 * Atomikus foglalás: minden elfoglalt rácspont külön, felülírhatatlan blob.
 * Ha bármelyik már létezik, a már megszerzetteket visszaadjuk.
 */
export async function reserve(booking) {
  const cells = cellsFor(booking.time, booking.duration);
  const acquired = [];
  try {
    for (const cell of cells) {
      const result = await put(slotPath(booking.date, cell), JSON.stringify({ id: booking.id }), {
        ...privateJson,
        allowOverwrite: false,
      });
      acquired.push(result.url);
    }
    await put(`bookings/records/${booking.date}/${booking.id}.json`, JSON.stringify({
      ...booking,
      createdAt: new Date().toISOString(),
    }), { ...privateJson, allowOverwrite: false });
    return { ok: true };
  } catch (error) {
    await release(acquired);
    if (isAlreadyExists(error)) return { ok: false, reason: 'taken' };
    throw error;
  }
}

/** Kis JSON-állapot olvasása/írása (pl. sebességkorlát). Nem létező blobra null. */
export async function readJson(pathname) {
  const result = await get(pathname, { access: 'private', useCache: false });
  if (!result || result.statusCode !== 200) return null;
  return JSON.parse(await new Response(result.stream).text());
}

export async function writeJson(pathname, value) {
  await put(pathname, JSON.stringify(value), { ...privateJson, allowOverwrite: true });
}

export const hashKey = (value) => createHash('sha256').update(String(value)).digest('hex').slice(0, 40);

async function findRecord(id) {
  let cursor;
  do {
    const page = await list({ prefix: 'bookings/records/', cursor, limit: 1000 });
    const hit = page.blobs.find((blob) => blob.pathname.endsWith(`/${id}.json`));
    if (hit) return hit;
    cursor = page.hasMore ? page.cursor : undefined;
  } while (cursor);
  return null;
}

/**
 * Foglalás lemondása azonosító és e-mail-cím alapján.
 * Visszatér: { ok: true, booking } vagy { ok: false, reason: 'notfound' | 'past' }.
 */
export async function cancelBooking(id, email) {
  const blob = await findRecord(id);
  if (!blob) return { ok: false, reason: 'notfound' };
  const booking = await readJson(blob.pathname);
  if (!booking || String(booking.email).trim().toLowerCase() !== String(email).trim().toLowerCase()) {
    return { ok: false, reason: 'notfound' };
  }
  if (!bookingIsInFuture(booking.date, booking.time)) return { ok: false, reason: 'past' };

  // Előbb a rácspontok szabadulnak fel, a rekord törlése csak utána történik.
  const cellUrls = [];
  for (const cell of cellsFor(booking.time, booking.duration)) {
    const found = await list({ prefix: slotPath(booking.date, cell), limit: 1 });
    cellUrls.push(...found.blobs.map((b) => b.url));
  }
  await Promise.all(cellUrls.map((url) => del(url)));
  await put(`bookings/cancelled/${booking.date}/${booking.id}.json`, JSON.stringify({
    ...booking,
    cancelledAt: new Date().toISOString(),
  }), { ...privateJson, allowOverwrite: true });
  await del(blob.url);
  return { ok: true, booking };
}
