<?php
// Callback endpoint tetap disediakan karena Duitku meminta callbackUrl pada inquiry,
// tetapi status pembayaran aplikasi tidak pernah diubah dari callback ini.
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
