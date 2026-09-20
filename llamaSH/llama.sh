#!/bin/bash

# Caminho exato onde estão as bibliotecas e o executável
LLAMA_DIR="/home/tlk/Documents/AIX/llama.cpp"
LLAMA_SERVER="$LLAMA_DIR/llama-server"

# Caminho exato de onde o modelo está na sua pasta web
MODEL_PATH="/var/www/html/model/Qwen3.8-2B-Uncensored-Q4_K_M.gguf"

chmod +x "$LLAMA_SERVER"
pkill -f "llama-server"

echo "Iniciando LLM na porta 8082..."

# Executa usando os caminhos absolutos corrigidos
nohup env LD_LIBRARY_PATH="$LLAMA_DIR" "$LLAMA_SERVER" \
-m "$MODEL_PATH" \
--host 127.0.0.1 \
--port 8082 \
-c 2048 \
-b 256 \
-t 6 > /var/www/html/erro.log 2>&1 &

sleep 3
