#!/bin/bash
set -euo pipefail

SSL=/var/webuzo/users/jomas/ssl

cp -a "$SSL/sesam-tech.com-combined.pem" "$SSL/sesam-tech.com-combined.pem.bak-502"

# Webuzo attend clé + certificats dans le combined.pem
CA=/root/.acme.sh/sesam-tech.com_ecc/ca.cer
if [ ! -f "$CA" ]; then
  CA=/root/.acme.sh/sesam-tech.com/ca.cer
fi
cat "$SSL/sesam-tech.com.key" "$SSL/sesam-tech.com.crt" "$CA" > "$SSL/sesam-tech.com-combined.pem"
chmod 640 "$SSL/sesam-tech.com-combined.pem"
chown webuzo:webuzo "$SSL/sesam-tech.com-combined.pem"

echo "combined starts with:"
head -1 "$SSL/sesam-tech.com-combined.pem"

# Doublons : Webuzo a déjà les vhosts sesam
if [ -f /usr/local/apps/apache2/etc/conf.d/sesam-tech.com.conf ]; then
  mv /usr/local/apps/apache2/etc/conf.d/sesam-tech.com.conf /usr/local/apps/apache2/etc/conf.d/sesam-tech.com.conf.disabled
fi
if [ -f /usr/local/apps/nginx/etc/conf.d/sesam-tech.com.conf ]; then
  mv /usr/local/apps/nginx/etc/conf.d/sesam-tech.com.conf /usr/local/apps/nginx/etc/conf.d/sesam-tech.com.conf.disabled
fi

/root/.acme.sh/acme.sh --install-cert -d sesam-tech.com \
  --cert-file "$SSL/sesam-tech.com.crt" \
  --key-file "$SSL/sesam-tech.com.key" \
  --fullchain-file /tmp/sesam-fullchain.pem \
  --reloadcmd 'cat /var/webuzo/users/jomas/ssl/sesam-tech.com.key /tmp/sesam-fullchain.pem > /var/webuzo/users/jomas/ssl/sesam-tech.com-combined.pem; /usr/local/apps/apache2/bin/apachectl graceful; /usr/local/apps/nginx/sbin/nginx -s reload'

echo "=== apache configtest ==="
/usr/local/apps/apache2/bin/apachectl configtest

echo "=== start apache ==="
/usr/local/apps/apache2/bin/apachectl start || true
systemctl start httpd || true
sleep 1
systemctl is-active httpd || true
ss -lntp | grep -E '8081|8082' || true

echo "=== nginx ==="
/usr/local/apps/nginx/sbin/nginx -t
/usr/local/apps/nginx/sbin/nginx -s reload

echo DONE
