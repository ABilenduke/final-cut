#!/bin/sh
#
# Generate self-signed SSL certificates for local development.
#
# Creates a local Certificate Authority (CA) and a server certificate
# signed by that CA, with Subject Alternative Names for the configured
# APP_DOMAIN and localhost.
#
# Usage: ./nginx/certs/generate-certs.sh
#

set -e

# Load APP_DOMAIN from the project root .env file
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
if [ -f "$PROJECT_ROOT/.env" ]; then
    APP_DOMAIN=$(grep '^APP_DOMAIN=' "$PROJECT_ROOT/.env" | cut -d '=' -f2)
fi
APP_DOMAIN="${APP_DOMAIN:-andrewbilenduke.test}"

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
CA_KEY="$CERT_DIR/ca.key"
CA_CERT="$CERT_DIR/ca.pem"
SERVER_KEY="$CERT_DIR/server.key"
SERVER_CERT="$CERT_DIR/server.pem"
SERVER_CSR="$CERT_DIR/server.csr"

# Verify openssl is available
if ! command -v openssl > /dev/null 2>&1; then
    echo "Error: openssl is not installed."
    exit 1
fi

echo "Generating SSL certificates in $CERT_DIR ..."
echo ""

# --- Certificate Authority ---
echo "[1/4] Generating CA private key ..."
openssl genrsa -out "$CA_KEY" 2048 2>/dev/null

echo "[2/4] Generating CA certificate (10-year validity) ..."
openssl req -x509 -new -nodes \
    -key "$CA_KEY" \
    -sha256 \
    -days 3650 \
    -out "$CA_CERT" \
    -subj "/C=US/ST=Local/L=Dev/O=FinalCut/CN=$APP_DOMAIN Local CA"

# --- Server Certificate ---
echo "[3/4] Generating server key and CSR ..."
openssl genrsa -out "$SERVER_KEY" 2048 2>/dev/null

openssl req -new \
    -key "$SERVER_KEY" \
    -out "$SERVER_CSR" \
    -subj "/C=US/ST=Local/L=Dev/O=FinalCut/CN=$APP_DOMAIN"

echo "[4/4] Signing server certificate with CA (365-day validity) ..."
openssl x509 -req \
    -in "$SERVER_CSR" \
    -CA "$CA_CERT" \
    -CAkey "$CA_KEY" \
    -CAcreateserial \
    -out "$SERVER_CERT" \
    -days 365 \
    -sha256 \
    -extfile - <<EXTENSIONS
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage=digitalSignature,nonRepudiation,keyEncipherment,dataEncipherment
subjectAltName=DNS:$APP_DOMAIN,DNS:*.$APP_DOMAIN,DNS:localhost,IP:127.0.0.1
EXTENSIONS

# Clean up intermediate files
rm -f "$SERVER_CSR" "$CERT_DIR/ca.srl"

echo ""
echo "=== SSL Certificates Generated ==="
echo ""
echo "  CA key:         $CA_KEY"
echo "  CA certificate:  $CA_CERT"
echo "  Server key:      $SERVER_KEY"
echo "  Server certificate: $SERVER_CERT"
echo ""
echo "SANs: $APP_DOMAIN, *.$APP_DOMAIN, localhost, 127.0.0.1"
echo ""
echo "Next steps:"
echo "  1. Add '127.0.0.1 $APP_DOMAIN' to your hosts file"
echo "     - Windows: C:\\Windows\\System32\\drivers\\etc\\hosts"
echo "     - WSL2:    sudo sh -c 'echo \"127.0.0.1 $APP_DOMAIN\" >> /etc/hosts'"
echo "  2. Run 'make trust-cert' to add the CA to Windows trust store"
echo "  3. Run 'make up' to start the containers with HTTPS"
echo ""
