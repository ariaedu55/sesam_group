#!/bin/bash
set -euo pipefail

KEYDIR=/root/.ssh
KEYFILE=$KEYDIR/sesam_group_deploy
mkdir -p "$KEYDIR"
chmod 700 "$KEYDIR"

if [ ! -f "$KEYFILE" ]; then
  ssh-keygen -t ed25519 -C "sesam-tech.com-deploy" -f "$KEYFILE" -N ""
fi

if ! grep -q "Host github.com-sesam" "$KEYDIR/config" 2>/dev/null; then
  cat >> "$KEYDIR/config" <<'EOF'

Host github.com-sesam
  HostName github.com
  User git
  IdentityFile /root/.ssh/sesam_group_deploy
  IdentitiesOnly yes
EOF
fi

chmod 600 "$KEYDIR/config" "$KEYFILE"
chmod 644 "$KEYFILE.pub"

ssh-keygen -F github.com >/dev/null 2>&1 || ssh-keyscan -t ed25519,rsa github.com >> "$KEYDIR/known_hosts" 2>/dev/null

echo "===== COLLER CETTE CLE DANS GITHUB (champ Key) ====="
cat "$KEYFILE.pub"
echo "===== FIN ====="
