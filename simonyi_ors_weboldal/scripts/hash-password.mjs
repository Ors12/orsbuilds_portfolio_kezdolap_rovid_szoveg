// Használat: node scripts/hash-password.mjs "az-admin-jelszavad"
// A kimenetet add meg a Vercelen ORSBUILDS_ADMIN_PASSWORD_HASH környezeti változóként.
import { hashPassword } from '../lib/admin-session.js';

const password = process.argv[2];
if (!password || password.length < 10) {
  console.error('Adj meg legalább 10 karakteres jelszót argumentumként.');
  process.exit(1);
}
console.log(hashPassword(password));
