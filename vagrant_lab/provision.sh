#!/bin/bash

echo "Atualizando sistema..."
apt-get update -y
apt-get upgrade -y

echo "Instalando Apache..."
apt-get install -y apache2

echo "Parando Apache para ajustes..."
systemctl stop apache2

echo "Limpando /var/www/html..."
rm -rf /var/www/html/*

echo "Copiando arquivos da pasta html..."
cp -r /vagrant/html/* /var/www/html/

echo "Ajustando permissões..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "Iniciando Apache..."
systemctl start apache2
systemctl enable apache2

echo "Provisionamento concluído com sucesso!"
