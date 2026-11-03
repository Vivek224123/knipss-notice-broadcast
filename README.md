# College Notice Broadcast (PHP + WhatsApp Cloud API)

Zarurat: PHP 8.0+, MySQL, cURL extension (normal hosting/XAMPP me hota hai).

## Setup
1. Folder ko web server me rakho (jaise `htdocs/notice-broadcast`).
2. MySQL me `schema.sql` run karo (ek view + 2 tables banenge, `student_info` me kuch nahi badlega).
3. `config.php` me college naam, admin password, DB aur Meta ki values bharo.
4. Browser me `login.php` kholo.

## Meta side
- Template: naam `college_notice`, category **Utility**, body:
  `Namaste {{1}}, college se naya notice: {{2}}. {{3}}`
- Webhook URL: `https://<your-domain>/webhook.php`, verify token = `config.php` ka `verify_token`, field `messages` subscribe karo.
- Pehle sirf test numbers par try karo.

## Notes
- Number `whatsapp_no` se liya jata hai, khali ho to `mobile` se. 10 digit number me `91` apne aap lagta hai.
- Cancelled students (`cancel_date` bhara ho) skip hote hain.
- Notice bhejte waqt page turant status page par chala jata hai aur sending background me chalti rehti hai.
  Agar hosting par PHP ka time limit/`ignore_user_abort` band ho to bade batch (1000+) ke liye cron/CLI worker alag se banana padega.
- Site HTTPS par hi chalao.
