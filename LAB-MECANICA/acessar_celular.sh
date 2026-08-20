#!/bin/bash
# ==============================================================================
# Script para Acesso Mobile - Lab-Mecanica (EDTEC-SOLUTION)
# ==============================================================================

# Identifica o IP da máquina na rede local
LOCAL_IP=$(python3 -c "
import socket
try:
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    s.connect(('8.8.8.8', 80))
    ip = s.getsockname()[0]
    s.close()
    print(ip)
except Exception:
    print('127.0.0.1')
")

PORT=8181

echo "============================================================"
echo "📱 LAB-MECANICA - ACESSO NO CELULAR / SMARTPHONE"
echo "============================================================"
echo ""
echo "Para abrir o sistema no seu celular:"
echo ""
echo "1. Certifique-se de que seu celular está conectado à MESMA REDE WI-FI do computador."
echo "2. Abra o navegador do seu celular (Chrome, Safari, Firefox, etc.)."
echo "3. Digite o seguinte endereço na barra de navegação:"
echo ""
echo "   👉 http://${LOCAL_IP}:${PORT}"
echo ""
echo "------------------------------------------------------------"
echo "Status do Container Docker:"
docker compose ps
echo "============================================================"
