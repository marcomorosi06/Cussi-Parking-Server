#!/bin/bash

# Assicuriamoci che lo script sia eseguito con sudo
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (sudo ./install.sh)"
  exit 1
fi

# 1. Scelta della lingua con timeout di 10 secondi
read -t 10 -p "Scegli la lingua / Choose language [IT/en]: " LANG_CHOICE
LANG_CHOICE=${LANG_CHOICE:-EN}

if [[ "$LANG_CHOICE" =~ ^[Ii][Tt] ]]; then
    MSG_WELCOME="Benvenuto! Configureremo il tuo server di parcheggio."
    MSG_DOMAIN="Inserisci il tuo sottodominio DuckDNS (es. mioserver, NON tutto l'url): "
    MSG_TOKEN="Inserisci il tuo Token DuckDNS: "
    MSG_EMAIL="Inserisci la tua email (serve a Caddy per i certificati SSL gratuiti): "
    MSG_UPDATE="Aggiornamento del sistema in corso..."
    MSG_INSTALL="Installazione dipendenze (PHP, SQLite, Curl)..."
    MSG_CADDY="Scaricamento e configurazione di Caddy per ARMv6..."
    MSG_FILES="Copia dei file PHP e configurazione permessi..."
    MSG_DONE="Installazione completata! Il tuo server è online su https://"
else
    MSG_WELCOME="Welcome! Let's set up your parking server."
    MSG_DOMAIN="Enter your DuckDNS subdomain (e.g. myserver, NOT the full url): "
    MSG_TOKEN="Enter your DuckDNS Token: "
    MSG_EMAIL="Enter your email (used by Caddy for free SSL certificates): "
    MSG_UPDATE="Updating system..."
    MSG_INSTALL="Installing dependencies (PHP, SQLite, Curl)..."
    MSG_CADDY="Downloading and configuring Caddy for ARMv6..."
    MSG_FILES="Copying PHP files and setting permissions..."
    MSG_DONE="Installation complete! Your server is live at https://"
fi

echo -e "\n$MSG_WELCOME\n"

# 2. Raccolta dati utente
read -p "$MSG_DOMAIN" DUCK_DOMAIN
read -p "$MSG_TOKEN" DUCK_TOKEN
read -p "$MSG_EMAIL" CADDY_EMAIL

# 3. Aggiornamento sistema
echo -e "\n---> $MSG_UPDATE"
apt update && apt upgrade -y

# 4. Installazione dipendenze
echo -e "\n---> $MSG_INSTALL"
apt install -y php-fpm php-sqlite3 curl cron tar

# Trova la versione di PHP-FPM installata per il socket (es. /run/php/php8.2-fpm.sock)
PHP_SOCK=$(find /run/php -name "php*-fpm.sock" | head -n 1)

# 5. Download e configurazione Caddy (Versione corretta per Pi Zero W - ARMv6)
echo -e "\n---> $MSG_CADDY"
wget https://github.com/caddyserver/caddy/releases/download/v2.8.4/caddy_2.8.4_linux_armv6.tar.gz -O /tmp/caddy.tar.gz
tar -xvf /tmp/caddy.tar.gz -C /tmp/ caddy
mv /tmp/caddy /usr/bin/caddy
chmod +x /usr/bin/caddy
setcap cap_net_bind_service=+ep /usr/bin/caddy

# Creazione utente e gruppo per Caddy
groupadd --system caddy || true
useradd --system --gid caddy --create-home --home-dir /var/lib/caddy --shell /usr/sbin/nologin caddy || true

# Configurazione Caddyfile
mkdir -p /etc/caddy
cat <<EOF > /etc/caddy/Caddyfile
${DUCK_DOMAIN}.duckdns.org {
    tls $CADDY_EMAIL
    root * /var/www/parcheggio
    php_fastcgi unix//$PHP_SOCK
    file_server
}
EOF

# Creazione file servizio Systemd per Caddy
cat <<EOF > /etc/systemd/system/caddy.service
[Unit]
Description=Caddy
Documentation=https://caddyserver.com/docs/
After=network.target network-online.target
Requires=network-online.target

[Service]
Type=notify
User=caddy
Group=caddy
ExecStart=/usr/bin/caddy run --environ --config /etc/caddy/Caddyfile
ExecReload=/usr/bin/caddy reload --config /etc/caddy/Caddyfile --force
TimeoutStopSec=5s
LimitNOFILE=1048576
LimitNPROC=512
PrivateTmp=true
ProtectSystem=full
AmbientCapabilities=CAP_NET_BIND_SERVICE

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now caddy

# 6. Configurazione DuckDNS tramite Crontab
echo -e "\n---> Config DuckDNS..."
# Rimuove vecchi cronjob duckdns per evitare duplicati e aggiunge quello nuovo
crontab -l 2>/dev/null | grep -v "duckdns.org" > /tmp/current_cron || true
echo "*/5 * * * * curl -s 'https://www.duckdns.org/update?domains=${DUCK_DOMAIN}&token=${DUCK_TOKEN}&ip=' >/dev/null 2>&1" >> /tmp/current_cron
crontab /tmp/current_cron
rm /tmp/current_cron
# Eseguiamo il primo update subito
curl -s "https://www.duckdns.org/update?domains=${DUCK_DOMAIN}&token=${DUCK_TOKEN}&ip=" >/dev/null 2>&1

# 7. Copia dei file PHP e configurazione permessi database
echo -e "\n---> $MSG_FILES"
mkdir -p /var/www/parcheggio
# Copia tutti i file .php dalla cartella da cui hai lanciato lo script
cp *.php /var/www/parcheggio/

# Assicuriamoci che Caddy e PHP-FPM (www-data) possano leggere/scrivere la cartella
chown -R www-data:www-data /var/www/parcheggio
# Permessi corretti: le cartelle devono poter essere "attraversate", i file letti/scritti.
# È vitale che la CARTELLA che contiene il DB SQLite abbia permessi di scrittura, 
# altrimenti SQLite non può creare i file di lock/journal.
chmod 775 /var/www/parcheggio
chmod 664 /var/www/parcheggio/*.php

# Riavvio dei servizi per applicare tutto
systemctl restart php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm
systemctl restart caddy

echo -e "\n=================================================="
echo -e "${MSG_DONE}${DUCK_DOMAIN}.duckdns.org"
echo -e "==================================================\n"
