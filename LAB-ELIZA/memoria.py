#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
===========================================================
Módulo de Memória e Persistência do Projeto ELIZA
===========================================================
Este módulo é responsável por gerenciar a conexão com o 
banco de dados SQLite, armazenar a memória de aprendizado 
e o histórico de conversas da ELIZA.
===========================================================
"""

import os
import re
import sqlite3
import unicodedata
from typing import Optional, Tuple, List, Dict, Any

# Caminho do banco de dados SQLite (eliza.db) no mesmo diretório deste arquivo
DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "eliza.db")


def _normalizar_texto(texto: str) -> str:
    """Função interna para normalizar textos removendo acentos e pontuação."""
    texto = texto.lower().strip()
    texto = unicodedata.normalize("NFD", texto)
    texto = "".join(c for c in texto if unicodedata.category(c) != "Mn")
    texto = re.sub(r"[^\w\s]", "", texto)
    return re.sub(r"\s+", " ", texto).strip()


def conectar_banco() -> sqlite3.Connection:
    """Abre e retorna uma conexão com o banco de dados SQLite."""
    return sqlite3.connect(DB_PATH)


def inicializar_banco() -> None:
    """Cria as tabelas 'sessoes', 'mensagens' e 'conhecimento' caso não existam."""
    with conectar_banco() as conn:
        cursor = conn.cursor()
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS sessoes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_fim TIMESTAMP
            );
        """)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS mensagens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sessao_id INTEGER,
                remetente TEXT,
                texto TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sessao_id) REFERENCES sessoes(id)
            );
        """)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS conhecimento (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                palavra_chave TEXT UNIQUE NOT NULL,
                resposta TEXT NOT NULL,
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        conn.commit()


def criar_sessao() -> int:
    """Cria uma nova sessão de conversa e retorna seu ID único."""
    inicializar_banco()
    with conectar_banco() as conn:
        cursor = conn.cursor()
        cursor.execute("INSERT INTO sessoes (data_inicio) VALUES (CURRENT_TIMESTAMP);")
        conn.commit()
        return cursor.lastrowid


def encerrar_sessao(sessao_id: int) -> None:
    """Atualiza a data/hora de encerramento da sessão no banco de dados."""
    try:
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("""
                UPDATE sessoes
                SET data_fim = CURRENT_TIMESTAMP
                WHERE id = ?;
            """, (sessao_id,))
            conn.commit()
    except sqlite3.Error as e:
        print(f"[Erro no BD]: Falha ao encerrar sessão - {e}")


def salvar_conversa(sessao_id: int, remetente: str, texto: str) -> None:
    """Salva uma mensagem enviada por 'VOCÊ' ou pela 'ELIZA' no banco de dados."""
    try:
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO mensagens (sessao_id, remetente, texto)
                VALUES (?, ?, ?);
            """, (sessao_id, remetente, texto))
            conn.commit()
    except sqlite3.Error as e:
        print(f"[Erro no BD]: Falha ao salvar conversa - {e}")


def salvar_memoria(palavra_chave: str, resposta: str) -> Tuple[bool, str]:
    """Salva ou atualiza um conceito/aprendizado dinâmico na tabela de conhecimento."""
    palavra_norm = _normalizar_texto(palavra_chave)
    resposta_limpa = resposta.strip()

    if not palavra_norm or not resposta_limpa:
        return False, "Palavra-chave ou resposta inválida."

    try:
        inicializar_banco()
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO conhecimento (palavra_chave, resposta)
                VALUES (?, ?)
                ON CONFLICT(palavra_chave) DO UPDATE SET resposta=excluded.resposta;
            """, (palavra_norm, resposta_limpa))
            conn.commit()
            return True, f"Entendido! Aprendi que quando você falar sobre '{palavra_norm}', devo responder: '{resposta_limpa}'"
    except sqlite3.Error as e:
        return False, f"Erro ao salvar memória: {e}"


def buscar_memoria(texto: str) -> Optional[str]:
    """Busca no banco se há uma resposta aprendida cadastrada para alguma palavra-chave contida no texto."""
    texto_norm = _normalizar_texto(texto)
    try:
        inicializar_banco()
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT palavra_chave, resposta FROM conhecimento ORDER BY LENGTH(palavra_chave) DESC;")
            for p_chave, resposta in cursor.fetchall():
                padrao = r"\b" + re.escape(p_chave) + r"\b"
                if re.search(padrao, texto_norm, re.IGNORECASE):
                    return resposta
    except sqlite3.Error:
        pass
    return None


def remover_memoria(palavra_chave: str) -> Tuple[bool, str]:
    """Remove um conhecimento cadastrado na memória."""
    palavra_norm = _normalizar_texto(palavra_chave)
    try:
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("DELETE FROM conhecimento WHERE palavra_chave = ?;", (palavra_norm,))
            conn.commit()
            if cursor.rowcount > 0:
                return True, f"Conhecimento sobre '{palavra_norm}' foi removido com sucesso."
            else:
                return False, f"Nenhum conhecimento encontrado para '{palavra_norm}'."
    except sqlite3.Error as e:
        return False, f"Erro ao remover memória: {e}"


def buscar_historico(limite: int = 5) -> List[Dict[str, Any]]:
    """Recupera o histórico das últimas sessões e mensagens gravadas."""
    historico = []
    try:
        inicializar_banco()
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT id, data_inicio, data_fim FROM sessoes ORDER BY id DESC LIMIT ?;", (limite,))
            sessoes = cursor.fetchall()

            for s_id, d_inicio, d_fim in sessoes:
                cursor.execute("""
                    SELECT remetente, texto, timestamp 
                    FROM mensagens 
                    WHERE sessao_id = ? 
                    ORDER BY id ASC;
                """, (s_id,))
                msgs = cursor.fetchall()
                historico.append({
                    "id": s_id,
                    "inicio": d_inicio,
                    "fim": d_fim,
                    "mensagens": msgs
                })
    except sqlite3.Error as e:
        print(f"[Erro no BD]: Falha ao buscar histórico - {e}")

    return historico


def listar_memorias() -> List[Tuple[str, str, str]]:
    """Retorna todas as memórias / aprendizados salvos."""
    try:
        inicializar_banco()
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT palavra_chave, resposta, data_criacao FROM conhecimento ORDER BY id ASC;")
            return cursor.fetchall()
    except sqlite3.Error:
        return []


def obter_status_memoria() -> Tuple[bool, int, int, int]:
    """Retorna o status da conexão e totais de sessões, mensagens e memórias registradas."""
    try:
        inicializar_banco()
        with conectar_banco() as conn:
            cursor = conn.cursor()
            cursor.execute("SELECT COUNT(*) FROM sessoes;")
            total_s = cursor.fetchone()[0]
            cursor.execute("SELECT COUNT(*) FROM mensagens;")
            total_m = cursor.fetchone()[0]
            cursor.execute("SELECT COUNT(*) FROM conhecimento;")
            total_c = cursor.fetchone()[0]
            return True, total_s, total_m, total_c
    except sqlite3.Error:
        return False, 0, 0, 0
