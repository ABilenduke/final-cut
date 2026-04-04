#!/bin/sh
#
# Generate self-signed TLS certificates for Redis.
#
# Creates a dedicated Certificate Authority and server certificate
# for Redis TLS connections, separate from the nginx/web certificates.
#
# Usage: ./redis/certs/generate-certs.sh
#

set -e

CERT_DIR="$(cd "$(dirname "$0")" && pwd)"
CA_KEY="$CERT_DIR/ca.key"
CA_CERT="$CERT_DIR/ca.pem"
SERVER_KEY="$CERT_DIR/server.key"
SERVER_CERT="$CERT_DIR/server.pem"
SERVER_CSR="$CERT_DIR/server.csr"

if ! command -v openssl > /dev/null 2>&1; then
    echo "Error: openssl is not installed."
    exit 1
fi

echo "Generating Redis TLS certificates in $CERT_DIR ..."
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
    -subj "/C=US/ST=Local/L=Dev/O=FinalCut/CN=Redis Local CA"

# --- Server Certificate ---
echo "[3/4] Generating server key and CSR ..."
openssl genrsa -out "$SERVER_KEY" 2048 2>/dev/null

openssl req -new \
    -key "$SERVER_KEY" \
    -out "$SERVER_CSR" \
    -subj "/C=US/ST=Local/L=Dev/O=FinalCut/CN=redis"

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
subjectAltName=DNS:redis,DNS:localhost,IP:127.0.0.1
EXTENSIONS

# Clean up intermediate files
rm -f "$SERVER_CSR" "$CERT_DIR/ca.srl"

echo ""
echo "=== Redis TLS Certificates Generated ==="
echo ""
echo "  CA key:            $CA_KEY"
echo "  CA certificate:    $CA_CERT"
echo "  Server key:        $SERVER_KEY"
echo "  Server certificate: $SERVER_CERT"
echo ""
echo "SANs: redis, localhost, 127.0.0.1"
echo ""
