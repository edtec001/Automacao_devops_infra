#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
===========================================================
Projeto ELIZA - Versão com Módulo de Memória (POO)
===========================================================
Uma recriação do chatbot ELIZA integrada ao módulo 'memoria.py'
para persistência em SQLite, aprendizado dinâmico e histórico.

Linguagem: Python 3
===========================================================
"""

import os
import re
import random
import unicodedata
from typing import Optional, Tuple, List, Dict, Any

# Importa o módulo de memória e banco de dados
import memoria


class ElizaBot:
    """Classe principal com a lógica do chatbot ELIZA."""

    RESPOSTAS_GENERICAS = [
        "Conte-me mais sobre isso.",
        "Por que você diz isso?",
        "Como você se sente em relação a isso?",
        "Pode explicar melhor?",
        "O que isso significa para você?",
        "Continue...",
        "Interessante. Continue falando.",
        "Você poderia desenvolver esse pensamento?",
        "Como você se sente?"
    ]

    REFLEXOES = {
        "meu": "seu",
        "minha": "sua",
        "meus": "seus",
        "minhas": "suas",
        "comigo": "com você",
        "mim": "você",
        "me": "se",
        "eu": "você"
    }

    REGRAS = [
        (r"\b(oi|ola|bom dia|boa tarde|boa noite)\b", ["Olá. Como você está?", "Olá. Sobre o que você gostaria de conversar?", "Olá. Pode falar comigo."]),
        (r"\b(mae)\b", ["Fale mais sobre sua mãe.", "Como é o seu relacionamento com sua mãe?", "Sua mãe é importante para você?"]),
        (r"\b(pai)\b", ["Fale mais sobre seu pai.", "Como é o seu relacionamento com seu pai?", "Seu pai influencia suas decisões?"]),
        (r"\b(familia)\b", ["Fale mais sobre sua família.", "Como você se sente em relação à sua família?", "Sua família costuma apoiar você?"]),
        (r"\b(trabalho|emprego|chefe|empresa)\b", ["Conte-me mais sobre seu trabalho.", "Você está satisfeito com seu trabalho?", "Como seu trabalho faz você se sentir?", "Existe algum problema relacionado ao seu trabalho?"]),
        (r"\b(medo|assustado|assustada|receio)\b", ["Por que você sente medo?", "O que exatamente causa esse medo?", "Você costuma sentir esse medo com frequência?"]),
        (r"\b(feliz|felicidade|alegre|alegria)\b", ["O que faz você se sentir feliz?", "Você costuma sentir essa felicidade com frequência?", "Fale mais sobre o que deixa você feliz."]),
        (r"\b(triste|tristeza|deprimido|deprimida)\b", ["Por que você está se sentindo assim?", "Há quanto tempo você se sente assim?", "O que você acha que está causando essa tristeza?"]),
        (r"\b(amor|amo|apaixonado|apaixonada)\b", ["Fale mais sobre seus sentimentos.", "O que o amor significa para você?", "Essa pessoa é importante para você?"]),
        (r"\b(problema|problemas|dificil)\b", ["Por que isso é um problema?", "Como esse problema faz você se sentir?", "Você já tentou resolver esse problema?", "Conte-me mais sobre esse problema."]),
        (r"\b(computador|linux|python|programacao|tecnologia)\b", ["Fale mais sobre seu interesse em tecnologia.", "O que mais lhe interessa nessa área?", "Você gosta de aprender novas tecnologias?"]),
        (r"\b(eliza)\b", ["Você está falando comigo?", "Por que você está interessado em ELIZA?", "O que você espera de mim?"])
    ]

    TRANSFORMACOES = [
        (r"^eu\s+estou\s+(.+)", ["Por que você está {0}?", "Há quanto tempo você está {0}?", "Como você se sente estando {0}?"]),
        (r"^eu\s+sinto\s+(.+)", ["Por que você sente {0}?", "O que faz você sentir {0}?", "Você costuma sentir {0}?"]),
        (r"^eu\s+quero\s+(.+)", ["Por que você quer {0}?", "O que faria você conseguir {0}?", "O que significa para você {0}?"]),
        (r"^eu\s+acho\s+que\s+(.+)", ["Por que você acha que {0}?", "Você tem certeza de que {0}?", "O que levou você a pensar que {0}?"]),
        (r"^eu\s+tenho\s+(.+)", ["Por que você tem {0}?", "Há quanto tempo você tem {0}?", "O que significa para você ter {0}?"]),
        (r"^eu\s+gosto\s+de\s+(.+)", ["Por que você gosta de {0}?", "O que mais agrada você sobre {0}?", "Há quanto tempo você gosta de {0}?"]),
        (r"^eu\s+nao\s+consigo\s+(.+)", ["Por que você não consegue {0}?", "O que impede você de {0}?", "O que aconteceria se você conseguisse {0}?"]),
        (r"^eu\s+nao\s+(.+)", ["Por que você não {0}?", "Você gostaria de {0}?", "O que impede você de {0}?"]),
        (r"^eu\s+(.+)", ["Por que você diz que {0}?", "Você realmente {0}?", "O que faz você {0}?", "Como você se sente quando {0}?"]),
        (r"^minha\s+(.+)", ["Conte-me mais sobre como a sua {0}.", "Por que você acha que a sua {0}?", "Como você se sente sabendo que a sua {0}?"]),
        (r"^meu\s+(.+)", ["Conte-me mais sobre como o seu {0}.", "Por que você acha que o seu {0}?", "Como você se sente sabendo que o seu {0}?"])
    ]

    SAIDA = ["tchau", "adeus", "sair", "exit", "quit", "fim"]

    def __init__(self):
        self.sessao_id: Optional[int] = None

    @staticmethod
    def normalizar(texto: str) -> str:
        """Normaliza o texto convertendo para minúsculas, removendo pontuações e acentos."""
        texto = texto.lower().strip()
        texto = unicodedata.normalize("NFD", texto)
        texto = "".join(c for c in texto if unicodedata.category(c) != "Mn")
        texto = re.sub(r"[^\w\s]", "", texto)
        return re.sub(r"\s+", " ", texto).strip()

    def refletir(self, texto: str) -> str:
        """Substitui pronomes e possessivos para inverter o ponto de vista."""
        palavras = texto.split()
        palavras_refletidas = [self.REFLEXOES.get(p.lower(), p) for p in palavras]
        return " ".join(palavras_refletidas)

    def procurar_regra(self, texto_norm: str) -> Optional[str]:
        """Procura correspondência de palavras-chave estáticas nas regras."""
        for padrao, respostas in self.REGRAS:
            if re.search(padrao, texto_norm, re.IGNORECASE):
                return random.choice(respostas)
        return None

    def transformar_frase(self, texto_norm: str) -> Optional[str]:
        """Tenta transformar a frase baseada nos padrões cadastrados."""
        for padrao, respostas in self.TRANSFORMACOES:
            resultado = re.search(padrao, texto_norm, re.IGNORECASE)
            if resultado:
                conteudo = self.refletir(resultado.group(1))
                resposta = random.choice(respostas)
                return resposta.format(conteudo)
        return None

    def responder(self, texto: str) -> str:
        """Gera a resposta da ELIZA consultando memória dinâmica, regras ou transformações."""
        texto_norm = self.normalizar(texto)

        if not texto_norm:
            return random.choice(self.RESPOSTAS_GENERICAS)

        # 1. Consulta memória aprendida no BD (via modulo memoria)
        resposta_memoria = memoria.buscar_memoria(texto_norm)
        if resposta_memoria:
            return resposta_memoria

        # 2. Regras de palavra-chave estáticas
        resposta = self.procurar_regra(texto_norm)
        if resposta:
            return resposta

        # 3. Transformação de frases
        resposta = self.transformar_frase(texto_norm)
        if resposta:
            return resposta

        # 4. Resposta genérica
        return random.choice(self.RESPOSTAS_GENERICAS)

    def exibir_ajuda(self) -> None:
        """Exibe o menu interativo de ajuda."""
        print("\n" + "=" * 60)
        print("                 MENU DE COMANDOS DA ELIZA")
        print("=" * 60)
        print(" • aprender: <termo> = <resposta> : Ensina algo novo à ELIZA.")
        print(" • esquecer <termo> / deletar     : Remove um aprendizado do banco.")
        print(" • aprendizados                   : Lista tudo o que foi aprendido.")
        print(" • historico                      : Exibe conversas anteriores.")
        print(" • status                         : Mostra o status da memória.")
        print(" • ajuda / help                   : Mostra este menu de ajuda.")
        print(" • sair / tchau                   : Encerra o programa.")
        print("=" * 60 + "\n")

    def exibir_historico_formatado(self) -> None:
        """Exibe o histórico de forma legível usando buscar_historico do modulo memoria."""
        historico = memoria.buscar_historico()
        if not historico:
            print("\n[Memória] Nenhuma sessão encontrada no histórico.\n")
            return

        print("\n" + "=" * 60)
        print("           HISTÓRICO DE CONVERSAS (Últimas Sessões)")
        print("=" * 60)
        for s in historico:
            print(f"\n--- Sessão #{s['id']} | Início: {s['inicio']} | Fim: {s['fim'] or 'Em andamento'} ---")
            for remetente, texto, ts in s['mensagens']:
                print(f"[{ts}] {remetente}: {texto}")
        print("=" * 60 + "\n")

    def exibir_aprendizados_formatado(self) -> None:
        """Exibe a lista de conceitos salvos na memória."""
        itens = memoria.listar_memorias()
        if not itens:
            print("\n[Memória] Nenhum conhecimento personalizado aprendido ainda.\n")
            return

        print("\n" + "=" * 60)
        print("           CONHECIMENTOS APRENDIDOS PELA ELIZA")
        print("=" * 60)
        for p_chave, resp, dt in itens:container
            print(f"• Termo: '{p_chave}' => Resposta: '{resp}' ({dt})")
        print("=" * 60 + "\n")

    def exibir_status(self) -> None:
        """Exibe o status e métricas da memória."""
        ativo, total_s, total_m, total_c = memoria.obter_status_memoria()
        print("\n" + "=" * 60)
        print("                 STATUS DA MEMÓRIA E BANCO DE DADOS")
        print("=" * 60)
        print(f" Status Conexão : {'ONLINE' if ativo else 'OFFLINE'}")
        print(f" Arquivo BD     : '{os.path.basename(memoria.DB_PATH)}'")
        print(f" Sessão Atual   : #{self.sessao_id}")
        print(f" Total Sessões  : {total_s}")
        print(f" Total Mensagens: {total_m}")
        print(f" Aprendizados   : {total_c}")
        print("=" * 60 + "\n")

    def iniciar(self) -> None:
        """Inicia a sessão interativa da ELIZA."""
        self.sessao_id = memoria.criar_sessao()
        ativo, total_s, total_m, total_c = memoria.obter_status_memoria()

        print("=" * 60)
        print("             PROJETO ELIZA (Módulo Memória Integrado)")
        print("=" * 60)

        if ativo:
            print(f"[BD Status]: ONLINE | Sessão Atual: #{self.sessao_id} | Arquivo: '{os.path.basename(memoria.DB_PATH)}'")
            print(f"[BD Dados] : {total_s} sessões | {total_m} mensagens | {total_c} aprendizados")
        else:
            print("[BD Status]: ERRO AO CONECTAR COM O BANCO DE DADOS")
        print("-" * 60)

        print()
        saudacao1 = "Olá. Eu sou ELIZA."
        saudacao2 = "Sobre o que você gostaria de conversar?"
        print(f"ELIZA: {saudacao1}")
        print(f"ELIZA: {saudacao2}")

        memoria.salvar_conversa(self.sessao_id, "ELIZA", saudacao1)
        memoria.salvar_conversa(self.sessao_id, "ELIZA", saudacao2)

        print()
        print("Digite 'ajuda' para ver os comandos disponíveis.")
        print()

        while True:
            try:
                usuario = input("VOCÊ: ")
            except (KeyboardInterrupt, EOFError):
                msg_adeus = "Até logo."
                print(f"\nELIZA: {msg_adeus}")
                memoria.salvar_conversa(self.sessao_id, "ELIZA", msg_adeus)
                memoria.encerrar_sessao(self.sessao_id)
                break

            usuario_norm = self.normalizar(usuario)

            if not usuario_norm:
                continue

            # Grava a entrada do usuário na memória
            memoria.salvar_conversa(self.sessao_id, "VOCÊ", usuario)

            # 1. Comando de Aprendizado: 'aprender: <termo> = <resposta>'
            if usuario.lower().startswith("aprender:") or usuario.lower().startswith("aprender "):
                conteudo = usuario[len("aprender"):].lstrip(": ").strip()
                if "=" in conteudo:
                    p_chave, resp = conteudo.split("=", 1)
                    sucesso, msg = memoria.salvar_memoria(p_chave, resp)
                    print(f"ELIZA: {msg}")
                    memoria.salvar_conversa(self.sessao_id, "ELIZA", msg)
                else:
                    msg_erro = "Para me ensinar, use o formato: aprender: <palavra_chave> = <resposta>"
                    print(f"ELIZA: {msg_erro}")
                    memoria.salvar_conversa(self.sessao_id, "ELIZA", msg_erro)
                continue

            # 2. Comando de Remoção: 'esquecer <termo>' ou 'deletar <termo>'
            if usuario.lower().startswith("esquecer ") or usuario.lower().startswith("deletar "):
                termo = usuario.split(" ", 1)[1].strip()
                sucesso, msg = memoria.remover_memoria(termo)
                print(f"ELIZA: {msg}")
                memoria.salvar_conversa(self.sessao_id, "ELIZA", msg)
                continue

            # 3. Comando de Ajuda
            if usuario_norm in ["ajuda", "help", "comandos"]:
                self.exibir_ajuda()
                continue

            # 4. Comando de Status
            if usuario_norm == "status":
                self.exibir_status()
                continue

            # 5. Comando de Aprendizadoscontainer
            if usuario_norm in ["aprendizados", "conhecimentos"]:
                self.exibir_aprendizados_formatado()
                continue

            # 6. Comando de Histórico
            if usuario_norm in ["historico", "buscar_historico"]:
                self.exibir_historico_formatado()
                continuecontainer

            # 7. Verifica encerramento
            if usuario_norm in self.SAIDA:
                msg_fim1 = "Foi interessante conversar com você."
                msg_fim2 = "Até a próxima."
                print(f"ELIZA: {msg_fim1}")
                print(f"ELIZA: {msg_fim2}")

                memoria.salvar_conversa(self.sessao_id, "ELIZA", msg_fim1)
                memoria.salvar_conversa(self.sessao_id, "ELIZA", msg_fim2)
                memoria.encerrar_sessao(self.sessao_id)
                break

            # Resposta Padrão do Bot
            resposta = self.responder(usuario)
            print(f"ELIZA: {resposta}")
            memoria.salvar_conversa(self.sessao_id, "ELIZA", resposta)


# =========================================================
# FUNÇÕES STANDALONE E DE COMPATIBILIDADE
# =========================================================

_bot_instancia: Optional[ElizaBot] = None


def _obter_bot() -> ElizaBot:
    global _bot_instancia
    if _bot_instancia is None:
        _bot_instancia = ElizaBot()
    return _bot_instancia


def normalizar(texto: str) -> str:
    return _obter_bot().normalizar(texto)


def buscar_memoria(texto: str) -> Optional[str]:
    return memoria.buscar_memoria(texto)


def buscar_historico(limite: int = 5) -> List[Dict[str, Any]]:
    return memoria.buscar_historico(limite)


def salvar_memoria(palavra_chave: str, resposta: str) -> Tuple[bool, str]:
    return memoria.salvar_memoria(palavra_chave, resposta)


def salvar_conversa(sessao_id: int, remetente: str, texto: str) -> None:
    memoria.salvar_conversa(sessao_id, remetente, texto)


def eliza_responde(texto: str) -> str:
    return _obter_bot().responder(texto)


# =========================================================
# EXECUÇÃO
# =========================================================

def iniciar_eliza():
    bot = _obter_bot()
    bot.iniciar()


if __name__ == "__main__":
    iniciar_eliza()