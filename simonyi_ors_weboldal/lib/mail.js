import nodemailer from 'nodemailer';
import { config, smtpSettings } from './config.js';

export function createMailer(env = process.env) {
  const smtp = smtpSettings(env);
  for (const key of ['host', 'username', 'password', 'from']) {
    if (!smtp[key].trim()) throw new Error(`Az SMTP beállítás hiányos: ${key}.`);
  }
  if (!Number.isInteger(smtp.port) || smtp.port < 1 || smtp.port > 65535) throw new Error('Az SMTP port érvénytelen.');
  if (!['tls', 'ssl'].includes(smtp.encryption)) throw new Error('Az SMTP titkosítás csak tls vagy ssl lehet.');
  return nodemailer.createTransport({
    host: smtp.host,
    port: smtp.port,
    secure: smtp.encryption === 'ssl',
    requireTLS: smtp.encryption === 'tls',
    auth: { user: smtp.username, pass: smtp.password },
    connectionTimeout: 10000,
    greetingTimeout: 10000,
    socketTimeout: 15000,
  });
}

export async function sendMail(mailer, { to, toName, replyTo, replyToName, subject, text }, env = process.env) {
  const { from } = smtpSettings(env);
  const clean = (s) => String(s || '').replace(/[\r\n]+/g, ' ').replace(/["<>]/g, '').trim();
  await mailer.sendMail({
    from: { name: config.fromName, address: from },
    to: toName ? { name: clean(toName), address: to } : to,
    replyTo: replyTo ? { name: clean(replyToName), address: replyTo } : undefined,
    subject,
    text,
  });
}
