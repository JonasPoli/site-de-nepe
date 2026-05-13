#!/bin/bash

# Cores para o output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}==> Iniciando limpeza completa do sistema TickePix...${NC}"

# 1. Limpar Cache do Symfony
echo -e "${GREEN}--> Limpando cache do Symfony...${NC}"
php bin/console cache:clear
rm -rf var/cache/*

# 2. Limpar Cache de Imagens (LiipImagine)
if [ -d "public/media/cache" ]; then
    echo -e "${GREEN}--> Removendo miniaturas (LiipImagine)...${NC}"
    rm -rf public/media/cache/*
fi

# 3. Recompilar Tailwind
echo -e "${GREEN}--> Recompilando Tailwind CSS...${NC}"
php bin/console tailwind:build

# 4. Compilar Assets (Asset Mapper)
echo -e "${GREEN}--> Compilando AssetMap...${NC}"
php bin/console asset-map:compile

# 5. Limpar Logs
echo -e "${GREEN}--> Limpando logs...${NC}"
rm -rf var/log/*

echo -e "${BLUE}==> Limpeza concluída com sucesso!${NC}"
