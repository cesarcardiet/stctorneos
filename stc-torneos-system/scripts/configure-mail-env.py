#!/usr/bin/env python3
import base64
import re
import sys
from pathlib import Path

ENV_PATH = Path('/var/www/stc-torneos/.env')

if len(sys.argv) != 2:
    print('Usage: configure-mail-env.py <base64-password>')
    sys.exit(1)

password = base64.b64decode(sys.argv[1]).decode('utf-8')
text = ENV_PATH.read_text(encoding='utf-8')

updates = {
    'MAIL_MAILER': 'smtp',
    'MAIL_HOST': 'mail.privateemail.com',
    'MAIL_PORT': '587',
    'MAIL_USERNAME': 'info@stctorneos.com',
    'MAIL_PASSWORD': password,
    'MAIL_ENCRYPTION': 'tls',
    'MAIL_FROM_ADDRESS': 'info@stctorneos.com',
    'MAIL_FROM_NAME': 'STC Torneos',
}

for key, value in updates.items():
    escaped = value.replace('\\', '\\\\').replace('"', '\\"')
    line = f'{key}="{escaped}"'
    pattern = re.compile(rf'^{re.escape(key)}=.*$', re.MULTILINE)
    if pattern.search(text):
        text = pattern.sub(line, text, count=1)
    else:
        if not text.endswith('\n'):
            text += '\n'
        text += line + '\n'

ENV_PATH.write_text(text, encoding='utf-8')
print('ENV_UPDATED')
