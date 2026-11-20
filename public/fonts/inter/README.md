# Font Inter — self-hosted

Folder ini menyimpan berkas font yang dipakai aplikasi. **Tidak ada** pemuatan font dari
CDN/domain pihak ketiga saat runtime.

| Berkas | Keterangan |
|---|---|
| `InterVariable.woff2` | Variable font Inter v4.1, weight 100–900, style normal |
| `InterVariable-Italic.woff2` | Variable font Inter v4.1, weight 100–900, style italic |
| `LICENSE.txt` | SIL Open Font License 1.1 (The Inter Project Authors) |

- Sumber resmi: <https://rsms.me/inter/> (berkas `font-files/InterVariable*.woff2`).
- Deklarasi `@font-face`: `resources/sass/_fonts.scss` (di-import di awal
  `resources/sass/app.scss`) dengan URL absolut `/fonts/inter/...`, sehingga berkas
  disajikan langsung dari `public/` tanpa diproses Vite.
- Dipakai lewat Tailwind: `fontFamily.sans` di `tailwind.config.js` = `['Inter', ...]`.
- CSP `font-src 'self'` (`app/Http/Middleware/SecurityHeaders.php`) memblokir font dari
  domain lain.

## Cara memperbarui

```powershell
# ganti kedua berkas .woff2 + LICENSE.txt dengan rilis terbaru, lalu:
npm run build
php artisan view:clear
```

Jangan menambahkan `@import url("https://fonts.googleapis.com/...")` atau
`<link rel="stylesheet" href="https://...">` ke view mana pun.
