// orsbuilds szerveroldali beállítások. A titkos értékek környezeti változókból jönnek.
export const config = {
  recipient: 'info.orsbuilds@gmail.com',
  fromName: 'orsbuilds weboldal',
  successUrl: '/koszonjuk.html',
  bookingSuccessUrl: '/foglalas-sikeres.html',
  booking: {
    timezone: 'Europe/Budapest',
    openTime: '09:00',
    closeTime: '17:00',
    stepMinutes: 30,
    maxDaysAhead: 90,
    services: {
      'Bemutatkozó weboldal': 30,
      'Többoldalas céges weboldal': 45,
      'Webshop': 60,
      'Egyedi fejlesztés': 45,
      'Karbantartás és szerkesztés': 30,
      'Egyéb': 30,
    },
  },
  contactTopics: [
    'Új weboldal készítése',
    'Webshop készítése',
    'Meglévő weboldal továbbfejlesztése',
    'Hiba javítása vagy karbantartás',
    'Egyedi fejlesztés',
    'Egyéb kérdés / együttműködés',
  ],
};

export function smtpSettings(env = process.env) {
  return {
    host: env.ORSBUILDS_SMTP_HOST || '',
    username: env.ORSBUILDS_SMTP_USERNAME || '',
    password: env.ORSBUILDS_SMTP_PASSWORD || '',
    from: env.ORSBUILDS_SMTP_FROM || env.ORSBUILDS_SMTP_USERNAME || '',
    port: Number(env.ORSBUILDS_SMTP_PORT || 587),
    encryption: (env.ORSBUILDS_SMTP_ENCRYPTION || 'tls').toLowerCase(),
  };
}
