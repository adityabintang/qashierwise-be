<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $success ? 'Kalender Terhubung' : 'Koneksi Gagal' }} – QashierWise</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: #f5f5f5;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .card {
    background: #fff;
    border-radius: 1rem;
    padding: 2.5rem 2rem;
    max-width: 420px;
    width: 100%;
    text-align: center;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
  }
  .icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.25rem;
    font-size: 2rem;
  }
  .icon.success { background: #dcfce7; }
  .icon.error   { background: #fee2e2; }
  h1 { font-size: 1.4rem; font-weight: 700; margin: 0 0 .75rem; color: #111; }
  p  { color: #555; line-height: 1.6; margin: 0 0 1.25rem; font-size: .95rem; }
  .badge {
    display: inline-block;
    background: #f0fdf4; color: #15803d;
    border: 1px solid #bbf7d0;
    padding: .3rem .8rem;
    border-radius: 9999px;
    font-size: .82rem; font-weight: 600;
    margin-bottom: 1.25rem;
  }
  .note {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: .6rem;
    padding: .9rem 1rem;
    font-size: .85rem; color: #475569;
    text-align: left;
  }
  .note strong { color: #1e293b; }
  .close-btn {
    margin-top: 1.5rem;
    display: inline-block;
    background: #7c3aed; color: #fff;
    padding: .65rem 2rem;
    border-radius: .6rem;
    text-decoration: none; font-weight: 600;
    font-size: .95rem;
  }
</style>
</head>
<body>
<div class="card">
  @if ($success)
    <div class="icon success">📅</div>
    <h1>Kalender Berhasil Terhubung!</h1>
    @if (!empty($email))
      <div class="badge">{{ $email }}</div>
    @endif
    <p>{{ $message }}</p>
    <div class="note">
      <strong>Apa yang terjadi selanjutnya?</strong><br>
      Setiap kali Anda membuat reservasi baru, event akan otomatis muncul di Google Calendar Anda — tanpa perlu klik apapun.
    </div>
  @else
    <div class="icon error">❌</div>
    <h1>Koneksi Gagal</h1>
    <p>{{ $message }}</p>
    <div class="note">
      <strong>Butuh bantuan?</strong><br>
      Anda masih bisa menambahkan reservasi ke kalender secara manual menggunakan link yang dikirimkan di WhatsApp Anda.
    </div>
  @endif
  <br>
  <small style="color:#94a3b8;">Halaman ini bisa ditutup.</small>
</div>
</body>
</html>
